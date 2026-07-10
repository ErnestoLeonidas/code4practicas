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

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, scalar|null>> $rows
     */
    public static function csv(string $filename, array $headers, array $rows, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $output = fopen('php://output', 'wb');
        if ($output === false) {
            throw new \RuntimeException('No se pudo generar el archivo CSV.');
        }

        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, $headers);

        foreach ($rows as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
    }
}
