<?php

namespace App\Services;

use App\Models\GoalModel;
use InvalidArgumentException;

class GoalService
{
    private const TYPES = ['exercise_weight', 'workout_frequency', 'body_weight', 'routine_completion', 'custom'];

    public function __construct(
        private readonly GoalModel $goals,
        private readonly GamificationService $gamification
    ) {
    }

    public function list(int $userId): array
    {
        return array_map([$this, 'withProgress'], $this->goals->findAllForUser($userId));
    }

    public function create(int $userId, array $data): array
    {
        if (empty($data['title'])) {
            throw new InvalidArgumentException('El título del objetivo es requerido');
        }
        if (empty($data['type']) || !in_array($data['type'], self::TYPES, true)) {
            throw new InvalidArgumentException('Tipo de objetivo inválido');
        }

        $id = $this->goals->create($userId, $data);
        return $this->withProgress($this->goals->findOwnedBy($userId, $id));
    }

    public function update(int $userId, int $id, array $data): array
    {
        $before = $this->goals->findOwnedBy($userId, $id);
        if ($before === null) {
            throw new InvalidArgumentException('Objetivo no encontrado');
        }

        if (($data['status'] ?? null) === 'completed') {
            $data['completed_at'] = date('Y-m-d H:i:s');
        }

        $this->goals->updateOwned($userId, $id, $data);

        if ($before['status'] !== 'completed' && ($data['status'] ?? null) === 'completed') {
            $this->gamification->onGoalCompleted($userId, $id);
        }

        return $this->withProgress($this->goals->findOwnedBy($userId, $id));
    }

    /** Called after a set is logged: updates any in-progress "lift X kg" goals for that exercise. */
    public function onSetLogged(int $userId, int $exerciseId, float $weight): void
    {
        foreach ($this->goals->findInProgressByType($userId, 'exercise_weight', $exerciseId) as $goal) {
            $current = max((float) $goal['current_value'], $weight);
            $completed = $goal['target_value'] !== null && $current >= (float) $goal['target_value'];
            $this->goals->updateProgress((int) $goal['id'], $current, $completed);
            if ($completed) {
                $this->gamification->onGoalCompleted($userId, (int) $goal['id']);
            }
        }
    }

    /** Called after a workout session is marked completed. */
    public function onWorkoutCompleted(int $userId, int $completedSessionsCount): void
    {
        foreach ($this->goals->findInProgressByType($userId, 'workout_frequency') as $goal) {
            $completed = $goal['target_value'] !== null && $completedSessionsCount >= (float) $goal['target_value'];
            $this->goals->updateProgress((int) $goal['id'], (float) $completedSessionsCount, $completed);
            if ($completed) {
                $this->gamification->onGoalCompleted($userId, (int) $goal['id']);
            }
        }
    }

    /** Called after a body weight entry is logged. */
    public function onBodyWeightLogged(int $userId, float $weight): void
    {
        foreach ($this->goals->findInProgressByType($userId, 'body_weight') as $goal) {
            $completed = $goal['target_value'] !== null && $weight >= (float) $goal['target_value'];
            $this->goals->updateProgress((int) $goal['id'], $weight, $completed);
            if ($completed) {
                $this->gamification->onGoalCompleted($userId, (int) $goal['id']);
            }
        }
    }

    private function withProgress(array $goal): array
    {
        $target = $goal['target_value'] !== null ? (float) $goal['target_value'] : null;
        $current = (float) $goal['current_value'];

        $goal['progress_percent'] = $target !== null && $target > 0
            ? (int) round(min(100, max(0, ($current / $target) * 100)))
            : ($goal['status'] === 'completed' ? 100 : 0);

        return $goal;
    }
}
