<?php

/**
 * Front controller de la API.
 *
 * Todas las rutas /api/* llegan aquí vía .htaccess (o el server embebido de PHP en dev).
 */

use App\Config;
use App\Http\Response;
use App\Http\Router;
use App\Controllers\HealthController;

require __DIR__ . '/src/autoload.php';

$config = Config::load();

// Manejo de errores: en prod se ocultan; en dev se muestran.
$isDev = ($config['env'] ?? 'dev') === 'dev';
error_reporting(E_ALL);
ini_set('display_errors', $isDev ? '1' : '0');
ini_set('log_errors', '1');

// CORS: en dev permitimos el origen del server de Vite.
$allowedOrigin = $config['app_url'] ?? 'http://localhost:5173';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && $origin === $allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Preflight CORS.
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Ruta solicitada (sin querystring).
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$router = new Router();

// --- Rutas ---
$router->get('/api/health', [HealthController::class, 'index']);

// Manejo global de excepciones no controladas.
try {
    $router->dispatch($method, $path);
} catch (\Throwable $e) {
    error_log('[API] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $message = $isDev ? $e->getMessage() : 'Error interno del servidor.';
    Response::error('internal_error', $message, 500);
}
