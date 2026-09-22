<?php

require __DIR__ . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Jwt;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Models\TokenDenylistModel;

$config = require __DIR__ . '/config/config.php';

$allowedOrigins = $config['cors']['allowedOrigins'];
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array('*', $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: *');
} elseif ($requestOrigin !== '' && in_array($requestOrigin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $requestOrigin);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (empty($config['jwt']['secret'])) {
    error_log('FitTrack API: JWT_SECRET no está configurado en .env — abortando arranque.');
    Response::error('Error de configuración del servidor', 500);
}

try {
    $db = Database::connection($config['db']);
} catch (\Throwable $e) {
    error_log('FitTrack API: fallo de conexión a la base de datos: ' . $e->getMessage());
    Response::error('Error de conexión a la base de datos', 500);
}

$jwt = new Jwt($config['jwt']['secret'], $config['jwt']['ttl']);
$authMiddleware = new AuthMiddleware($jwt, new TokenDenylistModel($db));

$router = new Router();
require __DIR__ . '/routes.php';

$request = new Request();

try {
    $router->dispatch($request);
} catch (\Throwable $e) {
    error_log('FitTrack API: excepción no capturada: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    Response::error('Error interno del servidor', 500);
}
