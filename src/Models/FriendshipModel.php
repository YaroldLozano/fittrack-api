<?php

namespace App\Models;

use PDO;

class FriendshipModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function areFriends(int $userA, int $userB): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM friendships WHERE user_id = :a AND friend_id = :b');
        $stmt->execute(['a' => $userA, 'b' => $userB]);
        return (bool) $stmt->fetchColumn();
    }

    /** Crea la relación simétrica (2 filas) al aceptar una solicitud. */
    public function create(int $userA, int $userB): void
    {
        // Nota: bajo ATTR_EMULATE_PREPARES=false, mysqlnd no soporta reutilizar el mismo
        // parámetro nombrado más de una vez en la consulta — cada ocurrencia necesita su propio nombre.
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO friendships (user_id, friend_id) VALUES (:a1, :b1), (:b2, :a2)'
        );
        $stmt->execute(['a1' => $userA, 'b1' => $userB, 'b2' => $userB, 'a2' => $userA]);
    }

    public function remove(int $userA, int $userB): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM friendships WHERE (user_id = :a1 AND friend_id = :b1) OR (user_id = :b2 AND friend_id = :a2)'
        );
        $stmt->execute(['a1' => $userA, 'b1' => $userB, 'b2' => $userB, 'a2' => $userA]);
    }

    public function listForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.username, u.name, u.avatar_media_id, f.created_at AS friends_since,
                    s.level, s.xp_total, s.current_streak_days
             FROM friendships f
             INNER JOIN users u ON u.id = f.friend_id
             LEFT JOIN user_stats s ON s.user_id = u.id
             WHERE f.user_id = :user_id
             ORDER BY s.xp_total DESC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function idsForUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT friend_id FROM friendships WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function countForUser(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM friendships WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }
}
