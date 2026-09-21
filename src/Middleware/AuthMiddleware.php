<?php

namespace App\Middleware;

use App\Core\Jwt;
use App\Core\Request;
use App\Core\Response;
use App\Models\TokenDenylistModel;

class AuthMiddleware
{
    public function __construct(
        private readonly Jwt $jwt,
        private readonly TokenDenylistModel $denylist
    ) {
    }

    public function __invoke(Request $request): void
    {
        $token = $request->bearerToken();

        if ($token === null) {
            Response::error('No autenticado', 401);
        }

        $payload = $this->jwt->verify($token);

        if ($payload === null) {
            Response::error('Token inválido o expirado', 401);
        }

        if ($this->denylist->isDenied($payload['jti'])) {
            Response::error('Sesión cerrada, inicia sesión de nuevo', 401);
        }

        $request->userId = (int) $payload['sub'];
        $request->jti = (string) $payload['jti'];
        $request->tokenExp = (int) $payload['exp'];
    }
}
