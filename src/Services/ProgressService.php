<?php

namespace App\Services;

use App\Config\GamificationRules;
use App\Models\BodyMetricModel;
use App\Models\ExerciseModel;
use App\Models\ExerciseProgressModel;
use App\Models\WorkoutSessionModel;
use App\Models\WorkoutSetModel;
use InvalidArgumentException;

class ProgressService
{
    public function __construct(
        private readonly WorkoutSetModel $sets,
        private readonly WorkoutSessionModel $sessions,
        private readonly ExerciseModel $exercises,
        private readonly ExerciseProgressModel $exerciseProgress,
        private readonly BodyMetricModel $bodyMetrics
    ) {
    }

    public function general(int $userId): array
    {
        $allSessions = $this->sessions->findAllForUser($userId);
        $completed = array_filter($allSessions, fn ($s) => $s['status'] === 'completed');

        $startOfWeek = date('Y-m-d', strtotime('monday this week'));
        $startOfMonth = date('Y-m-01');

        $workoutsThisWeek = count(array_filter($completed, fn ($s) => $s['scheduled_date'] >= $startOfWeek));
        $workoutsThisMonth = count(array_filter($completed, fn ($s) => $s['scheduled_date'] >= $startOfMonth));
        $totalTimeSeconds = array_sum(array_map(fn ($s) => (int) ($s['duration_seconds'] ?? 0), $completed));

        $allTimeStats = $this->sets->generalStats($userId, '1970-01-01');

        return [
            'workouts_this_week' => $workoutsThisWeek,
            'workouts_this_month' => $workoutsThisMonth,
            'total_workouts_completed' => count($completed),
            'total_time_trained_seconds' => $totalTimeSeconds,
            'total_sets' => (int) $allTimeStats['total_sets'],
            'total_reps' => (int) $allTimeStats['total_reps'],
            'total_volume' => (float) $allTimeStats['total_volume'],
        ];
    }

    public function forExercise(int $userId, int $exerciseId): array
    {
        if ($this->exercises->findVisibleById($userId, $exerciseId) === null) {
            throw new InvalidArgumentException('Ejercicio no encontrado');
        }

        $stats = $this->sets->statsForExercise($userId, $exerciseId);
        $maxWeight = $stats['max_weight'] !== null ? (float) $stats['max_weight'] : null;

        $progress = $this->exerciseProgress->ensureExists($userId, $exerciseId);
        $level = (int) $progress['level'];
        $levelInfo = GamificationRules::levelInfo($level, GamificationRules::EXERCISE_LEVELS);
        $nextLevelInfo = GamificationRules::levelInfo($level + 1, GamificationRules::EXERCISE_LEVELS);

        $latestBodyWeight = $this->bodyMetrics->latestForUser($userId)['weight'] ?? null;
        $relativeStrength = ($maxWeight !== null && $latestBodyWeight !== null && (float) $latestBodyWeight > 0)
            ? round($maxWeight / (float) $latestBodyWeight, 2)
            : null;

        return [
            'exercise_id' => $exerciseId,
            'max_weight' => $maxWeight,
            'max_reps' => $stats['max_reps'] !== null ? (int) $stats['max_reps'] : null,
            'total_volume' => (float) ($stats['total_volume'] ?? 0),
            'total_sets' => (int) ($stats['total_sets'] ?? 0),
            'best_set' => $this->sets->bestSet($userId, $exerciseId),
            'evolution' => $this->sets->evolutionForExercise($userId, $exerciseId),
            'relative_strength' => $relativeStrength,
            'progression' => [
                'level' => $level,
                'level_name' => $levelInfo['name'],
                'badge' => $levelInfo['badge'],
                'xp' => (int) $progress['xp'],
                'xp_to_next_level' => GamificationRules::xpToNextLevel((int) $progress['xp'], GamificationRules::EXERCISE_LEVELS),
                'next_level_name' => $level < 7 ? $nextLevelInfo['name'] : null,
            ],
        ];
    }
}
