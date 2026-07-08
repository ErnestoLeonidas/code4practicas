<?php

namespace App\Middleware;

use App\Http\HttpException;
use App\Services\Auth;

/**
 * Restringe una ruta a ciertos roles. Se usará al conectar rutas en v0.2.0:
 *   $router->post('/api/usuarios', [UsuarioController::class, 'crear'],
 *                 [ RoleMiddleware::permitir('admin') ]);
 */
final class RoleMiddleware
{
    /**
     * Devuelve un middleware (closure) que exige que el usuario tenga uno de $roles.
     *
     * @return callable(array<string, mixed>): void
     */
    public static function permitir(string ...$roles): callable
    {
        return static function (array $params) use ($roles): void {
            $usuario = Auth::usuario();

            if ($usuario === null) {
                throw new HttpException(401, 'no_autenticado', 'Debes iniciar sesión.');
            }

            if (!in_array($usuario['rol'], $roles, true)) {
                throw new HttpException(403, 'sin_permiso', 'No tienes permiso para esta acción.');
            }
        };
    }
}
