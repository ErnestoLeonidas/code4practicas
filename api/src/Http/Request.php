<?php

namespace App\Http;

/**
 * Helpers para leer datos de la petición entrante.
 */
final class Request
{
    /**
     * Cuerpo de la petición decodificado como arreglo asociativo.
     * Devuelve [] si el cuerpo está vacío o no es JSON válido.
     *
     * @return array<string, mixed>
     */
    public static function json(): array
    {
        $crudo = file_get_contents('php://input');
        if ($crudo === false || $crudo === '') {
            return [];
        }

        $datos = json_decode($crudo, true);
        return is_array($datos) ? $datos : [];
    }

    /**
     * Dirección IP del cliente (o cadena vacía si no está disponible).
     */
    public static function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
}
