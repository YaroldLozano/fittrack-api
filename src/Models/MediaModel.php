<?php

namespace App\Models;

use PDO;

class MediaModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function create(int $userId, array $stored): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO media (user_id, type, mime_type, path, thumbnail_path, width, height, size_bytes)
             VALUES (:user_id, :type, :mime_type, :path, :thumbnail_path, :width, :height, :size_bytes)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'type' => $stored['type'],
            'mime_type' => $stored['mime_type'],
            'path' => $stored['path'],
            'thumbnail_path' => $stored['thumbnail_path'],
            'width' => $stored['width'],
            'height' => $stored['height'],
            'size_bytes' => $stored['size_bytes'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM media WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM media WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
