<?php

namespace App\Models;

use PDO;

class StoryModel
{
    private const LIFETIME_HOURS = 24;

    public function __construct(private readonly PDO $db)
    {
    }

    public function create(int $userId, int $mediaId, ?string $caption, string $visibility): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO stories (user_id, media_id, caption, visibility, expires_at)
             VALUES (:user_id, :media_id, :caption, :visibility, NOW() + INTERVAL ' . self::LIFETIME_HOURS . ' HOUR)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'media_id' => $mediaId,
            'caption' => $caption,
            'visibility' => $visibility,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*, m.type AS media_type, m.mime_type
             FROM stories s
             INNER JOIN media m ON m.id = s.media_id
             WHERE s.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findOwnedBy(int $userId, int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM stories WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM stories WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** Historias activas de un conjunto de autores (propias + amigos), agrupadas por autor. */
    public function activeForAuthors(array $authorIds): array
    {
        if (empty($authorIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($authorIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT s.*, m.type AS media_type, m.mime_type
             FROM stories s
             INNER JOIN media m ON m.id = s.media_id
             WHERE s.user_id IN ($placeholders) AND s.expires_at > NOW()
             ORDER BY s.user_id, s.created_at ASC"
        );
        $stmt->execute($authorIds);
        return $stmt->fetchAll();
    }
}
