<?php

namespace App\Services;

use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Core\PublicProfile;
use App\Models\BlockedUserModel;
use App\Models\FriendshipModel;
use App\Models\MediaModel;
use App\Models\PostCommentModel;
use App\Models\PostLikeModel;
use App\Models\PostMediaModel;
use App\Models\PostModel;
use App\Models\UserModel;
use App\Models\UserStatsModel;
use InvalidArgumentException;

class PostService
{
    private const MAX_CAPTION_LENGTH = 2000;
    private const MAX_COMMENT_LENGTH = 500;
    private const DEFAULT_PAGE_SIZE = 20;
    private const MAX_PAGE_SIZE = 50;
    private const MAX_MEDIA_PER_POST = 6;
    private const VALID_VISIBILITIES = ['public', 'friends', 'private'];
    private const VALID_TYPES = ['text', 'workout', 'pr'];

    public function __construct(
        private readonly PostModel $posts,
        private readonly PostMediaModel $postMedia,
        private readonly PostLikeModel $likes,
        private readonly PostCommentModel $comments,
        private readonly MediaModel $media,
        private readonly UserModel $users,
        private readonly UserStatsModel $stats,
        private readonly FriendshipModel $friendships,
        private readonly BlockedUserModel $blocked,
        private readonly ?NotificationService $notifications = null
    ) {
    }

    public function createPost(
        int $userId,
        ?string $caption,
        string $postType,
        ?array $context,
        string $visibility,
        array $mediaIds
    ): array {
        $caption = $caption !== null ? trim($caption) : null;
        if ($caption === '') {
            $caption = null;
        }
        if ($caption !== null && mb_strlen($caption) > self::MAX_CAPTION_LENGTH) {
            throw new InvalidArgumentException('El texto es demasiado largo');
        }
        if (!in_array($postType, self::VALID_TYPES, true)) {
            throw new InvalidArgumentException('Tipo de publicación inválido');
        }
        if (!in_array($visibility, self::VALID_VISIBILITIES, true)) {
            throw new InvalidArgumentException('Visibilidad inválida');
        }
        if (count($mediaIds) > self::MAX_MEDIA_PER_POST) {
            throw new InvalidArgumentException('Demasiados archivos para una publicación');
        }
        if ($caption === null && empty($mediaIds)) {
            throw new InvalidArgumentException('La publicación necesita texto o una foto/video');
        }

        // Cada media_id debe existir y pertenecer al autor — nunca adjuntar archivos ajenos.
        foreach ($mediaIds as $mediaId) {
            $row = $this->media->find((int) $mediaId);
            if ($row === null || (int) $row['user_id'] !== $userId) {
                throw new InvalidArgumentException('Archivo no encontrado');
            }
        }

        $contextJson = $context !== null ? json_encode($context) : null;
        $postId = $this->posts->create($userId, $caption, $postType, $contextJson, $visibility);

        foreach (array_values($mediaIds) as $index => $mediaId) {
            $this->postMedia->attach($postId, (int) $mediaId, $index);
        }

        return $this->getPost($userId, $postId);
    }

    public function getPost(int $viewerId, int $postId): array
    {
        $post = $this->posts->find($postId);
        if ($post === null) {
            throw new NotFoundException('Publicación no encontrada');
        }
        if (!$this->canView($viewerId, $post)) {
            throw new NotFoundException('Publicación no encontrada');
        }
        return $this->hydrate([$post], $viewerId)[0];
    }

    public function updatePost(int $userId, int $postId, array $fields): array
    {
        $post = $this->posts->findOwnedBy($userId, $postId);
        if ($post === null) {
            throw new NotFoundException('Publicación no encontrada');
        }

        $caption = array_key_exists('caption', $fields) ? trim((string) $fields['caption']) : $post['caption'];
        $visibility = array_key_exists('visibility', $fields) ? (string) $fields['visibility'] : $post['visibility'];

        if ($caption === '') {
            $caption = null;
        }
        if ($caption !== null && mb_strlen($caption) > self::MAX_CAPTION_LENGTH) {
            throw new InvalidArgumentException('El texto es demasiado largo');
        }
        if (!in_array($visibility, self::VALID_VISIBILITIES, true)) {
            throw new InvalidArgumentException('Visibilidad inválida');
        }

        $this->posts->update($postId, $caption, $visibility);
        return $this->getPost($userId, $postId);
    }

    public function deletePost(int $userId, int $postId): void
    {
        $post = $this->posts->findOwnedBy($userId, $postId);
        if ($post === null) {
            throw new NotFoundException('Publicación no encontrada');
        }
        $this->posts->softDelete($postId);
    }

    /** @return array{posts: array, has_more: bool} */
    public function feed(int $userId, ?int $beforeId, int $limit): array
    {
        $limit = $this->clampLimit($limit);
        $authorIds = [$userId, ...$this->friendships->idsForUser($userId)];

        $rows = $this->posts->feedForAuthors($authorIds, $beforeId, $limit);
        $visibleRows = array_values(array_filter(
            $rows,
            fn (array $p) => (int) $p['user_id'] === $userId || $p['visibility'] !== 'private'
        ));

        $hasMore = $rows !== [] && $this->posts->hasMoreForAuthors($authorIds, (int) end($rows)['id']);

        return ['posts' => $this->hydrate($visibleRows, $userId), 'has_more' => $hasMore];
    }

    /** @return array{posts: array, has_more: bool} */
    public function profilePosts(int $viewerId, int $profileUserId, ?int $beforeId, int $limit): array
    {
        $limit = $this->clampLimit($limit);
        $allowed = $this->allowedVisibilitiesFor($viewerId, $profileUserId);
        if (empty($allowed)) {
            return ['posts' => [], 'has_more' => false];
        }

        $rows = $this->posts->forProfile($profileUserId, $allowed, $beforeId, $limit);
        $hasMore = $rows !== [] && $this->posts->hasMoreForProfile($profileUserId, $allowed, (int) end($rows)['id']);

        return ['posts' => $this->hydrate($rows, $viewerId), 'has_more' => $hasMore];
    }

    public function like(int $viewerId, int $postId): void
    {
        $post = $this->requireViewable($viewerId, $postId);
        $this->likes->like($postId, $viewerId);
        $this->notifications?->notify((int) $post['user_id'], 'post_like', $viewerId, 'post', $postId);
    }

    public function unlike(int $viewerId, int $postId): void
    {
        $this->requireViewable($viewerId, $postId);
        $this->likes->unlike($postId, $viewerId);
    }

    public function listComments(int $viewerId, int $postId): array
    {
        $this->requireViewable($viewerId, $postId);
        return array_map(fn (array $row) => $this->commentDto($row), $this->comments->listForPost($postId));
    }

    public function addComment(int $viewerId, int $postId, string $text): array
    {
        $post = $this->requireViewable($viewerId, $postId);

        $text = trim($text);
        if ($text === '') {
            throw new InvalidArgumentException('El comentario no puede estar vacío');
        }
        if (mb_strlen($text) > self::MAX_COMMENT_LENGTH) {
            throw new InvalidArgumentException('El comentario es demasiado largo');
        }

        $row = $this->comments->create($postId, $viewerId, $text);
        $this->notifications?->notify((int) $post['user_id'], 'post_comment', $viewerId, 'post', $postId);

        $author = $this->users->findById($viewerId);
        return $this->commentDto([
            ...$row,
            'username' => $author['username'] ?? '',
            'avatar_media_id' => $author['avatar_media_id'] ?? null,
        ]);
    }

    public function deleteComment(int $userId, int $commentId): void
    {
        $comment = $this->comments->find($commentId);
        if ($comment === null || (int) $comment['user_id'] !== $userId) {
            throw new NotFoundException('Comentario no encontrado');
        }
        $this->comments->softDelete($commentId);
    }

    private function requireViewable(int $viewerId, int $postId): array
    {
        $post = $this->posts->find($postId);
        if ($post === null || !$this->canView($viewerId, $post)) {
            throw new NotFoundException('Publicación no encontrada');
        }
        return $post;
    }

    private function canView(int $viewerId, array $post): bool
    {
        $ownerId = (int) $post['user_id'];
        if ($viewerId === $ownerId) {
            return true;
        }
        if ($this->blocked->isBlockedEitherWay($viewerId, $ownerId)) {
            return false;
        }
        return match ($post['visibility']) {
            'public' => true,
            'friends' => $this->friendships->areFriends($viewerId, $ownerId),
            default => false,
        };
    }

    /** @return array<int, string> visibilidades que el visitante puede ver de este perfil. */
    private function allowedVisibilitiesFor(int $viewerId, int $profileUserId): array
    {
        if ($viewerId === $profileUserId) {
            return self::VALID_VISIBILITIES;
        }
        if ($this->blocked->isBlockedEitherWay($viewerId, $profileUserId)) {
            return [];
        }
        if ($this->friendships->areFriends($viewerId, $profileUserId)) {
            return ['public', 'friends'];
        }
        return ['public'];
    }

    private function hydrate(array $postRows, int $viewerId): array
    {
        if (empty($postRows)) {
            return [];
        }

        $postIds = array_map(fn ($p) => (int) $p['id'], $postRows);
        $mediaByPost = $this->postMedia->forPosts($postIds);
        $likeCounts = $this->likes->countsForPosts($postIds);
        $commentCounts = $this->comments->countsForPosts($postIds);
        $likedByMe = $this->likes->likedByUserForPosts($viewerId, $postIds);

        $authorCache = [];

        return array_map(function (array $p) use ($mediaByPost, $likeCounts, $commentCounts, $likedByMe, &$authorCache) {
            $authorId = (int) $p['user_id'];
            if (!isset($authorCache[$authorId])) {
                $user = $this->users->findById($authorId);
                $authorCache[$authorId] = $user !== null
                    ? PublicProfile::summary($user, $this->stats->findByUser($authorId))
                    : null;
            }

            $postId = (int) $p['id'];

            return [
                'id' => $postId,
                'author' => $authorCache[$authorId],
                'caption' => $p['caption'],
                'post_type' => $p['post_type'],
                'context' => $p['context_json'] !== null ? json_decode($p['context_json'], true) : null,
                'visibility' => $p['visibility'],
                'media' => $mediaByPost[$postId] ?? [],
                'like_count' => $likeCounts[$postId] ?? 0,
                'comment_count' => $commentCounts[$postId] ?? 0,
                'liked_by_me' => isset($likedByMe[$postId]),
                'created_at' => $p['created_at'],
                'updated_at' => $p['updated_at'],
            ];
        }, $postRows);
    }

    private function commentDto(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'post_id' => (int) $row['post_id'],
            'user_id' => (int) $row['user_id'],
            'username' => $row['username'],
            'avatar_media_id' => $row['avatar_media_id'] !== null ? (int) $row['avatar_media_id'] : null,
            'comment' => $row['comment'],
            'created_at' => $row['created_at'],
        ];
    }

    private function clampLimit(int $limit): int
    {
        return max(1, min($limit ?: self::DEFAULT_PAGE_SIZE, self::MAX_PAGE_SIZE));
    }
}
