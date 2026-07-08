<?php

/**
 * Front controller de la API.
 *
 * Todas las rutas /api/* llegan aquí vía .htaccess (o el server embebido de PHP en dev).
 */

use App\Config;
use App\Http\HttpException;
use App\Http\Response;
use App\Http\Router;
use App\Services\Auth;
use App\Controllers\HealthController;
use App\Controllers\AuthController;
use App\Controllers\UsuarioController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

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

// Arranca la sesión (cookie pp_sesion) antes de resolver la ruta.
Auth::iniciar();

// Ruta solicitada (sin querystring).
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$router = new Router();

// --- Rutas ---
$router->get('/api/health', [HealthController::class, 'index']);

$router->post('/api/auth/login',  [AuthController::class, 'login']);
$router->post('/api/auth/logout', [AuthController::class, 'logout']);
$router->get('/api/auth/me',      [AuthController::class, 'me'], [ [AuthMiddleware::class, 'handle'] ]);
$router->post('/api/auth/cambiar-password', [AuthController::class, 'cambiarPassword'], [ [AuthMiddleware::class, 'handle'] ]);

// Gestión de usuarios (solo admin).
$soloAdmin = [ RoleMiddleware::permitir('admin') ];
$router->get('/api/usuarios',                          [UsuarioController::class, 'index'],   $soloAdmin);
$router->post('/api/usuarios',                         [UsuarioController::class, 'store'],   $soloAdmin);
$router->put('/api/usuarios/{id}',                     [UsuarioController::class, 'update'],  $soloAdmin);
$router->delete('/api/usuarios/{id}',                  [UsuarioController::class, 'destroy'], $soloAdmin);
$router->post('/api/usuarios/{id}/regenerar-password', [UsuarioController::class, 'regenerarPassword'], $soloAdmin);

// Manejo global de excepciones.
try {
    $router->dispatch($method, $path);
} catch (HttpException $e) {
    // Errores de aplicación (validación, auth, permisos): forma { error: { code, message } }.
    Response::error($e->codigo, $e->getMessage(), $e->status);
} catch (\Throwable $e) {
    error_log('[API] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $message = $isDev ? $e->getMessage() : 'Error interno del servidor.';
    Response::error('internal_error', $message, 500);
}
