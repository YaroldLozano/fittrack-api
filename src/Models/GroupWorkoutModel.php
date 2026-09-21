<?php

namespace App\Models;

use PDO;

class GroupWorkoutModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function create(int $creatorId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO group_workouts (creator_id, name, scheduled_date, scheduled_time, routine_id, status)
             VALUES (:creator_id, :name, :scheduled_date, :scheduled_time, :routine_id, "scheduled")'
        );
        $stmt->execute([
            'creator_id' => $creatorId,
            'name' => $data['name'],
            'scheduled_date' => $data['scheduled_date'],
            'scheduled_time' => $data['scheduled_time'] ?? null,
            'routine_id' => $data['routine_id'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM group_workouts WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT gw.*, gwp.status AS my_status
             FROM group_workouts gw
             INNER JOIN group_workout_participants gwp ON gwp.group_workout_id = gw.id
             WHERE gwp.user_id = :user_id
             ORDER BY gw.scheduled_date DESC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function setStatus(int $id, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE group_workouts SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }
}
