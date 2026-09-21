<?php

namespace App\Models;

use PDO;

class TokenDenylistModel
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function add(string $jti, int $userId, int $expiresAtTimestamp): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO token_denylist (jti, user_id, expires_at) VALUES (:jti, :user_id, FROM_UNIXTIME(:expires_at))'
        );
        $stmt->execute(['jti' => $jti, 'user_id' => $userId, 'expires_at' => $expiresAtTimestamp]);
    }

    public function isDenied(string $jti): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM token_denylist WHERE jti = :jti AND expires_at > NOW()');
        $stmt->execute(['jti' => $jti]);
        return (bool) $stmt->fetchColumn();
    }
}
