<?php

namespace App\Services;

use App\Core\ForbiddenException;
use App\Core\MediaStorage;
use App\Core\NotFoundException;
use App\Models\BlockedUserModel;
use App\Models\FriendshipModel;
use App\Models\MediaModel;
use PDO;
use InvalidArgumentException;

class MediaService
{
    public function __construct(
        private readonly PDO $db,
        private readonly MediaModel $media,
        private readonly MediaStorage $storage,
        private readonly FriendshipModel $friendships,
        private readonly BlockedUserModel $blocked
    ) {
    }

    public function upload(int $userId, array $file): array
    {
        $stored = $this->storage->store($file);
        $id = $this->media->create($userId, $stored);
        return ['id' => $id, ...$stored];
    }

    /**
     * Resuelve si el usuario autenticado puede ver este archivo (es su avatar, o
     * pertenece a un post/story visible para él según amistad/bloqueo/visibilidad),
     * y devuelve la ruta absoluta lista para stream. Nunca se sirve un archivo sin
     * pasar por aquí — ver decisión 3 del plan.
     */
    public function resolveForViewing(int $viewerId, int $mediaId, bool $wantThumbnail): array
    {
        $row = $this->media->find($mediaId);
        if ($row === null) {
            throw new NotFoundException('Archivo no encontrado');
        }
        $ownerId = (int) $row['user_id'];

        // El dueño siempre puede ver su propio archivo (recién subido y aún no
        // publicado, o ya adjunto a un post/story/avatar propio).
        $isOwner = $ownerId === $viewerId;

        if (!$isOwner) {
            if ($this->blocked->isBlockedEitherWay($viewerId, $ownerId)) {
                throw new ForbiddenException('No autorizado');
            }

            $authorized = $this->isAvatarOf($mediaId)
                || $this->authorizedViaPost($viewerId, $ownerId, $mediaId)
                || $this->authorizedViaStory($viewerId, $ownerId, $mediaId);

            if (!$authorized) {
                throw new ForbiddenException('No autorizado');
            }
        }

        $relativePath = $wantThumbnail && $row['thumbnail_path'] !== null ? $row['thumbnail_path'] : $row['path'];

        return [
            'absolute_path' => $this->storage->absolutePath($relativePath),
            'mime_type' => $wantThumbnail && $row['thumbnail_path'] !== null ? 'image/jpeg' : $row['mime_type'],
        ];
    }

    public function deleteById(int $userId, int $mediaId): void
    {
        $row = $this->media->find($mediaId);
        if ($row === null || (int) $row['user_id'] !== $userId) {
            throw new InvalidArgumentException('Archivo no encontrado');
        }
        $this->storage->delete($row['path']);
        $this->storage->delete($row['thumbnail_path']);
        $this->media->delete($mediaId);
    }

    private function isAvatarOf(int $mediaId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM users WHERE avatar_media_id = :id');
        $stmt->execute(['id' => $mediaId]);
        return (bool) $stmt->fetchColumn();
    }

    private function authorizedViaPost(int $viewerId, int $ownerId, int $mediaId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT p.visibility FROM post_media pm
             INNER JOIN posts p ON p.id = pm.post_id
             WHERE pm.media_id = :media_id AND p.deleted_at IS NULL'
        );
        $stmt->execute(['media_id' => $mediaId]);
        $visibility = $stmt->fetchColumn();
        if ($visibility === false) {
            return false;
        }
        return $this->visibilityAllows((string) $visibility, $viewerId, $ownerId);
    }

    private function authorizedViaStory(int $viewerId, int $ownerId, int $mediaId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT visibility FROM stories WHERE media_id = :media_id AND expires_at > NOW()'
        );
        $stmt->execute(['media_id' => $mediaId]);
        $visibility = $stmt->fetchColumn();
        if ($visibility === false) {
            return false;
        }
        return $this->visibilityAllows((string) $visibility, $viewerId, $ownerId);
    }

    private function visibilityAllows(string $visibility, int $viewerId, int $ownerId): bool
    {
        if ($viewerId === $ownerId) {
            return true;
        }
        return match ($visibility) {
            'public' => true,
            'friends' => $this->friendships->areFriends($viewerId, $ownerId),
            default => false, // 'private'
        };
    }
}
