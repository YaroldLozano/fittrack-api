<?php

namespace App\Models;

use PDO;

class WorkoutSessionModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function findAllForUser(int $userId, ?string $from = null, ?string $to = null): array
    {
        $sql = 'SELECT ws.*,
                    (SELECT COUNT(*) FROM workout_session_exercises wse WHERE wse.session_id = ws.id) AS exercise_count,
                    (SELECT COUNT(*) FROM workout_sets s
                        JOIN workout_session_exercises wse2 ON wse2.id = s.session_exercise_id
                        WHERE wse2.session_id = ws.id) AS set_count
                FROM workout_sessions ws
                WHERE ws.user_id = :user_id';
        $params = ['user_id' => $userId];

        if ($from !== null) {
            $sql .= ' AND ws.scheduled_date >= :from';
            $params['from'] = $from;
        }
        if ($to !== null) {
            $sql .= ' AND ws.scheduled_date <= :to';
            $params['to'] = $to;
        }

        $sql .= ' ORDER BY ws.scheduled_date DESC, ws.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findOwnedBy(int $userId, int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM workout_sessions WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $userId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO workout_sessions (user_id, routine_id, routine_day_id, name, scheduled_date, status, notes)
             VALUES (:user_id, :routine_id, :routine_day_id, :name, :scheduled_date, :status, :notes)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'routine_id' => $data['routine_id'] ?? null,
            'routine_day_id' => $data['routine_day_id'] ?? null,
            'name' => $data['name'],
            'scheduled_date' => $data['scheduled_date'],
            'status' => $data['status'] ?? 'scheduled',
            'notes' => $data['notes'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateStatusOwned(int $userId, int $id, array $fields): void
    {
        $sets = [];
        $params = ['id' => $id, 'user_id' => $userId];

        foreach (['status', 'started_at', 'completed_at', 'duration_seconds', 'notes'] as $field) {
            if (array_key_exists($field, $fields)) {
                $sets[] = "$field = :$field";
                $params[$field] = $fields[$field];
            }
        }

        if (empty($sets)) {
            return;
        }

        $sql = 'UPDATE workout_sessions SET ' . implode(', ', $sets) . ' WHERE id = :id AND user_id = :user_id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    public function deleteOwned(int $userId, int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM workout_sessions WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
    }
}
