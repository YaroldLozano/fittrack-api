<?php

namespace App\Models;

use PDO;

class PostMediaModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function attach(int $postId, int $mediaId, int $sortOrder): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO post_media (post_id, media_id, sort_order) VALUES (:post_id, :media_id, :sort_order)'
        );
        $stmt->execute(['post_id' => $postId, 'media_id' => $mediaId, 'sort_order' => $sortOrder]);
    }

    /** @return array<int, array<int, array>> mapa post_id => lista de filas de media, en orden. */
    public function forPosts(array $postIds): array
    {
        if (empty($postIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT pm.post_id, m.id, m.type, m.mime_type, m.width, m.height
             FROM post_media pm
             INNER JOIN media m ON m.id = pm.media_id
             WHERE pm.post_id IN ($placeholders)
             ORDER BY pm.post_id, pm.sort_order ASC"
        );
        $stmt->execute($postIds);

        $grouped = [];
        foreach ($stmt->fetchAll() as $row) {
            $grouped[(int) $row['post_id']][] = [
                'id' => (int) $row['id'],
                'type' => $row['type'],
                'mime_type' => $row['mime_type'],
                'width' => $row['width'] !== null ? (int) $row['width'] : null,
                'height' => $row['height'] !== null ? (int) $row['height'] : null,
            ];
        }
        return $grouped;
    }
}
