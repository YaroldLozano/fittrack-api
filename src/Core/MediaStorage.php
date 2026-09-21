<?php

namespace App\Core;

use InvalidArgumentException;

/**
 * Guarda archivos subidos fuera de htdocs (ver decisión 3 del plan de FitTrack
 * Social) y genera thumbnails de imagen con GD. Nunca confía en la extensión ni
 * el MIME que declara el cliente — siempre valida el MIME real con fileinfo.
 */
class MediaStorage
{
    private const IMAGE_MIME_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    private const VIDEO_MIME_EXT = [
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
    ];

    private const THUMBNAIL_MAX_WIDTH = 480;

    public function __construct(
        private readonly string $basePath,
        private readonly int $maxImageBytes,
        private readonly int $maxVideoBytes
    ) {
    }

    /**
     * @param array $file Una entrada de $_FILES.
     * @return array{type: string, mime_type: string, path: string, thumbnail_path: ?string, width: ?int, height: ?int, size_bytes: int}
     */
    public function store(array $file): array
    {
        if (!isset($file['tmp_name'], $file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('No se pudo procesar el archivo subido');
        }

        $tmpPath = $file['tmp_name'];
        if (!is_uploaded_file($tmpPath)) {
            throw new InvalidArgumentException('Archivo inválido');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpPath) ?: '';
        finfo_close($finfo);

        $sizeBytes = (int) filesize($tmpPath);

        if (isset(self::IMAGE_MIME_EXT[$mimeType])) {
            $type = 'image';
            $ext = self::IMAGE_MIME_EXT[$mimeType];
            if ($sizeBytes > $this->maxImageBytes) {
                throw new InvalidArgumentException('La imagen supera el tamaño máximo permitido');
            }
        } elseif (isset(self::VIDEO_MIME_EXT[$mimeType])) {
            $type = 'video';
            $ext = self::VIDEO_MIME_EXT[$mimeType];
            if ($sizeBytes > $this->maxVideoBytes) {
                throw new InvalidArgumentException('El video supera el tamaño máximo permitido');
            }
        } else {
            throw new InvalidArgumentException('Tipo de archivo no permitido');
        }

        $subdir = date('Y') . DIRECTORY_SEPARATOR . date('m');
        $dir = $this->basePath . DIRECTORY_SEPARATOR . $subdir;
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException('No se pudo preparar el almacenamiento de medios');
        }

        // Nombre aleatorio: nunca el nombre original del usuario (evita path traversal / colisiones).
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $relativePath = $subdir . DIRECTORY_SEPARATOR . $filename;
        $destination = $dir . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmpPath, $destination)) {
            throw new \RuntimeException('No se pudo guardar el archivo');
        }

        $width = null;
        $height = null;
        $thumbnailRelativePath = null;

        if ($type === 'image') {
            $dimensions = @getimagesize($destination);
            if ($dimensions !== false) {
                [$width, $height] = $dimensions;
            }
            $thumbnailRelativePath = $this->makeThumbnail($destination, $dir, $subdir, $mimeType);
        }

        return [
            'type' => $type,
            'mime_type' => $mimeType,
            'path' => $relativePath,
            'thumbnail_path' => $thumbnailRelativePath,
            'width' => $width,
            'height' => $height,
            'size_bytes' => $sizeBytes,
        ];
    }

    public function absolutePath(string $relativePath): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . $relativePath;
    }

    public function delete(?string $relativePath): void
    {
        if ($relativePath === null) {
            return;
        }
        $full = $this->absolutePath($relativePath);
        if (is_file($full)) {
            @unlink($full);
        }
    }

    private function makeThumbnail(string $sourcePath, string $dir, string $subdir, string $mimeType): ?string
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        $data = @file_get_contents($sourcePath);
        if ($data === false) {
            return null;
        }

        $image = @imagecreatefromstring($data);
        if ($image === false) {
            return null;
        }

        $srcWidth = imagesx($image);
        $srcHeight = imagesy($image);

        if ($srcWidth <= self::THUMBNAIL_MAX_WIDTH) {
            imagedestroy($image);
            return null; // ya es lo bastante pequeña, no vale la pena duplicar
        }

        $ratio = self::THUMBNAIL_MAX_WIDTH / $srcWidth;
        $thumbWidth = self::THUMBNAIL_MAX_WIDTH;
        $thumbHeight = (int) round($srcHeight * $ratio);

        $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);
        if (in_array($mimeType, ['image/png', 'image/webp', 'image/gif'], true)) {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
        }
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $srcWidth, $srcHeight);

        $thumbFilename = bin2hex(random_bytes(16)) . '_thumb.jpg';
        $thumbRelativePath = $subdir . DIRECTORY_SEPARATOR . $thumbFilename;
        $thumbDestination = $dir . DIRECTORY_SEPARATOR . $thumbFilename;

        imagejpeg($thumb, $thumbDestination, 82);
        imagedestroy($thumb);
        imagedestroy($image);

        return $thumbRelativePath;
    }
}
