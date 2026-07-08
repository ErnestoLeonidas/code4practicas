<?php

namespace App\Support;

use App\Config;

/**
 * Validaciones reutilizables para datos de entrada.
 *
 * Centraliza la validación de correos institucionales para que el login
 * (AuthController) y la gestión de usuarios (UsuarioController) apliquen la
 * misma regla.
 */
final class Validaciones
{
    /**
     * ¿El correo tiene un formato de email válido?
     */
    public static function emailValido(string $correo): bool
    {
        return filter_var($correo, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * ¿El dominio del correo (tras la @, en minúsculas) está en
     * dominios_permitidos de la configuración?
     */
    public static function dominioPermitido(string $correo): bool
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
