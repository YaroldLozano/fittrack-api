<?php

function load_env(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        $value = trim($value);

        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

load_env(__DIR__ . '/../.env');

return [
    'db' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: '3306',
        'user' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'name' => getenv('DB_NAME') ?: 'Yarold',
    ],
    'jwt' => [
        'secret' => getenv('JWT_SECRET') ?: '',
        'ttl' => (int) (getenv('JWT_TTL_SECONDS') ?: 604800),
    ],
    'ai' => [
        'anthropicApiKey' => getenv('ANTHROPIC_API_KEY') ?: '',
        'anthropicModel' => getenv('ANTHROPIC_MODEL') ?: 'claude-sonnet-5',
    ],
    'mail' => [
        'host' => getenv('SMTP_HOST') ?: '',
        'port' => (int) (getenv('SMTP_PORT') ?: 587),
        'username' => getenv('SMTP_USERNAME') ?: '',
        'password' => getenv('SMTP_PASSWORD') ?: '',
        'fromEmail' => getenv('SMTP_FROM_EMAIL') ?: '',
        'fromName' => getenv('SMTP_FROM_NAME') ?: 'FitTrack',
    ],
    'storage' => [
        // Fuera de htdocs a propósito: los medios nunca deben ser servibles como
        // estáticos por Apache — todo acceso pasa por MediaController (visibilidad,
        // amistad y bloqueos se validan ahí). Ver decisión 3 del plan de FitTrack Social.
        'media_path' => getenv('MEDIA_STORAGE_PATH') ?: 'C:\\xampp\\fitness-api-storage\\media',
        'max_image_bytes' => (int) (getenv('MEDIA_MAX_IMAGE_BYTES') ?: 10 * 1024 * 1024),
        'max_video_bytes' => (int) (getenv('MEDIA_MAX_VIDEO_BYTES') ?: 30 * 1024 * 1024),
    ],
];
