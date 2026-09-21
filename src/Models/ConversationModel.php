<?php

namespace App\Models;

use PDO;

class ConversationModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function findBetween(int $userA, int $userB): ?array
    {
        [$a, $b] = $userA < $userB ? [$userA, $userB] : [$userB, $userA];

        $stmt = $this->db->prepare('SELECT * FROM conversations WHERE user_a_id = :a AND user_b_id = :b');
        $stmt->execute(['a' => $a, 'b' => $b]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function create(int $userA, int $userB): array
    {
        [$a, $b] = $userA < $userB ? [$userA, $userB] : [$userB, $userA];

        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO conversations (user_a_id, user_b_id) VALUES (:a, :b)'
        );
        $stmt->execute(['a' => $a, 'b' => $b]);

        return $this->findBetween($a, $b);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM conversations WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** El usuario autenticado debe ser user_a_id o user_b_id; null si no pertenece a la conversación. */
    public function findOwnedBy(int $userId, int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM conversations WHERE id = :id AND (user_a_id = :u1 OR user_b_id = :u2)'
        );
        $stmt->execute(['id' => $id, 'u1' => $userId, 'u2' => $userId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function otherUserId(array $conversation, int $userId): int
    {
        return (int) $conversation['user_a_id'] === $userId
            ? (int) $conversation['user_b_id']
            : (int) $conversation['user_a_id'];
    }

    public function touch(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE conversations SET updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * Lista las conversaciones del usuario con el perfil público del amigo, el último
     * mensaje (no borrado) y el conteo de no leídos, ordenadas por actividad reciente.
     */
    public function listForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                c.id,
                c.user_a_id,
                c.user_b_id,
                c.updated_at,
                friend.id AS friend_id,
                friend.username AS friend_username,
                friend.name AS friend_name,
                friend.avatar_media_id AS friend_avatar_media_id,
                s.level AS friend_level,
                lm.id AS last_message_id,
                lm.message AS last_message,
                lm.sender_id AS last_message_sender_id,
                lm.created_at AS last_message_created_at,
                (
                    SELECT COUNT(*) FROM messages m
                    WHERE m.conversation_id = c.id
                      AND m.sender_id != :user_id
                      AND m.read_at IS NULL
                      AND m.deleted_at IS NULL
                ) AS unread_count
             FROM conversations c
             INNER JOIN users friend ON friend.id = IF(c.user_a_id = :user_id2, c.user_b_id, c.user_a_id)
             LEFT JOIN user_stats s ON s.user_id = friend.id
             LEFT JOIN messages lm ON lm.id = (
                SELECT m2.id FROM messages m2
                WHERE m2.conversation_id = c.id AND m2.deleted_at IS NULL
                ORDER BY m2.id DESC LIMIT 1
             )
             WHERE c.user_a_id = :user_id3 OR c.user_b_id = :user_id4
             ORDER BY c.updated_at DESC'
        );
        $stmt->execute([
            'user_id' => $userId,
            'user_id2' => $userId,
            'user_id3' => $userId,
            'user_id4' => $userId,
        ]);
        return $stmt->fetchAll();
    }
}
