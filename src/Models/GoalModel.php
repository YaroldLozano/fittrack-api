<?php

namespace App\Models;

use PDO;

class GoalModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function findAllForUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM goals WHERE user_id = :user_id ORDER BY created_at DESC');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function findOwnedBy(int $userId, int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM goals WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $userId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO goals (user_id, title, type, exercise_id, target_value, unit, start_date, target_date)
             VALUES (:user_id, :title, :type, :exercise_id, :target_value, :unit, :start_date, :target_date)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'title' => $data['title'],
            'type' => $data['type'],
            'exercise_id' => $data['exercise_id'] ?? null,
            'target_value' => $data['target_value'] ?? null,
            'unit' => $data['unit'] ?? null,
            'start_date' => $data['start_date'] ?? date('Y-m-d'),
            'target_date' => $data['target_date'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateOwned(int $userId, int $id, array $data): void
    {
        $sets = [];
        $params = ['id' => $id, 'user_id' => $userId];

        foreach (['title', 'target_value', 'current_value', 'unit', 'target_date', 'status', 'completed_at'] as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($sets)) {
            return;
        }

        $sql = 'UPDATE goals SET ' . implode(', ', $sets) . ' WHERE id = :id AND user_id = :user_id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    public function updateProgress(int $goalId, float $currentValue, bool $completed): void
    {
        $stmt = $this->db->prepare(
            'UPDATE goals SET current_value = :current_value,
                status = :status,
                completed_at = CASE WHEN :completed = 1 AND status != "completed" THEN NOW() ELSE completed_at END
             WHERE id = :id'
        );
        $stmt->execute([
            'current_value' => $currentValue,
            'status' => $completed ? 'completed' : 'in_progress',
            'completed' => $completed ? 1 : 0,
            'id' => $goalId,
        ]);
    }

    /** All in-progress goals of a given type for a user (used to auto-update progress after a relevant event). */
    public function findInProgressByType(int $userId, string $type, ?int $exerciseId = null): array
    {
        $sql = 'SELECT * FROM goals WHERE user_id = :user_id AND type = :type AND status = "in_progress"';
        $params = ['user_id' => $userId, 'type' => $type];

        if ($exerciseId !== null) {
            $sql .= ' AND exercise_id = :exercise_id';
            $params['exercise_id'] = $exerciseId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
