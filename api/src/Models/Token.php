<?php

namespace App\Models;

use App\Database;

/**
 * Acceso a la tabla pp_tokens_recuperacion.
 *
 * Almacena hashes HMAC-SHA256 de tokens de recuperación de contraseña.
 * El token plano nunca se persiste.
 */
final class Token
{
    /**
     * Inserta un nuevo token y retorna su id.
     *
     * @param int    $usuarioId id del usuario al que pertenece el token
     * @param string $hash      HMAC-SHA256 del token plano
     * @param string $expiraEn  fecha/hora de expiración en formato 'Y-m-d H:i:s'
     */
    public static function crear(int $usuarioId, string $hash, string $expiraEn): int
    {
        $ahora = date('Y-m-d H:i:s');

        $stmt = Database::connection()->prepare(
            'INSERT INTO pp_tokens_recuperacion (usuario_id, token_hash, expira_en, usado, creado_en)
             VALUES (?, ?, ?, 0, ?)'
        );
        $stmt->execute([$usuarioId, $hash, $expiraEn, $ahora]);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * Busca un token por su hash. Retorna la fila completa o null.
     *
     * @return array<string, mixed>|null
     */
    public static function porHash(string $hash): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM pp_tokens_recuperacion WHERE token_hash = ? LIMIT 1'
        );
        $stmt->execute([$hash]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    /**
     * Marca un token como usado (usado = 1).
     */
    public static function marcarUsado(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pp_tokens_recuperacion SET usado = 1 WHERE id = ?'
        );
        $stmt->execute([$id]);
    }

    /**
     * Invalida todos los tokens activos de un usuario marcándolos como usados.
     * Útil para invalidar tokens pendientes al cambiar la contraseña.
     */
    public static function invalidarPorUsuario(int $usuarioId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pp_tokens_recuperacion SET usado = 1 WHERE usuario_id = ? AND usado = 0'
        );
        $stmt->execute([$usuarioId]);
    }
}
