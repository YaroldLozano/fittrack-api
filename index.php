<?php

require __DIR__ . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Jwt;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Models\TokenDenylistModel;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$config = require __DIR__ . '/config/config.php';

try {
    $db = Database::connection($config['db']);
} catch (\Throwable $e) {
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
    Response::error('Error interno del servidor', 500);
}
