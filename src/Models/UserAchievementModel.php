<?php

namespace App\Models;

use PDO;
use PDOException;

class UserAchievementModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function unlockedIdsForUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT achievement_id FROM user_achievements WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function findForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT ua.*, a.code, a.category, a.name, a.description, a.icon, a.rarity, a.requirement_type, a.requirement_value
             FROM user_achievements ua
             INNER JOIN achievements a ON a.id = ua.achievement_id
             WHERE ua.user_id = :user_id
             ORDER BY ua.unlocked_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    /** Devuelve true si se desbloqueó ahora, false si ya estaba desbloqueado. */
    public function tryUnlock(int $userId, int $achievementId): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO user_achievements (user_id, achievement_id) VALUES (:user_id, :achievement_id)'
        );
        try {
            $stmt->execute(['user_id' => $userId, 'achievement_id' => $achievementId]);
            return true;
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }
    }

    public function countForUser(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM user_achievements WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }
}
