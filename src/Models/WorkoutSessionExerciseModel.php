<?php

namespace App\Models;

use PDO;

class WorkoutSessionExerciseModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function findBySession(int $sessionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT wse.*, e.name AS exercise_name, e.muscle_group_id
             FROM workout_session_exercises wse
             JOIN exercises e ON e.id = wse.exercise_id
             WHERE wse.session_id = :session_id
             ORDER BY wse.order_index'
        );
        $stmt->execute(['session_id' => $sessionId]);
        return $stmt->fetchAll();
    }

    public function findOne(int $sessionId, int $exerciseId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM workout_session_exercises WHERE session_id = :session_id AND exercise_id = :exercise_id'
        );
        $stmt->execute(['session_id' => $sessionId, 'exercise_id' => $exerciseId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $sessionId, int $exerciseId, int $orderIndex, ?array $plannedFrom = null): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO workout_session_exercises
                (session_id, exercise_id, order_index, planned_sets, planned_reps, planned_weight, planned_rest_seconds)
             VALUES
                (:session_id, :exercise_id, :order_index, :planned_sets, :planned_reps, :planned_weight, :planned_rest_seconds)'
        );
        $stmt->execute([
            'session_id' => $sessionId,
            'exercise_id' => $exerciseId,
            'order_index' => $orderIndex,
            'planned_sets' => $plannedFrom['sets'] ?? null,
            'planned_reps' => $plannedFrom['reps'] ?? null,
            'planned_weight' => $plannedFrom['target_weight'] ?? null,
            'planned_rest_seconds' => $plannedFrom['rest_seconds'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }
}
