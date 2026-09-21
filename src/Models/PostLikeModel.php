<?php

namespace App\Models;

use PDO;

class PostLikeModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return bool true si el like es nuevo (no existía antes). */
    public function like(int $postId, int $userId): bool
    {
        $stmt = $this->db->prepare('INSERT IGNORE INTO post_likes (post_id, user_id) VALUES (:post_id, :user_id)');
        $stmt->execute(['post_id' => $postId, 'user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public function unlike(int $postId, int $userId): void
    {
        $stmt = $this->db->prepare('DELETE FROM post_likes WHERE post_id = :post_id AND user_id = :user_id');
        $stmt->execute(['post_id' => $postId, 'user_id' => $userId]);
    }

    /** @return array<int, int> mapa post_id => cantidad de likes. */
    public function countsForPosts(array $postIds): array
    {
        if (empty($postIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT post_id, COUNT(*) AS c FROM post_likes WHERE post_id IN ($placeholders) GROUP BY post_id"
        );
        $stmt->execute($postIds);
        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(int) $row['post_id']] = (int) $row['c'];
        }
        return $counts;
    }

    /** @return array<int, bool> mapa post_id => si $userId le dio like. */
    public function likedByUserForPosts(int $userId, array $postIds): array
    {
        if (empty($postIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT post_id FROM post_likes WHERE user_id = ? AND post_id IN ($placeholders)"
        );
        $stmt->execute([$userId, ...$postIds]);
        $liked = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $postId) {
            $liked[(int) $postId] = true;
        }
        return $liked;
    }
}
