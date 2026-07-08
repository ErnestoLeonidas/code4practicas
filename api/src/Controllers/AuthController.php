<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\LoginIntento;
use App\Models\Usuario;
use App\Services\Auth;
use App\Services\Password;
use App\Support\Validaciones;

/**
 * Autenticación: login (con correo institucional), logout y sesión actual.
 */
final class AuthController
{
    /** Intentos fallidos permitidos antes de bloquear temporalmente. */
    private const MAX_INTENTOS = 5;

    /** Ventana (en minutos) sobre la que se cuentan los intentos fallidos. */
    private const VENTANA_MIN = 15;

    /** Longitud mínima de una contraseña elegida por el usuario. */
    private const MIN_PASSWORD = 8;

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
        if (!Validaciones::emailValido($correo) || !Validaciones::dominioPermitido($correo)) {
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
     * POST /api/auth/cambiar-password — protegido por AuthMiddleware.
     *
     * Cambia la contraseña del usuario en sesión: verifica la actual, valida la
     * longitud de la nueva y limpia el flag debe_cambiar_password.
     */
    public function cambiarPassword(array $params): void
    {
        // El AuthMiddleware ya garantizó que hay un usuario autenticado.
        $usuario = Auth::usuario();

        $cuerpo         = Request::json();
        $passwordActual = (string) ($cuerpo['password_actual'] ?? '');
        $passwordNueva  = (string) ($cuerpo['password_nueva'] ?? '');

        if (!Password::verify($passwordActual, (string) $usuario['password_hash'])) {
            Response::error('password_actual_incorrecta', 'La contraseña actual no es correcta.', 422);
            return;
        }

        if (mb_strlen($passwordNueva) < self::MIN_PASSWORD) {
            Response::error('password_debil', 'La nueva contraseña debe tener al menos ' . self::MIN_PASSWORD . ' caracteres.', 422);
            return;
        }

        Usuario::actualizarPassword((int) $usuario['id'], Password::hash($passwordNueva), 0);

        Response::json(['ok' => true]);
    }
}
