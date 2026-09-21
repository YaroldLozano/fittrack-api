<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\PasswordResetService;
use InvalidArgumentException;

class AuthController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly PasswordResetService $passwordReset
    ) {
    }

    public function register(Request $request): void
    {
        try {
            $result = $this->auth->register(
                (string) $request->input('username', ''),
                (string) $request->input('password', ''),
                $request->input('email') !== null ? (string) $request->input('email') : null
            );
            Response::success($result, 201);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function login(Request $request): void
    {
        try {
            $result = $this->auth->login(
                (string) $request->input('username', ''),
                (string) $request->input('password', '')
            );
            Response::success($result);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 401);
        }
    }

    public function logout(Request $request): void
    {
        $this->auth->logout((string) $request->jti, (int) $request->userId, (int) $request->tokenExp);
        Response::success(['message' => 'Sesión cerrada']);
    }

    public function updateEmail(Request $request): void
    {
        try {
            $user = $this->auth->updateEmail((int) $request->userId, (string) $request->input('email', ''));
            Response::success(['user' => $user]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function forgotPassword(Request $request): void
    {
        try {
            $result = $this->passwordReset->requestReset((string) $request->input('identifier', ''));
            Response::success($result);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            Response::error('No se pudo enviar el correo de recuperación, intenta más tarde', 500);
        }
    }

    public function resetPassword(Request $request): void
    {
        try {
            $this->passwordReset->resetPassword(
                (string) $request->input('token', ''),
                (string) $request->input('password', '')
            );
            Response::success(['message' => 'Contraseña actualizada correctamente']);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
