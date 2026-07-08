<?php

namespace App\Controllers;

use App\Config;
use App\Http\Request;
use App\Http\Response;
use App\Models\LoginIntento;
use App\Models\Usuario;
use App\Services\Auth;
use App\Services\Password;

/**
 * Autenticación: login (con correo institucional), logout y sesión actual.
 */
final class AuthController
{
    /** Intentos fallidos permitidos antes de bloquear temporalmente. */
    private const MAX_INTENTOS = 5;

    /** Ventana (en minutos) sobre la que se cuentan los intentos fallidos. */
    private const VENTANA_MIN = 15;

    /**
     * POST /api/auth/login
     */
    public function login(array $params): void
    {
        $cuerpo   = Request::json();
        $correo   = trim((string) ($cuerpo['correo'] ?? ''));
        $password = (string) ($cuerpo['password'] ?? '');

        // 1) Campos obligatorios.
        if ($correo === '' || $password === '') {
            Response::error('datos_invalidos', 'Debes indicar correo y contraseña.', 422);
            return;
        }

        // 2) Correo válido y de dominio institucional permitido.
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || !$this->dominioPermitido($correo)) {
            Response::error('dominio_no_institucional', 'Debes usar un correo institucional Duoc válido.', 422);
            return;
        }

        // 3) Rate limiting: demasiados intentos fallidos recientes.
        if (LoginIntento::fallidosRecientes($correo, self::VENTANA_MIN) >= self::MAX_INTENTOS) {
            Response::error('demasiados_intentos', 'Demasiados intentos fallidos. Inténtalo de nuevo en unos minutos.', 429);
            return;
        }

        // 4) Verificación de credenciales (mensaje genérico, sin revelar el motivo).
        $usuario = Usuario::porCorreo($correo);
        $credencialesOk = $usuario !== null
            && (int) $usuario['activo'] === 1
            && Password::verify($password, (string) $usuario['password_hash']);

        if (!$credencialesOk) {
            LoginIntento::registrar($correo, Request::ip(), false);
            Response::error('credenciales_invalidas', 'Correo o contraseña incorrectos.', 401);
            return;
        }

        // Éxito: resetea el contador, abre sesión y responde con el usuario público.
        LoginIntento::limpiar($correo);
        Auth::login($usuario);

        Response::json(['usuario' => Usuario::publico($usuario)]);
    }

    /**
     * POST /api/auth/logout — público e idempotente.
     */
    public function logout(array $params): void
    {
        Auth::logout();
        Response::json(['ok' => true]);
    }

    /**
     * GET /api/auth/me — protegido por AuthMiddleware.
     */
    public function me(array $params): void
    {
        // Si llegó aquí, el middleware ya garantizó que hay usuario autenticado.
        $usuario = Auth::usuario();
        Response::json(['usuario' => Usuario::publico($usuario)]);
    }

    /**
     * ¿El dominio del correo (tras la @) está en dominios_permitidos?
     */
    private function dominioPermitido(string $correo): bool
    {
        $pos = strrpos($correo, '@');
        if ($pos === false) {
            return false;
        }

        $dominio    = strtolower(substr($correo, $pos + 1));
        $permitidos = Config::get('dominios_permitidos', []);

        return in_array($dominio, $permitidos, true);
    }
}
