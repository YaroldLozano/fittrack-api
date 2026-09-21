<?php

namespace App\Models;

use PDO;

class PasswordResetModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function create(int $userId, string $tokenHash, string $expiresAt): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, :expires_at)'
        );
        $stmt->execute(['user_id' => $userId, 'token_hash' => $tokenHash, 'expires_at' => $expiresAt]);
    }

    public function findValidByTokenHash(string $tokenHash): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM password_resets
             WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > NOW()'
        );
        $stmt->execute(['token_hash' => $tokenHash]);
        $reset = $stmt->fetch();
        return $reset ?: null;
    }

    public function markUsed(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
