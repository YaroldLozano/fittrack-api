<?php

namespace App\Services;

use App\Core\Jwt;
use App\Models\TokenDenylistModel;
use App\Models\UserModel;
use InvalidArgumentException;

class AuthService
{
    public function __construct(
        private readonly UserModel $users,
        private readonly TokenDenylistModel $denylist,
        private readonly Jwt $jwt
    ) {
    }

    public function register(string $username, string $password, ?string $email = null): array
    {
        $username = trim($username);

        if ($username === '' || $password === '') {
            throw new InvalidArgumentException('Usuario y contraseña son requeridos');
        }

        if (strlen($password) < 6) {
            throw new InvalidArgumentException('La contraseña debe tener al menos 6 caracteres');
        }

        if ($this->users->findByUsername($username) !== null) {
            throw new InvalidArgumentException('El usuario ya existe');
        }

        $email = $email !== null && trim($email) !== '' ? trim($email) : null;
        if ($email !== null && $this->users->findByEmail($email) !== null) {
            throw new InvalidArgumentException('Ese correo ya está registrado');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $userId = $this->users->create($username, $hash, $email);

        return $this->issueTokenFor($userId, $username, $email);
    }

    public function login(string $username, string $password): array
    {
        $user = $this->users->findByUsername(trim($username));

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            throw new InvalidArgumentException('Usuario o contraseña incorrectos');
        }

        return $this->issueTokenFor((int) $user['id'], $user['username'], $user['email'] ?? null);
    }

    public function logout(string $jti, int $userId, int $expiresAtTimestamp): void
    {
        $this->denylist->add($jti, $userId, $expiresAtTimestamp);
    }

    public function updateEmail(int $userId, string $email): array
    {
        $email = trim($email);
        if ($email === '') {
            throw new InvalidArgumentException('El correo no puede estar vacío');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Correo inválido');
        }

        $existing = $this->users->findByEmail($email);
        if ($existing !== null && (int) $existing['id'] !== $userId) {
            throw new InvalidArgumentException('Ese correo ya está en uso por otra cuenta');
        }

        $this->users->updateEmail($userId, $email);
        $user = $this->users->findById($userId);

        return ['id' => (int) $user['id'], 'username' => $user['username'], 'email' => $user['email']];
    }

    private function issueTokenFor(int $userId, string $username, ?string $email): array
    {
        $issued = $this->jwt->issue($userId, $username);
        return [
            'token' => $issued['token'],
            'expiresAt' => $issued['expiresAt'],
            'user' => ['id' => $userId, 'username' => $username, 'email' => $email],
        ];
    }
}
