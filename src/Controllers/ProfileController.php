<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\ProfileService;
use InvalidArgumentException;

class ProfileController
{
    public function __construct(private readonly ProfileService $profile)
    {
    }

    public function update(Request $request): void
    {
        try {
            $fields = [];
            if (array_key_exists('name', $request->body)) {
                $fields['name'] = $request->body['name'];
            }
            if (array_key_exists('bio', $request->body)) {
                $fields['bio'] = $request->body['bio'];
            }
            $user = $this->profile->updateProfile((int) $request->userId, $fields);
            Response::success(['user' => $user]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /** Recibe un media_id ya subido vía POST /media (reutiliza la misma subida que posts/stories). */
    public function setAvatar(Request $request): void
    {
        try {
            $mediaId = (int) $request->input('media_id', 0);
            if ($mediaId <= 0) {
                throw new InvalidArgumentException('media_id requerido');
            }
            $user = $this->profile->setAvatar((int) $request->userId, $mediaId);
            Response::success(['user' => $user]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function removeAvatar(Request $request): void
    {
        $this->profile->removeAvatar((int) $request->userId);
        Response::success(['message' => 'Foto de perfil eliminada']);
    }
}
