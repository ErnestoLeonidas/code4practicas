<?php

namespace App\Models;

use App\Database;

/**
 * Acceso a la tabla pp_carreras.
 *
 * Nota: pp_carreras no tiene creado_en ni actualizado_en; solo id, nombre, escuela, activo.
 */
final class Carrera
{
    /**
     * Lista todas las carreras activas, ordenadas por nombre.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function listar(): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, nombre, escuela, activo FROM pp_carreras WHERE activo = 1 ORDER BY nombre'
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Busca una carrera por id (activa o no).
     *
     * @return array<string, mixed>|null
     */
    public static function porId(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, nombre, escuela, activo FROM pp_carreras WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    /**
     * Inserta una nueva carrera. Devuelve el id generado.
     *
     * @param array<string, mixed> $datos nombre, escuela (opcional)
     */
    public static function crear(array $datos): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO pp_carreras (nombre, escuela, activo) VALUES (?, ?, 1)'
        );
        $stmt->execute([
            $datos['nombre'],
            $datos['escuela'] ?? null,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * Actualiza nombre y/o escuela de una carrera.
     * pp_carreras no tiene actualizado_en, así que solo se tocan los campos de datos.
     *
     * @param array<string, mixed> $datos nombre, escuela (opcional)
     */
    public static function actualizar(int $id, array $datos): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pp_carreras SET nombre = ?, escuela = ? WHERE id = ?'
        );
        $stmt->execute([
            $datos['nombre'],
            $datos['escuela'] ?? null,
            $id,
        ]);
    }

    /**
     * Borrado lógico: marca activo = 0.
     */
    public static function desactivar(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pp_carreras SET activo = 0 WHERE id = ?'
        );
        $stmt->execute([$id]);
    }
}
