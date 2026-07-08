<?php

namespace App\Http;

/**
 * Excepción de aplicación que se traduce directamente a una respuesta de error.
 *
 * La captura el front controller (index.php) y responde con la forma estándar
 * { "error": { "code", "message" } } usando el status y el código indicados.
 */
final class HttpException extends \RuntimeException
{
    public function __construct(
        public int $status,
        public string $codigo,
        string $message
    ) {
        parent::__construct($message);
    }
}
