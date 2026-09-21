<?php

namespace App\Services;

use App\Core\Mailer;
use App\Models\PasswordResetModel;
use App\Models\UserModel;
use InvalidArgumentException;

class PasswordResetService
{
    private const TOKEN_TTL_MINUTES = 30;

    public function __construct(
        private readonly UserModel $users,
        private readonly PasswordResetModel $resets,
        private readonly Mailer $mailer
    ) {
    }

    /**
     * El código se envía por correo, nunca en la respuesta de la API.
     * Responde con el mismo mensaje exista o no la cuenta, para no permitir enumerar usuarios.
     */
    public function requestReset(string $identifier): array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            throw new InvalidArgumentException('Ingresa tu usuario o correo');
        }

        $generic = ['message' => 'Si existe una cuenta con correo registrado, te enviamos un código de recuperación.'];

        $user = $this->users->findByUsernameOrEmail($identifier);
        if ($user === null || empty($user['email'])) {
            return $generic;
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + self::TOKEN_TTL_MINUTES * 60);

        $this->resets->create((int) $user['id'], $tokenHash, $expiresAt);
        $this->mailer->sendPasswordResetCode((string) $user['email'], (string) $user['username'], $token, self::TOKEN_TTL_MINUTES);

        return $generic;
    }

    public function resetPassword(string $token, string $newPassword): void
    {
        if (strlen($newPassword) < 6) {
            throw new InvalidArgumentException('La contraseña debe tener al menos 6 caracteres');
        }

        $tokenHash = hash('sha256', $token);
        $reset = $this->resets->findValidByTokenHash($tokenHash);

        if ($reset === null) {
            throw new InvalidArgumentException('Token inválido o expirado');
        }

        $this->users->updatePasswordHash((int) $reset['user_id'], password_hash($newPassword, PASSWORD_DEFAULT));
        $this->resets->markUsed((int) $reset['id']);
    }
}
