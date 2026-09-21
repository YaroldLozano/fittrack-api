<?php

namespace App\Services;

use App\Models\ExerciseModel;
use InvalidArgumentException;
use RuntimeException;

/**
 * Asistente de IA que ajusta el entrenamiento del día a partir de una
 * petición en lenguaje natural (ver sección "Herramienta de IA" del pedido
 * de rediseño). Usa la API de Claude (Anthropic) con tool-use forzado para
 * obtener siempre una propuesta estructurada y nunca prosa libre.
 */
class AIService
{
    public function __construct(
        private readonly ExerciseModel $exercises,
        private readonly string $apiKey,
        private readonly string $model
    ) {
    }

    public function suggestWorkout(int $userId, string $prompt, array $currentExercises = []): array
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            throw new InvalidArgumentException('Cuéntame qué quieres ajustar en tu entrenamiento de hoy');
        }
        if (mb_strlen($prompt) > 500) {
            throw new InvalidArgumentException('La solicitud es demasiado larga (máx. 500 caracteres)');
        }
        if ($this->apiKey === '') {
            throw new RuntimeException('La IA no está configurada todavía. Falta ANTHROPIC_API_KEY en el servidor.');
        }

        $catalog = $this->exercises->findVisibleTo($userId);
        if (empty($catalog)) {
            throw new InvalidArgumentException('Todavía no tienes ejercicios disponibles en tu catálogo');
        }

        $catalogForPrompt = array_map(static fn (array $ex): array => [
            'id' => (int) $ex['id'],
            'name' => $ex['name'],
            'muscle_group' => $ex['muscle_group_name'],
            'equipment' => $ex['equipment'],
        ], $catalog);

        $toolUse = $this->requestProposal($catalogForPrompt, $prompt, $currentExercises);

        return $this->sanitizeProposal($toolUse, $catalogForPrompt);
    }

    private function requestProposal(array $catalogForPrompt, string $prompt, array $currentExercises): array
    {
        $systemPrompt = 'Eres el asistente de entrenamiento de FitTrack, una app de fitness. El usuario te pide '
            . 'ajustes para el entrenamiento de HOY (por ejemplo: poco tiempo, equipo no disponible, enfoque en '
            . 'un grupo muscular, nivel de intensidad, o una molestia/lesión a evitar). '
            . 'Propón un entrenamiento usando EXCLUSIVAMENTE ejercicios del catálogo dado, referenciándolos por su '
            . '"id" exacto — nunca inventes un exercise_id que no esté en el catálogo. Ajusta series, repeticiones, '
            . 'peso objetivo y descanso según lo que pida el usuario; si no da información suficiente, usa buen '
            . 'criterio de entrenador personal (3-6 ejercicios, 3-4 series, 8-12 reps, 60-90s de descanso como '
            . 'valores por defecto razonables). Responde siempre en español, incluso si el usuario escribe en otro idioma.';

        $userMessage = "Catálogo de ejercicios disponibles (JSON):\n"
            . json_encode($catalogForPrompt, JSON_UNESCAPED_UNICODE)
            . "\n\nEntrenamiento planeado actualmente para hoy (puede venir vacío):\n"
            . json_encode(array_values($currentExercises), JSON_UNESCAPED_UNICODE)
            . "\n\nSolicitud del usuario: " . $prompt;

        $tool = [
            'name' => 'propose_workout',
            'description' => 'Propone el entrenamiento ajustado para hoy a partir del catálogo de ejercicios.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'name' => [
                        'type' => 'string',
                        'description' => 'Nombre corto del entrenamiento propuesto (ej. "Tren superior express")',
                    ],
                    'note' => [
                        'type' => 'string',
                        'description' => 'Explicación breve (1-2 frases, en español) de los ajustes realizados',
                    ],
                    'exercises' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'exercise_id' => ['type' => 'integer', 'description' => 'Debe existir en el catálogo dado'],
                                'sets' => ['type' => 'integer'],
                                'reps' => ['type' => 'integer'],
                                'target_weight' => ['type' => ['number', 'null']],
                                'rest_seconds' => ['type' => 'integer'],
                            ],
                            'required' => ['exercise_id', 'sets', 'reps', 'rest_seconds'],
                        ],
                    ],
                ],
                'required' => ['name', 'note', 'exercises'],
            ],
        ];

        $payload = [
            'model' => $this->model,
            'max_tokens' => 1500,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userMessage],
            ],
            'tools' => [$tool],
            'tool_choice' => ['type' => 'tool', 'name' => 'propose_workout'],
        ];

        $response = $this->callAnthropic($payload);

        foreach ($response['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'tool_use' && ($block['name'] ?? '') === 'propose_workout') {
                return is_array($block['input'] ?? null) ? $block['input'] : [];
            }
        }

        throw new RuntimeException('La IA no devolvió una propuesta válida. Intenta de nuevo.');
    }

    /** Descarta cualquier exercise_id alucinado fuera del catálogo y normaliza los tipos. */
    private function sanitizeProposal(array $toolUse, array $catalogForPrompt): array
    {
        $names = array_column($catalogForPrompt, 'name', 'id');

        $validExercises = [];
        foreach (($toolUse['exercises'] ?? []) as $ex) {
            $exerciseId = (int) ($ex['exercise_id'] ?? 0);
            if (!isset($names[$exerciseId])) {
                continue;
            }
            $validExercises[] = [
                'exercise_id' => $exerciseId,
                'exercise_name' => $names[$exerciseId],
                'sets' => max(1, (int) ($ex['sets'] ?? 3)),
                'reps' => max(1, (int) ($ex['reps'] ?? 10)),
                'target_weight' => isset($ex['target_weight']) && $ex['target_weight'] !== null
                    ? (float) $ex['target_weight']
                    : null,
                'rest_seconds' => max(0, (int) ($ex['rest_seconds'] ?? 60)),
            ];
        }

        if (empty($validExercises)) {
            throw new RuntimeException('La IA no propuso ejercicios válidos. Intenta reformular tu pedido.');
        }

        return [
            'name' => (string) ($toolUse['name'] ?? 'Entrenamiento sugerido'),
            'note' => (string) ($toolUse['note'] ?? ''),
            'exercises' => $validExercises,
        ];
    }

    private function callAnthropic(array $payload): array
    {
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'content-type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 30,
        ]);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new RuntimeException('No se pudo contactar al servicio de IA: ' . $error);
        }

        $decoded = json_decode((string) $raw, true);

        if ($status >= 400) {
            $message = $decoded['error']['message'] ?? 'Error del servicio de IA';
            throw new RuntimeException($message);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Respuesta inválida del servicio de IA');
        }

        return $decoded;
    }
}
