<?php

namespace App\Http;

/**
 * Helpers para respuestas JSON estandarizadas.
 *
 * Errores siempre con la forma: { "error": { "code", "message" } }.
 */
final class Response
{
    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function error(string $code, string $message, int $status = 400): void
    {
        self::json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
