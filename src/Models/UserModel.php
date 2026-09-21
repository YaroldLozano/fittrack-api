<?php

namespace App\Models;

use PDO;

class UserModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = :username');
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findByUsernameOrEmail(string $identifier): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = :username OR email = :email');
        $stmt->execute(['username' => $identifier, 'email' => $identifier]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function create(string $username, string $passwordHash, ?string $email = null): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, password_hash, email) VALUES (:username, :password_hash, :email)'
        );
        $stmt->execute([
            'username' => $username,
            'password_hash' => $passwordHash,
            'email' => $email,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updatePasswordHash(int $userId, string $passwordHash): void
    {
        $stmt = $this->db->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
        $stmt->execute(['password_hash' => $passwordHash, 'id' => $userId]);
    }

    /** Búsqueda por username/nombre para el buscador de amigos. Nunca devuelve email. */
    public function searchByUsername(string $query, int $excludeUserId, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, username, name, bio, avatar_media_id FROM users
             WHERE (username LIKE :query1 OR name LIKE :query2) AND id != :exclude_id
             ORDER BY username
             LIMIT ' . (int) $limit
        );
        $like = '%' . $query . '%';
        $stmt->execute(['query1' => $like, 'query2' => $like, 'exclude_id' => $excludeUserId]);
        return $stmt->fetchAll();
    }

    public function updateEmail(int $userId, string $email): void
    {
        $stmt = $this->db->prepare('UPDATE users SET email = :email WHERE id = :id');
        $stmt->execute(['email' => $email, 'id' => $userId]);
    }

    public function updateProfile(int $userId, ?string $name, ?string $bio): void
    {
        $stmt = $this->db->prepare('UPDATE users SET name = :name, bio = :bio WHERE id = :id');
        $stmt->execute(['name' => $name, 'bio' => $bio, 'id' => $userId]);
    }

    public function updateAvatar(int $userId, ?int $mediaId): void
    {
        $stmt = $this->db->prepare('UPDATE users SET avatar_media_id = :media_id WHERE id = :id');
        $stmt->execute(['media_id' => $mediaId, 'id' => $userId]);
    }
}
