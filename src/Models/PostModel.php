<?php

namespace App\Models;

use PDO;

class PostModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function create(int $userId, ?string $caption, string $postType, ?string $contextJson, string $visibility): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO posts (user_id, caption, post_type, context_json, visibility)
             VALUES (:user_id, :caption, :post_type, :context_json, :visibility)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'caption' => $caption,
            'post_type' => $postType,
            'context_json' => $contextJson,
            'visibility' => $visibility,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM posts WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findOwnedBy(int $userId, int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM posts WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function update(int $id, ?string $caption, string $visibility): void
    {
        $stmt = $this->db->prepare('UPDATE posts SET caption = :caption, visibility = :visibility WHERE id = :id');
        $stmt->execute(['caption' => $caption, 'visibility' => $visibility, 'id' => $id]);
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE posts SET deleted_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** Feed: publicaciones propias + de amigos, paginado por cursor, más reciente primero. */
    public function feedForAuthors(array $authorIds, ?int $beforeId, int $limit): array
    {
        if (empty($authorIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($authorIds), '?'));
        $params = $authorIds;
        $cursorClause = '';
        if ($beforeId !== null) {
            $cursorClause = ' AND p.id < ?';
            $params[] = $beforeId;
        }

        $stmt = $this->db->prepare(
            "SELECT p.* FROM posts p
             WHERE p.user_id IN ($placeholders) AND p.deleted_at IS NULL $cursorClause
             ORDER BY p.id DESC
             LIMIT " . (int) $limit
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function hasMoreForAuthors(array $authorIds, int $oldestIdInPage): bool
    {
        if (empty($authorIds)) {
            return false;
        }
        $placeholders = implode(',', array_fill(0, count($authorIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT 1 FROM posts WHERE user_id IN ($placeholders) AND deleted_at IS NULL AND id < ? LIMIT 1"
        );
        $stmt->execute([...$authorIds, $oldestIdInPage]);
        return (bool) $stmt->fetchColumn();
    }

    /** Publicaciones de un perfil visibles para el visitante (según relación ya resuelta por el servicio). */
    public function forProfile(int $profileUserId, array $allowedVisibilities, ?int $beforeId, int $limit): array
    {
        $placeholders = implode(',', array_fill(0, count($allowedVisibilities), '?'));
        $params = array_merge([$profileUserId], $allowedVisibilities);
        $cursorClause = '';
        if ($beforeId !== null) {
            $cursorClause = ' AND p.id < ?';
            $params[] = $beforeId;
        }

        $stmt = $this->db->prepare(
            "SELECT p.* FROM posts p
             WHERE p.user_id = ? AND p.visibility IN ($placeholders) AND p.deleted_at IS NULL $cursorClause
             ORDER BY p.id DESC
             LIMIT " . (int) $limit
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function hasMoreForProfile(int $profileUserId, array $allowedVisibilities, int $oldestIdInPage): bool
    {
        $placeholders = implode(',', array_fill(0, count($allowedVisibilities), '?'));
        $stmt = $this->db->prepare(
            "SELECT 1 FROM posts WHERE user_id = ? AND visibility IN ($placeholders) AND deleted_at IS NULL AND id < ? LIMIT 1"
        );
        $stmt->execute([$profileUserId, ...$allowedVisibilities, $oldestIdInPage]);
        return (bool) $stmt->fetchColumn();
    }
}
