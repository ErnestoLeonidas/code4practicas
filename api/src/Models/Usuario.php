<?php

namespace App\Models;

use App\Database;

/**
 * Acceso a la tabla pp_usuarios.
 *
 * Devuelve arreglos asociativos crudos (incluyen password_hash). Para exponer
 * un usuario por la API usa siempre publico().
 */
final class Usuario
{
    /**
     * Busca un usuario por correo (sin importar si está activo).
     *
     * @return array<string, mixed>|null
     */
    public static function porCorreo(string $correo): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM pp_usuarios WHERE correo = ? LIMIT 1'
        );
        $stmt->execute([$correo]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    /**
     * Busca un usuario activo por id.
     *
     * @return array<string, mixed>|null
     */
    public static function porIdActivo(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM pp_usuarios WHERE id = ? AND activo = 1 LIMIT 1'
        );
        $stmt->execute([$id]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    /**
     * Proyección segura de un usuario para exponer por la API.
     * NUNCA incluye password_hash.
     *
     * @param array<string, mixed> $u
     * @return array<string, mixed>
     */
    public static function publico(array $u): array
    {
        return [
            'id'                    => (int) $u['id'],
            'nombre'                => $u['nombre'],
            'apellido'              => $u['apellido'],
            'correo'                => $u['correo'],
            'rol'                   => $u['rol'],
            'debe_cambiar_password' => (bool) (int) $u['debe_cambiar_password'],
        ];
    }
}
