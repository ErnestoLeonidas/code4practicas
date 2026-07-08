<?php

namespace App\Services;

use App\Config;
use App\Models\Usuario;

/**
 * Gestión de la sesión de usuario (cookie pp_sesion).
 *
 * La cookie es HttpOnly + SameSite=Lax; secure solo en producción. El usuario
 * autenticado se carga de forma perezosa desde la BD y se cachea por request.
 */
final class Auth
{
    private const COOKIE = 'pp_sesion';

    private static bool $cargado = false;

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * Arranca la sesión con los parámetros de cookie correctos. Idempotente.
     */
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => Config::get('env', 'dev') === 'prod',
        ]);
        session_name(self::COOKIE);
        session_start();
    }

    /**
     * Marca al usuario como autenticado. Regenera el id de sesión para evitar
     * fijación y limpia el cache interno.
     *
     * @param array<string, mixed> $usuario
     */
    public static function login(array $usuario): void
    {
        self::iniciar();
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = (int) $usuario['id'];

        self::$cargado = false;
        self::$cache   = null;
    }

    /**
     * Cierra la sesión: vacía $_SESSION, borra la cookie y destruye la sesión.
     */
    public static function logout(): void
    {
        self::iniciar();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();

        self::$cargado = false;
        self::$cache   = null;
    }

    /**
     * Usuario autenticado ACTIVO (carga perezosa + cache por request).
     * Devuelve null si no hay sesión o el usuario ya no existe / está inactivo.
     *
     * @return array<string, mixed>|null
     */
    public static function usuario(): ?array
    {
        if (self::$cargado) {
            return self::$cache;
        }

        self::$cargado = true;
        self::$cache   = null;

        $id = $_SESSION['usuario_id'] ?? null;
        if ($id === null) {
            return null;
        }

        self::$cache = Usuario::porIdActivo((int) $id);
        return self::$cache;
    }

    public static function check(): bool
    {
        return self::usuario() !== null;
    }
}
