<?php

namespace App\Models;

use PDO;

class RoutineDayModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function findByRoutine(int $routineId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM routine_days WHERE routine_id = :routine_id ORDER BY order_index, day_of_week'
        );
        $stmt->execute(['routine_id' => $routineId]);
        return $stmt->fetchAll();
    }

    /** @param int[] $routineIds */
    public function findByRoutines(array $routineIds): array
    {
        if (empty($routineIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($routineIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT * FROM routine_days WHERE routine_id IN ($placeholders)
             ORDER BY routine_id, order_index, day_of_week"
        );
        $stmt->execute(array_values($routineIds));
        return $stmt->fetchAll();
    }

    public function deleteByRoutine(int $routineId): void
    {
        $stmt = $this->db->prepare('DELETE FROM routine_days WHERE routine_id = :routine_id');
        $stmt->execute(['routine_id' => $routineId]);
    }

    public function create(int $routineId, array $day, int $orderIndex): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO routine_days (routine_id, day_of_week, label, is_rest_day, order_index)
             VALUES (:routine_id, :day_of_week, :label, :is_rest_day, :order_index)'
        );
        $stmt->execute([
            'routine_id' => $routineId,
            'day_of_week' => $day['day_of_week'],
            'label' => $day['label'] ?? null,
            'is_rest_day' => !empty($day['is_rest_day']) ? 1 : 0,
            'order_index' => $orderIndex,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM routine_days WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Routine day, only if its parent routine belongs to $userId. */
    public function findOwnedByUser(int $userId, int $dayId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT rd.* FROM routine_days rd
             INNER JOIN workout_routines wr ON wr.id = rd.routine_id
             WHERE rd.id = :id AND wr.user_id = :user_id'
        );
        $stmt->execute(['id' => $dayId, 'user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
