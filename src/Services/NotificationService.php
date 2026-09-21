<?php

namespace App\Services;

use App\Models\NotificationModel;

class NotificationService
{
    public function __construct(private readonly NotificationModel $notifications)
    {
    }

    public function notify(int $userId, string $type, int $actorId, string $subjectType, int $subjectId): void
    {
        $this->notifications->create($userId, $type, $actorId, $subjectType, $subjectId);
    }

    public function list(int $userId): array
    {
        return array_map(function (array $row) {
            return [
                'id' => (int) $row['id'],
                'type' => $row['type'],
                'actor' => [
                    'id' => (int) $row['actor_id'],
                    'username' => $row['actor_username'],
                    'avatar_media_id' => $row['actor_avatar_media_id'] !== null ? (int) $row['actor_avatar_media_id'] : null,
                ],
                'subject_type' => $row['subject_type'],
                'subject_id' => (int) $row['subject_id'],
                'is_read' => (bool) $row['is_read'],
                'created_at' => $row['created_at'],
            ];
        }, $this->notifications->listForUser($userId));
    }

    public function unreadCount(int $userId): int
    {
        return $this->notifications->countUnread($userId);
    }

    public function markRead(int $userId, int $id): void
    {
        $this->notifications->markRead($userId, $id);
    }

    public function markAllRead(int $userId): void
    {
        $this->notifications->markAllRead($userId);
    }
}
