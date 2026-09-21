<?php

namespace App\Models;

use PDO;

class RoutineModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function findAllForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM workout_routines WHERE user_id = :user_id ORDER BY created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function findOwnedBy(int $userId, int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM workout_routines WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $userId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO workout_routines (user_id, name, description, goal, start_date, end_date, status)
             VALUES (:user_id, :name, :description, :goal, :start_date, :end_date, :status)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'goal' => $data['goal'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateOwned(int $userId, int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE workout_routines SET name = :name, description = :description, goal = :goal,
                start_date = :start_date, end_date = :end_date, status = :status
             WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'goal' => $data['goal'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'status' => $data['status'] ?? 'active',
            'id' => $id,
            'user_id' => $userId,
        ]);
    }

    public function updateStatusOwned(int $userId, int $id, string $status): void
    {
        $stmt = $this->db->prepare(
            'UPDATE workout_routines SET status = :status WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute(['status' => $status, 'id' => $id, 'user_id' => $userId]);
    }

    public function deleteOwned(int $userId, int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM workout_routines WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
    }
}
