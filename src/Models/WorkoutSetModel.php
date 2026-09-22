<?php

namespace App\Models;

use PDO;

class WorkoutSetModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function findBySessionExercise(int $sessionExerciseId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM workout_sets WHERE session_exercise_id = :id ORDER BY set_number'
        );
        $stmt->execute(['id' => $sessionExerciseId]);
        return $stmt->fetchAll();
    }

    /** @param int[] $sessionExerciseIds */
    public function findBySessionExercises(array $sessionExerciseIds): array
    {
        if (empty($sessionExerciseIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($sessionExerciseIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT * FROM workout_sets WHERE session_exercise_id IN ($placeholders)
             ORDER BY session_exercise_id, set_number"
        );
        $stmt->execute(array_values($sessionExerciseIds));
        return $stmt->fetchAll();
    }

    /** Best (max) weight ever lifted by this user on this exercise, before the given moment. */
    public function maxWeightBefore(int $userId, int $exerciseId): ?float
    {
        $stmt = $this->db->prepare(
            'SELECT MAX(weight) FROM workout_sets WHERE user_id = :user_id AND exercise_id = :exercise_id'
        );
        $stmt->execute(['user_id' => $userId, 'exercise_id' => $exerciseId]);
        $max = $stmt->fetchColumn();
        return $max !== null ? (float) $max : null;
    }

    public function create(int $sessionExerciseId, int $userId, int $exerciseId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO workout_sets
                (session_exercise_id, user_id, exercise_id, set_number, reps, weight, rest_seconds,
                 completed_at, is_personal_record, previous_best_weight)
             VALUES
                (:session_exercise_id, :user_id, :exercise_id, :set_number, :reps, :weight, :rest_seconds,
                 :completed_at, :is_personal_record, :previous_best_weight)'
        );
        $stmt->execute([
            'session_exercise_id' => $sessionExerciseId,
            'user_id' => $userId,
            'exercise_id' => $exerciseId,
            'set_number' => $data['set_number'],
            'reps' => $data['reps'],
            'weight' => $data['weight'],
            'rest_seconds' => $data['rest_seconds'] ?? null,
            'completed_at' => $data['completed_at'],
            'is_personal_record' => $data['is_personal_record'] ? 1 : 0,
            'previous_best_weight' => $data['previous_best_weight'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function statsForExercise(int $userId, int $exerciseId): array
    {
        $stmt = $this->db->prepare(
            'SELECT MAX(weight) AS max_weight, MAX(reps) AS max_reps,
                    SUM(weight * reps) AS total_volume, COUNT(*) AS total_sets
             FROM workout_sets WHERE user_id = :user_id AND exercise_id = :exercise_id'
        );
        $stmt->execute(['user_id' => $userId, 'exercise_id' => $exerciseId]);
        return $stmt->fetch() ?: [];
    }

    public function bestSet(int $userId, int $exerciseId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM workout_sets WHERE user_id = :user_id AND exercise_id = :exercise_id
             ORDER BY weight DESC, reps DESC LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId, 'exercise_id' => $exerciseId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** One row per day: the max weight lifted that day, for a weight-evolution chart. */
    public function evolutionForExercise(int $userId, int $exerciseId): array
    {
        $stmt = $this->db->prepare(
            "SELECT DATE(completed_at) AS date, MAX(weight) AS max_weight, MAX(reps) AS max_reps
             FROM workout_sets
             WHERE user_id = :user_id AND exercise_id = :exercise_id
             GROUP BY DATE(completed_at)
             ORDER BY date"
        );
        $stmt->execute(['user_id' => $userId, 'exercise_id' => $exerciseId]);
        return $stmt->fetchAll();
    }

    public function generalStats(int $userId, string $sinceDate): array
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS total_sets, COALESCE(SUM(reps), 0) AS total_reps,
                    COALESCE(SUM(weight * reps), 0) AS total_volume
             FROM workout_sets
             WHERE user_id = :user_id AND completed_at >= :since'
        );
        $stmt->execute(['user_id' => $userId, 'since' => $sinceDate]);
        return $stmt->fetch() ?: [];
    }

    /**
     * Mayor % de mejora entre el peso más bajo y el más alto registrados, entre todos los
     * ejercicios del usuario con al menos 2 series (aproximación simple de "progreso").
     */
    public function bestProgressPercent(int $userId): ?float
    {
        $stmt = $this->db->prepare(
            'SELECT MAX(pct) FROM (
                SELECT ((MAX(weight) - MIN(weight)) / NULLIF(MIN(weight), 0)) * 100 AS pct
                FROM workout_sets
                WHERE user_id = :user_id
                GROUP BY exercise_id
                HAVING COUNT(*) >= 2
             ) t'
        );
        $stmt->execute(['user_id' => $userId]);
        $value = $stmt->fetchColumn();
        return $value !== null ? (float) $value : null;
    }

    public function maxWeightEver(int $userId): ?float
    {
        $stmt = $this->db->prepare('SELECT MAX(weight) FROM workout_sets WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        $value = $stmt->fetchColumn();
        return $value !== null ? (float) $value : null;
    }

    public function countPersonalRecords(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM workout_sets WHERE user_id = :user_id AND is_personal_record = 1');
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }
}
