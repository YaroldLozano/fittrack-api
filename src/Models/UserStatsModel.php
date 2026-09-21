<?php

namespace App\Models;

use PDO;

class UserStatsModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function findByUser(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM user_stats WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Garantiza que exista una fila de stats para el usuario (idempotente). */
    public function ensureExists(int $userId): array
    {
        $existing = $this->findByUser($userId);
        if ($existing !== null) {
            return $existing;
        }

        $stmt = $this->db->prepare('INSERT IGNORE INTO user_stats (user_id) VALUES (:user_id)');
        $stmt->execute(['user_id' => $userId]);

        return $this->findByUser($userId) ?? [
            'user_id' => $userId, 'xp_total' => 0, 'level' => 1,
            'current_streak_days' => 0, 'longest_streak_days' => 0,
            'last_activity_date' => null, 'ranking_score' => 0.0,
        ];
    }

    public function addXp(int $userId, int $amount, int $newLevel): void
    {
        $stmt = $this->db->prepare(
            'UPDATE user_stats SET xp_total = xp_total + :amount, level = :level WHERE user_id = :user_id'
        );
        $stmt->execute(['amount' => $amount, 'level' => $newLevel, 'user_id' => $userId]);
    }

    public function updateStreak(int $userId, int $currentStreak, int $longestStreak, string $lastActivityDate): void
    {
        $stmt = $this->db->prepare(
            'UPDATE user_stats SET current_streak_days = :current, longest_streak_days = :longest, last_activity_date = :date
             WHERE user_id = :user_id'
        );
        $stmt->execute([
            'current' => $currentStreak,
            'longest' => $longestStreak,
            'date' => $lastActivityDate,
            'user_id' => $userId,
        ]);
    }
}
