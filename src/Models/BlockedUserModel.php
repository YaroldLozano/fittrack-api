<?php

namespace App\Models;

use PDO;

class BlockedUserModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function block(int $userId, int $blockedUserId): void
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO blocked_users (user_id, blocked_user_id) VALUES (:user_id, :blocked_user_id)'
        );
        $stmt->execute(['user_id' => $userId, 'blocked_user_id' => $blockedUserId]);
    }

    /** true si cualquiera de los dos bloqueó al otro. */
    public function isBlockedEitherWay(int $userA, int $userB): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM blocked_users
             WHERE (user_id = :a1 AND blocked_user_id = :b1) OR (user_id = :b2 AND blocked_user_id = :a2)'
        );
        $stmt->execute(['a1' => $userA, 'b1' => $userB, 'b2' => $userB, 'a2' => $userA]);
        return (bool) $stmt->fetchColumn();
    }
}
