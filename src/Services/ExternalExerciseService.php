<?php

namespace App\Services;

use RuntimeException;

/**
 * Cliente de la API pública de wger.de (wger.de/api/v2) para explorar un
 * catálogo de ejercicios mucho más grande que el propio, y poder
 * "importarlos" (adaptados al modelo de FitTrack) al catálogo del usuario.
 * No requiere API key.
 */
class ExternalExerciseService
{
    private const BASE_URL = 'https://wger.de/api/v2';
    private const LANGUAGE_ENGLISH = 2;
    private const PAGE_SIZE = 20;

    /** Categorías de wger (Chest, Back, Legs, ...) — para el filtro. */
    public function categories(): array
    {
        $data = $this->request('/exercisecategory/', []);

        return array_map(static fn (array $category): array => [
            'id' => (int) $category['id'],
            'name' => (string) $category['name'],
        ], $data['results'] ?? []);
    }

    public function search(?int $categoryId, ?string $query, int $page): array
    {
        $page = max(1, $page);
        $params = [
            'limit' => self::PAGE_SIZE,
            'offset' => ($page - 1) * self::PAGE_SIZE,
        ];
        if ($categoryId !== null) {
            $params['category'] = $categoryId;
        }

        $data = $this->request('/exerciseinfo/', $params);

        $exercises = [];
        foreach (($data['results'] ?? []) as $item) {
            $translation = $this->englishTranslation($item['translations'] ?? []);
            if ($translation === null || empty($translation['name'])) {
                continue; // sin nombre en inglés, no sirve para mostrar
            }

            $name = $translation['name'];
            if ($query !== null && $query !== '' && stripos($name, $query) === false) {
                continue;
            }

            $exercises[] = [
                'external_id' => (int) $item['id'],
                'name' => $name,
                'description' => $this->cleanDescription($translation['description'] ?? ''),
                'category' => $item['category']['name'] ?? null,
                'muscles' => array_values(array_filter(array_map(
                    static fn (array $m): string => $m['name_en'] !== '' ? $m['name_en'] : $m['name'],
                    $item['muscles'] ?? []
                ))),
                'equipment' => array_map(static fn (array $e): string => $e['name'], $item['equipment'] ?? []),
                'image_url' => $this->mainImageUrl($item['images'] ?? []),
            ];
        }

        return [
            'exercises' => $exercises,
            'total' => (int) ($data['count'] ?? 0),
            'page' => $page,
        ];
    }

    private function englishTranslation(array $translations): ?array
    {
        foreach ($translations as $translation) {
            if ((int) ($translation['language'] ?? 0) === self::LANGUAGE_ENGLISH) {
                return $translation;
            }
        }
        return null;
    }

    private function mainImageUrl(array $images): ?string
    {
        if (empty($images)) {
            return null;
        }

        foreach ($images as $image) {
            if (!empty($image['is_main'])) {
                return $image['thumbnails']['medium'] ?? $image['image'] ?? null;
            }
        }

        return $images[0]['thumbnails']['medium'] ?? $images[0]['image'] ?? null;
    }

    private function cleanDescription(string $html): string
    {
        $text = trim(strip_tags($html));
        return mb_substr($text, 0, 300);
    }

    private function request(string $path, array $params): array
    {
        $params['format'] = 'json';
        $url = self::BASE_URL . $path . '?' . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new RuntimeException('No se pudo contactar la API externa de ejercicios: ' . $error);
        }
        if ($status >= 400) {
            throw new RuntimeException('La API externa de ejercicios respondió con un error (' . $status . ')');
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Respuesta inválida de la API externa de ejercicios');
        }

        return $decoded;
    }
}
