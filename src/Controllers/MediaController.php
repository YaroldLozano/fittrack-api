<?php

namespace App\Controllers;

use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Services\MediaService;
use InvalidArgumentException;

class MediaController
{
    public function __construct(private readonly MediaService $mediaService)
    {
    }

    /** Subida genérica reutilizada por posts, stories y avatar (ver decisión 4 del plan). */
    public function upload(Request $request): void
    {
        try {
            $file = $_FILES['file'] ?? null;
            if ($file === null) {
                throw new InvalidArgumentException('No se envió ningún archivo');
            }
            $result = $this->mediaService->upload((int) $request->userId, $file);
            Response::success(['media' => $result], 201);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    /** Streaming autenticado — único punto por el que se sirve cualquier archivo (nunca estático). */
    public function show(Request $request): void
    {
        try {
            $wantThumbnail = (string) $request->input('thumb', '0') === '1';
            $resolved = $this->mediaService->resolveForViewing(
                (int) $request->userId,
                (int) $request->params['id'],
                $wantThumbnail
            );
        } catch (NotFoundException $e) {
            Response::error($e->getMessage(), 404);
        } catch (ForbiddenException $e) {
            Response::error($e->getMessage(), 403);
        }

        if (!is_file($resolved['absolute_path'])) {
            Response::error('Archivo no encontrado', 404);
        }

        header('Content-Type: ' . $resolved['mime_type']);
        header('Content-Length: ' . filesize($resolved['absolute_path']));
        header('Cache-Control: private, max-age=3600');
        readfile($resolved['absolute_path']);
        exit;
    }

    public function destroy(Request $request): void
    {
        try {
            $this->mediaService->deleteById((int) $request->userId, (int) $request->params['id']);
            Response::success(['message' => 'Archivo eliminado']);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 404);
        }
    }
}
