<?php

namespace App\Services;

use App\Models\MediaModel;
use App\Models\UserModel;
use InvalidArgumentException;

class ProfileService
{
    private const MAX_BIO_LENGTH = 160;

    public function __construct(
        private readonly UserModel $users,
        private readonly MediaModel $media,
        private readonly MediaService $mediaService
    ) {
    }

    /** PATCH parcial: solo toca los campos presentes en $fields (claves 'name'/'bio'). */
    public function updateProfile(int $userId, array $fields): array
    {
        $user = $this->users->findById($userId);
        if ($user === null) {
            throw new InvalidArgumentException('Usuario no encontrado');
        }

        $name = array_key_exists('name', $fields) ? trim((string) $fields['name']) : $user['name'];
        $bio = array_key_exists('bio', $fields) ? trim((string) $fields['bio']) : $user['bio'];

        if ($bio !== null && mb_strlen($bio) > self::MAX_BIO_LENGTH) {
            throw new InvalidArgumentException('La biografía es demasiado larga');
        }

        $this->users->updateProfile($userId, $name !== '' ? $name : null, $bio !== '' ? $bio : null);
        return $this->sanitized($userId);
    }

    /** El media_id debe haber sido subido por el propio usuario y ser una imagen. */
    public function setAvatar(int $userId, int $mediaId): array
    {
        $media = $this->media->find($mediaId);
        if ($media === null || (int) $media['user_id'] !== $userId) {
            throw new InvalidArgumentException('Archivo no encontrado');
        }
        if ($media['type'] !== 'image') {
            throw new InvalidArgumentException('El avatar debe ser una imagen');
        }

        $previousAvatarMediaId = $this->users->findById($userId)['avatar_media_id'] ?? null;

        $this->users->updateAvatar($userId, $mediaId);

        // Limpieza del avatar anterior (si tenía uno) para no acumular archivos huérfanos.
        if ($previousAvatarMediaId !== null && (int) $previousAvatarMediaId !== $mediaId) {
            try {
                $this->mediaService->deleteById($userId, (int) $previousAvatarMediaId);
            } catch (InvalidArgumentException) {
                // ya no existía o no era del usuario — nada que limpiar
            }
        }

        return $this->sanitized($userId);
    }

    public function removeAvatar(int $userId): void
    {
        $previousAvatarMediaId = $this->users->findById($userId)['avatar_media_id'] ?? null;
        $this->users->updateAvatar($userId, null);

        if ($previousAvatarMediaId !== null) {
            try {
                $this->mediaService->deleteById($userId, (int) $previousAvatarMediaId);
            } catch (InvalidArgumentException) {
            }
        }
    }

    /** Nunca devolver la fila cruda de `users` — jamás debe salir password_hash. */
    private function sanitized(int $userId): array
    {
        $user = $this->users->findById($userId);
        return [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'name' => $user['name'],
            'bio' => $user['bio'],
            'avatar_media_id' => $user['avatar_media_id'] !== null ? (int) $user['avatar_media_id'] : null,
        ];
    }
}
