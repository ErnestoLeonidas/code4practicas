<?php

namespace App\Models;

use App\Database;

/**
 * Acceso a la tabla pp_login_intentos (rate limiting de login).
 *
 * Portabilidad SQLite/MySQL: el umbral temporal se calcula en PHP y creado_en
 * se inserta explícito con date('Y-m-d H:i:s'). No se usan funciones de fecha
 * del motor para no depender de diferencias entre drivers.
 */
final class LoginIntento
{
    /**
     * Cantidad de intentos FALLIDOS para un correo dentro de los últimos $minutos.
     */
    public static function fallidosRecientes(string $correo, int $minutos): int
    {
        $desde = date('Y-m-d H:i:s', time() - $minutos * 60);

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM pp_login_intentos WHERE correo = ? AND exitoso = 0 AND creado_en >= ?'
        );
        $stmt->execute([$correo, $desde]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Registra un intento de login (exitoso o fallido).
     */
    public static function registrar(string $correo, string $ip, bool $exito): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO pp_login_intentos (correo, ip, exitoso, creado_en) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $correo,
            $ip !== '' ? $ip : null,
            $exito ? 1 : 0,
            date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Borra los intentos de un correo (se llama tras un login exitoso).
     */
    public static function limpiar(string $correo): void
    {
        $stmt = Database::connection()->prepare(
            'DELETE FROM pp_login_intentos WHERE correo = ?'
        );
        $stmt->execute([$correo]);
    }
}
