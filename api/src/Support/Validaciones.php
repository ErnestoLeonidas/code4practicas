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

    /**
     * Valida un RUT chileno (con o sin puntos y guión).
     *
     * Algoritmo dígito verificador:
     *   secuencia [2,3,4,5,6,7] en reversa sobre los dígitos del número.
     *   DV = 11 - (suma % 11); 11 → '0', 10 → 'K', resto → string del número.
     */
    public static function rutValido(string $rut): bool
    {
        // Normaliza: elimina puntos, guión y pasa a mayúsculas.
        $rut = strtoupper(str_replace(['.', '-'], '', trim($rut)));

        if (strlen($rut) < 2) {
            return false;
        }

        // Último carácter = DV; el resto = número.
        $dv     = substr($rut, -1);
        $numero = substr($rut, 0, -1);

        // El número debe ser numérico y positivo.
        if (!ctype_digit($numero) || (int) $numero <= 0) {
            return false;
        }

        // Calcula el DV esperado.
        $suma    = 0;
        $factor  = 2;
        $digitos = strrev($numero);

        for ($i = 0; $i < strlen($digitos); $i++) {
            $suma   += (int) $digitos[$i] * $factor;
            $factor  = $factor === 7 ? 2 : $factor + 1;
        }

        $resultado = 11 - ($suma % 11);
        if ($resultado === 11) {
            $dvEsperado = '0';
        } elseif ($resultado === 10) {
            $dvEsperado = 'K';
        } else {
            $dvEsperado = (string) $resultado;
        }

        return $dv === $dvEsperado;
    }
}
