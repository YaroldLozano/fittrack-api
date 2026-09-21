<?php

namespace App\Models;

use PDO;

class NotificationModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function create(int $userId, string $type, int $actorId, string $subjectType, int $subjectId): void
    {
        // El actor nunca se notifica a sí mismo (ej. dar like a tu propia publicación).
        if ($userId === $actorId) {
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO notifications (user_id, type, actor_id, subject_type, subject_id)
             VALUES (:user_id, :type, :actor_id, :subject_type, :subject_id)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'type' => $type,
            'actor_id' => $actorId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
        ]);
    }

    public function listForUser(int $userId, int $limit = 30): array
    {
        $stmt = $this->db->prepare(
            'SELECT n.*, a.username AS actor_username, a.avatar_media_id AS actor_avatar_media_id
             FROM notifications n
             INNER JOIN users a ON a.id = n.actor_id
             WHERE n.user_id = :user_id
             ORDER BY n.id DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function countUnread(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0');
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function markRead(int $userId, int $id): void
    {
        $stmt = $this->db->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
    }

    public function markAllRead(int $userId): void
    {
        $stmt = $this->db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0');
        $stmt->execute(['user_id' => $userId]);
    }
}
