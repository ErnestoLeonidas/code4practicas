<?php

namespace App\Models;

use App\Database;

/**
 * Acceso a la tabla pp_estudiantes.
 *
 * Las consultas de listado/detalle incluyen JOIN a pp_carreras y pp_usuarios
 * para exponer carrera_nombre y docente_nombre.
 */
final class Estudiante
{
    /**
     * Listado paginado con filtros. Ordena por apellido, nombre.
     *
     * @param array<string, mixed> $filtros  q | semestre | carrera_id | docente_id
     * @return array<int, array<string, mixed>>
     */
    public static function listar(array $filtros, int $limit, int $offset): array
    {
        [$where, $bindings] = self::construirFiltros($filtros);

        $sql = 'SELECT e.id, e.nombre, e.apellido, e.rut, e.correo_duoc, e.telefono,
                       e.carrera_id, e.semestre_ingreso_practica, e.docente_id,
                       e.activo, e.creado_en, e.actualizado_en,
                       c.nombre AS carrera_nombre,
                       (u.nombre || \' \' || u.apellido) AS docente_nombre
                FROM pp_estudiantes e
                LEFT JOIN pp_carreras c ON c.id = e.carrera_id
                LEFT JOIN pp_usuarios u ON u.id = e.docente_id'
              . $where
              . ' ORDER BY e.apellido, e.nombre LIMIT ? OFFSET ?';

        $stmt = Database::connection()->prepare($sql);

        $pos = 1;
        foreach ($bindings as $valor) {
            $stmt->bindValue($pos++, $valor);
        }
        $stmt->bindValue($pos++, $limit,  \PDO::PARAM_INT);
        $stmt->bindValue($pos,   $offset, \PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Cuenta total de estudiantes que cumplen los filtros.
     *
     * @param array<string, mixed> $filtros
     */
    public static function contar(array $filtros): int
    {
        [$where, $bindings] = self::construirFiltros($filtros);

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM pp_estudiantes e' . $where
        );
        $stmt->execute($bindings);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Devuelve un estudiante por id, incluyendo carrera y docente.
     *
     * @return array<string, mixed>|null
     */
    public static function porId(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT e.id, e.nombre, e.apellido, e.rut, e.correo_duoc, e.telefono,
                    e.carrera_id, e.semestre_ingreso_practica, e.docente_id,
                    e.activo, e.creado_en, e.actualizado_en,
                    c.nombre AS carrera_nombre,
                    (u.nombre || \' \' || u.apellido) AS docente_nombre
             FROM pp_estudiantes e
             LEFT JOIN pp_carreras c ON c.id = e.carrera_id
             LEFT JOIN pp_usuarios u ON u.id = e.docente_id
             WHERE e.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    /**
     * ¿Ya existe un estudiante con ese RUT?
     * Puede excluirse un id (para permitir conservar el propio RUT al actualizar).
     */
    public static function rutExiste(string $rut, ?int $exceptoId = null): bool
    {
        $sql      = 'SELECT COUNT(*) FROM pp_estudiantes WHERE rut = ?';
        $bindings = [$rut];

        if ($exceptoId !== null) {
            $sql .= ' AND id <> ?';
            $bindings[] = $exceptoId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($bindings);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Inserta un estudiante. Setea creado_en y actualizado_en explícitos.
     * Devuelve el id generado.
     *
     * @param array<string, mixed> $datos nombre, apellido, rut, correo_duoc?, telefono?,
     *                                     carrera_id?, semestre_ingreso_practica?, docente_id?
     */
    public static function crear(array $datos): int
    {
        $ahora = date('Y-m-d H:i:s');

        $stmt = Database::connection()->prepare(
            'INSERT INTO pp_estudiantes
                (nombre, apellido, rut, correo_duoc, telefono,
                 carrera_id, semestre_ingreso_practica, docente_id,
                 activo, creado_en, actualizado_en)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)'
        );
        $stmt->execute([
            $datos['nombre'],
            $datos['apellido'],
            $datos['rut'],
            $datos['correo_duoc']               ?? null,
            $datos['telefono']                  ?? null,
            $datos['carrera_id']                ?? null,
            $datos['semestre_ingreso_practica'] ?? null,
            $datos['docente_id']                ?? null,
            $ahora,
            $ahora,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * Actualiza los datos editables de un estudiante. Setea actualizado_en explícito.
     *
     * @param array<string, mixed> $datos
     */
    public static function actualizar(int $id, array $datos): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pp_estudiantes
                SET nombre = ?, apellido = ?, rut = ?, correo_duoc = ?, telefono = ?,
                    carrera_id = ?, semestre_ingreso_practica = ?, docente_id = ?,
                    actualizado_en = ?
              WHERE id = ?'
        );
        $stmt->execute([
            $datos['nombre'],
            $datos['apellido'],
            $datos['rut'],
            $datos['correo_duoc']               ?? null,
            $datos['telefono']                  ?? null,
            $datos['carrera_id']                ?? null,
            $datos['semestre_ingreso_practica'] ?? null,
            $datos['docente_id']                ?? null,
            date('Y-m-d H:i:s'),
            $id,
        ]);
    }

    /**
     * Borrado lógico: marca activo = 0. Setea actualizado_en explícito.
     */
    public static function desactivar(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pp_estudiantes SET activo = 0, actualizado_en = ? WHERE id = ?'
        );
        $stmt->execute([date('Y-m-d H:i:s'), $id]);
    }

    /**
     * Construye la cláusula WHERE y sus bindings a partir de los filtros.
     * Los filtros que aplican sobre columnas de la tabla principal usan alias "e.".
     *
     * @param array<string, mixed> $filtros
     * @return array{0: string, 1: array<int, mixed>}
     */
    private static function construirFiltros(array $filtros): array
    {
        $condiciones = ['e.activo = 1'];
        $bindings    = [];

        if (isset($filtros['q']) && $filtros['q'] !== '') {
            $like            = '%' . $filtros['q'] . '%';
            $condiciones[]   = '(e.nombre LIKE ? OR e.apellido LIKE ? OR e.rut LIKE ?)';
            $bindings[]      = $like;
            $bindings[]      = $like;
            $bindings[]      = $like;
        }

        if (isset($filtros['semestre']) && $filtros['semestre'] !== '') {
            $condiciones[] = 'e.semestre_ingreso_practica = ?';
            $bindings[]    = $filtros['semestre'];
        }

        if (isset($filtros['carrera_id']) && $filtros['carrera_id'] !== '') {
            $condiciones[] = 'e.carrera_id = ?';
            $bindings[]    = (int) $filtros['carrera_id'];
        }

        if (isset($filtros['docente_id']) && $filtros['docente_id'] !== '') {
            $condiciones[] = 'e.docente_id = ?';
            $bindings[]    = (int) $filtros['docente_id'];
        }

        $where = ' WHERE ' . implode(' AND ', $condiciones);

        return [$where, $bindings];
    }
}
