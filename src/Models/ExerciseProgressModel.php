<?php

namespace App\Models;

use PDO;

class ExerciseProgressModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function find(int $userId, int $exerciseId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM exercise_progress WHERE user_id = :user_id AND exercise_id = :exercise_id');
        $stmt->execute(['user_id' => $userId, 'exercise_id' => $exerciseId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function ensureExists(int $userId, int $exerciseId): array
    {
        $existing = $this->find($userId, $exerciseId);
        if ($existing !== null) {
            return $existing;
        }

        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO exercise_progress (user_id, exercise_id) VALUES (:user_id, :exercise_id)'
        );
        $stmt->execute(['user_id' => $userId, 'exercise_id' => $exerciseId]);

        return $this->find($userId, $exerciseId) ?? ['user_id' => $userId, 'exercise_id' => $exerciseId, 'xp' => 0, 'level' => 1];
    }

    public function addXp(int $userId, int $exerciseId, int $amount, int $newLevel): void
    {
        $stmt = $this->db->prepare(
            'UPDATE exercise_progress SET xp = xp + :amount, level = :level WHERE user_id = :user_id AND exercise_id = :exercise_id'
        );
        $stmt->execute(['amount' => $amount, 'level' => $newLevel, 'user_id' => $userId, 'exercise_id' => $exerciseId]);
    }

    public function maxLevelForUser(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT MAX(level) FROM exercise_progress WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        $value = $stmt->fetchColumn();
        return $value !== null ? (int) $value : 0;
    }

    /** Mejores ejercicios del usuario por nivel (para el perfil / dashboard de ranking). */
    public function topForUser(int $userId, int $limit = 5): array
    {
        $stmt = $this->db->prepare(
            'SELECT ep.*, e.name AS exercise_name FROM exercise_progress ep
             INNER JOIN exercises e ON e.id = ep.exercise_id
             WHERE ep.user_id = :user_id
             ORDER BY ep.level DESC, ep.xp DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }
}
