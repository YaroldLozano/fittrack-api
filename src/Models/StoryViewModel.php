<?php

namespace App\Models;

use PDO;

class StoryViewModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function recordView(int $storyId, int $viewerId): void
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO story_views (story_id, viewer_id) VALUES (:story_id, :viewer_id)'
        );
        $stmt->execute(['story_id' => $storyId, 'viewer_id' => $viewerId]);
    }

    /** @return array<int, bool> mapa story_id => si $viewerId ya la vio. */
    public function viewedByUserForStories(int $viewerId, array $storyIds): array
    {
        if (empty($storyIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($storyIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT story_id FROM story_views WHERE viewer_id = ? AND story_id IN ($placeholders)"
        );
        $stmt->execute([$viewerId, ...$storyIds]);
        $viewed = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $storyId) {
            $viewed[(int) $storyId] = true;
        }
        return $viewed;
    }

    public function countForStory(int $storyId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM story_views WHERE story_id = :story_id');
        $stmt->execute(['story_id' => $storyId]);
        return (int) $stmt->fetchColumn();
    }

    public function listViewers(int $storyId): array
    {
        $stmt = $this->db->prepare(
            'SELECT sv.viewed_at, u.id, u.username, u.avatar_media_id
             FROM story_views sv
             INNER JOIN users u ON u.id = sv.viewer_id
             WHERE sv.story_id = :story_id
             ORDER BY sv.viewed_at DESC'
        );
        $stmt->execute(['story_id' => $storyId]);
        return $stmt->fetchAll();
    }
}
