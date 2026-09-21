<?php

namespace App\Models;

use PDO;

class RoutineExerciseModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function findByDay(int $routineDayId): array
    {
        $stmt = $this->db->prepare(
            'SELECT re.*, e.name AS exercise_name
             FROM routine_exercises re
             JOIN exercises e ON e.id = re.exercise_id
             WHERE re.routine_day_id = :routine_day_id
             ORDER BY re.order_index'
        );
        $stmt->execute(['routine_day_id' => $routineDayId]);
        return $stmt->fetchAll();
    }

    public function create(int $routineDayId, array $exercise, int $orderIndex): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO routine_exercises
                (routine_day_id, exercise_id, sets, reps, target_weight, rest_seconds, order_index, notes)
             VALUES
                (:routine_day_id, :exercise_id, :sets, :reps, :target_weight, :rest_seconds, :order_index, :notes)'
        );
        $stmt->execute([
            'routine_day_id' => $routineDayId,
            'exercise_id' => $exercise['exercise_id'],
            'sets' => $exercise['sets'] ?? 3,
            'reps' => $exercise['reps'] ?? 10,
            'target_weight' => $exercise['target_weight'] ?? null,
            'rest_seconds' => $exercise['rest_seconds'] ?? null,
            'order_index' => $orderIndex,
            'notes' => $exercise['notes'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM routine_exercises WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
