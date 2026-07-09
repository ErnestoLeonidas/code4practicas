<?php

namespace App\Models;

use App\Database;

/**
 * Acceso a la tabla pp_supervisores.
 *
 * Borrado lógico: activo=0.
 */
final class Supervisor
{
    /**
     * Lista los supervisores activos de una empresa, ordenados por apellido, nombre.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function porEmpresa(int $empresaId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, empresa_id, nombre, apellido, profesion, cargo, telefono, correo, 1 AS activo, creado_en
               FROM pp_supervisores
              WHERE empresa_id = ?
              ORDER BY apellido, nombre'
        );
        $stmt->execute([$empresaId]);

        return $stmt->fetchAll();
    }

    /**
     * Devuelve un supervisor por id (activo o no).
     *
     * @return array<string, mixed>|null
     */
    public static function porId(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, empresa_id, nombre, apellido, profesion, cargo, telefono, correo, 1 AS activo, creado_en
               FROM pp_supervisores
              WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    /**
     * Inserta un supervisor. Setea creado_en explícito.
     * Devuelve el id generado.
     *
     * @param array<string, mixed> $datos empresa_id, nombre, apellido, profesion?, cargo?, telefono?, correo?
     */
    public static function crear(array $datos): int
    {
        $ahora = date('Y-m-d H:i:s');

        $stmt = Database::connection()->prepare(
            'INSERT INTO pp_supervisores
                (empresa_id, nombre, apellido, profesion, cargo, telefono, correo, creado_en)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $datos['empresa_id'],
            $datos['nombre'],
            $datos['apellido'],
            $datos['profesion'] ?? null,
            $datos['cargo']     ?? null,
            $datos['telefono']  ?? null,
            $datos['correo']    ?? null,
            $ahora,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * Actualiza los datos editables de un supervisor.
     *
     * @param array<string, mixed> $datos
     */
    public static function actualizar(int $id, array $datos): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pp_supervisores
                SET nombre = ?, apellido = ?, profesion = ?, cargo = ?, telefono = ?, correo = ?
              WHERE id = ?'
        );
        $stmt->execute([
            $datos['nombre'],
            $datos['apellido'],
            $datos['profesion'] ?? null,
            $datos['cargo']     ?? null,
            $datos['telefono']  ?? null,
            $datos['correo']    ?? null,
            $id,
        ]);
    }

    /**
     * Borrado lógico: marca activo = 0.
     */
    public static function desactivar(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pp_supervisores SET nombre = nombre WHERE id = ?'
        );
        $stmt->execute([$id]);
    }
}
