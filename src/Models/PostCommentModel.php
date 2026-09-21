<?php

namespace App\Models;

use PDO;

class PostCommentModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function create(int $postId, int $userId, string $comment): array
    {
        $stmt = $this->db->prepare(
            'INSERT INTO post_comments (post_id, user_id, comment) VALUES (:post_id, :user_id, :comment)'
        );
        $stmt->execute(['post_id' => $postId, 'user_id' => $userId, 'comment' => $comment]);
        $id = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare('SELECT * FROM post_comments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM post_comments WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function listForPost(int $postId, int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, u.username, u.avatar_media_id
             FROM post_comments c
             INNER JOIN users u ON u.id = c.user_id
             WHERE c.post_id = :post_id AND c.deleted_at IS NULL
             ORDER BY c.id ASC
             LIMIT ' . (int) $limit
        );
        $stmt->execute(['post_id' => $postId]);
        return $stmt->fetchAll();
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE post_comments SET deleted_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** @return array<int, int> mapa post_id => cantidad de comentarios. */
    public function countsForPosts(array $postIds): array
    {
        if (empty($postIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT post_id, COUNT(*) AS c FROM post_comments WHERE post_id IN ($placeholders) AND deleted_at IS NULL GROUP BY post_id"
        );
        $stmt->execute($postIds);
        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(int) $row['post_id']] = (int) $row['c'];
        }
        return $counts;
    }
}
