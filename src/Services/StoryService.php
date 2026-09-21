<?php

namespace App\Services;

use App\Core\NotFoundException;
use App\Core\PublicProfile;
use App\Models\BlockedUserModel;
use App\Models\FriendshipModel;
use App\Models\MediaModel;
use App\Models\StoryModel;
use App\Models\StoryViewModel;
use App\Models\UserModel;
use App\Models\UserStatsModel;
use InvalidArgumentException;

class StoryService
{
    private const MAX_CAPTION_LENGTH = 200;
    private const VALID_VISIBILITIES = ['public', 'friends'];

    public function __construct(
        private readonly StoryModel $stories,
        private readonly StoryViewModel $views,
        private readonly MediaModel $media,
        private readonly UserModel $users,
        private readonly UserStatsModel $stats,
        private readonly FriendshipModel $friendships,
        private readonly BlockedUserModel $blocked
    ) {
    }

    public function createStory(int $userId, int $mediaId, ?string $caption, string $visibility): array
    {
        $media = $this->media->find($mediaId);
        if ($media === null || (int) $media['user_id'] !== $userId) {
            throw new InvalidArgumentException('Archivo no encontrado');
        }

        $caption = $caption !== null ? trim($caption) : null;
        if ($caption === '') {
            $caption = null;
        }
        if ($caption !== null && mb_strlen($caption) > self::MAX_CAPTION_LENGTH) {
            throw new InvalidArgumentException('El texto es demasiado largo');
        }
        if (!in_array($visibility, self::VALID_VISIBILITIES, true)) {
            throw new InvalidArgumentException('Visibilidad inválida');
        }

        $id = $this->stories->create($userId, $mediaId, $caption, $visibility);
        return $this->getStory($userId, $id);
    }

    /** Historias activas de amigos + propias, agrupadas por autor (más reciente primero por autor). */
    public function listActive(int $viewerId): array
    {
        $authorIds = [$viewerId, ...$this->friendships->idsForUser($viewerId)];
        $rows = $this->stories->activeForAuthors($authorIds);
        if (empty($rows)) {
            return [];
        }

        $storyIds = array_map(fn ($r) => (int) $r['id'], $rows);
        $viewedByMe = $this->views->viewedByUserForStories($viewerId, $storyIds);

        $grouped = [];
        foreach ($rows as $row) {
            $authorId = (int) $row['user_id'];
            if (!isset($grouped[$authorId])) {
                $user = $this->users->findById($authorId);
                $grouped[$authorId] = [
                    'author' => PublicProfile::summary($user, $this->stats->findByUser($authorId)),
                    'stories' => [],
                    'has_unseen' => false,
                ];
            }

            $storyId = (int) $row['id'];
            $seen = isset($viewedByMe[$storyId]);
            $grouped[$authorId]['stories'][] = $this->storyDto($row, $seen);
            if (!$seen) {
                $grouped[$authorId]['has_unseen'] = true;
            }
        }

        // Propia historia primero, luego amigos con no vistas primero.
        $result = array_values($grouped);
        usort($result, function ($a, $b) use ($viewerId) {
            if ((int) $a['author']['id'] === $viewerId) {
                return -1;
            }
            if ((int) $b['author']['id'] === $viewerId) {
                return 1;
            }
            return $b['has_unseen'] <=> $a['has_unseen'];
        });

        return $result;
    }

    public function getStory(int $viewerId, int $storyId): array
    {
        $story = $this->stories->find($storyId);
        if ($story === null || !$this->canView($viewerId, $story)) {
            throw new NotFoundException('Historia no encontrada');
        }
        return $this->storyDto($story, false);
    }

    public function viewStory(int $viewerId, int $storyId): void
    {
        $story = $this->stories->find($storyId);
        if ($story === null || !$this->canView($viewerId, $story)) {
            throw new NotFoundException('Historia no encontrada');
        }
        if ((int) $story['user_id'] !== $viewerId) {
            $this->views->recordView($storyId, $viewerId);
        }
    }

    /** Solo el dueño de la historia puede ver quién la vio. */
    public function listViewers(int $userId, int $storyId): array
    {
        $story = $this->stories->findOwnedBy($userId, $storyId);
        if ($story === null) {
            throw new NotFoundException('Historia no encontrada');
        }
        return array_map(fn (array $row) => [
            'id' => (int) $row['id'],
            'username' => $row['username'],
            'avatar_media_id' => $row['avatar_media_id'] !== null ? (int) $row['avatar_media_id'] : null,
            'viewed_at' => $row['viewed_at'],
        ], $this->views->listViewers($storyId));
    }

    public function deleteStory(int $userId, int $storyId): void
    {
        $story = $this->stories->findOwnedBy($userId, $storyId);
        if ($story === null) {
            throw new NotFoundException('Historia no encontrada');
        }
        $this->stories->delete($storyId);
    }

    private function canView(int $viewerId, array $story): bool
    {
        $ownerId = (int) $story['user_id'];
        if ($story['expires_at'] !== null && strtotime((string) $story['expires_at']) < time() && $ownerId !== $viewerId) {
            return false;
        }
        if ($viewerId === $ownerId) {
            return true;
        }
        if ($this->blocked->isBlockedEitherWay($viewerId, $ownerId)) {
            return false;
        }
        return match ($story['visibility']) {
            'public' => true,
            'friends' => $this->friendships->areFriends($viewerId, $ownerId),
            default => false,
        };
    }

    private function storyDto(array $row, bool $viewedByMe): array
    {
        return [
            'id' => (int) $row['id'],
            'media_id' => (int) $row['media_id'],
            'media_type' => $row['media_type'] ?? null,
            'caption' => $row['caption'],
            'visibility' => $row['visibility'],
            'created_at' => $row['created_at'],
            'expires_at' => $row['expires_at'],
            'viewed_by_me' => $viewedByMe,
        ];
    }
}
