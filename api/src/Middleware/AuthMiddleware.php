<?php

namespace App\Middleware;

use App\Http\HttpException;
use App\Services\Auth;

/**
 * Exige una sesión válida. Se conecta a las rutas protegidas:
 *   $router->get('/api/auth/me', [AuthController::class, 'me'], [ [AuthMiddleware::class, 'handle'] ]);
 */
final class AuthMiddleware
{
    /**
     * @param array<string, mixed> $params
     * @throws HttpException si no hay usuario autenticado.
     */
    public function handle(array $params): void
    {
        if (!Auth::check()) {
            throw new HttpException(401, 'no_autenticado', 'Debes iniciar sesión.');
        }
    }
}
