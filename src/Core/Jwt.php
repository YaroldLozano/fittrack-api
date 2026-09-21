<?php

namespace App\Core;

use Firebase\JWT\JWT as FirebaseJwt;
use Firebase\JWT\Key;

class Jwt
{
    public function __construct(
        private readonly string $secret,
        private readonly int $ttlSeconds
    ) {
    }

    /** @return array{token: string, jti: string, expiresAt: int} */
    public function issue(int $userId, string $username): array
    {
        $now = time();
        $expiresAt = $now + $this->ttlSeconds;
        $jti = bin2hex(random_bytes(16));

        $payload = [
            'sub' => $userId,
            'username' => $username,
            'jti' => $jti,
            'iat' => $now,
            'exp' => $expiresAt,
        ];

        $token = FirebaseJwt::encode($payload, $this->secret, 'HS256');

        return ['token' => $token, 'jti' => $jti, 'expiresAt' => $expiresAt];
    }

    /** @return array{sub: int, username: string, jti: string, exp: int}|null */
    public function verify(string $token): ?array
    {
        try {
            $decoded = FirebaseJwt::decode($token, new Key($this->secret, 'HS256'));
            return (array) $decoded;
        } catch (\Throwable) {
            // Any malformed/expired/invalid-signature token is just "not authenticated" — never a 500.
            return null;
        }
    }
}
