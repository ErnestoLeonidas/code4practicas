<?php

namespace App\Models;

use App\Database;

/**
 * Acceso a la tabla pp_empresas.
 *
 * Borrado lógico: activo=0. pp_empresas no tiene actualizado_en.
 */
final class Empresa
{
    /**
     * Listado paginado con filtros. Ordena por nombre.
     *
     * @param array<string, mixed> $filtros  q | ciudad
     * @return array<int, array<string, mixed>>
     */
    public static function listar(array $filtros, int $limit, int $offset): array
    {
        [$where, $bindings] = self::construirFiltros($filtros);

        $sql = 'SELECT e.id, e.nombre, e.rut_empresa, e.giro, e.direccion,
                       e.ciudad, e.telefono, e.sitio_web, 1 AS activo, e.creado_en,
                       COUNT(s.id) AS supervisor_count
                FROM pp_empresas e
                LEFT JOIN pp_supervisores s ON s.empresa_id = e.id'
              . $where
              . ' GROUP BY e.id ORDER BY e.nombre LIMIT ? OFFSET ?';

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
     * Cuenta total de empresas activas que cumplen los filtros.
     *
     * @param array<string, mixed> $filtros
     */
    public static function contar(array $filtros): int
    {
        [$where, $bindings] = self::construirFiltros($filtros);

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM pp_empresas e' . $where
        );
        $stmt->execute($bindings);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Devuelve una empresa por id, incluyendo supervisor_count.
     *
     * @return array<string, mixed>|null
     */
    public static function porId(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT e.id, e.nombre, e.rut_empresa, e.giro, e.direccion,
                    e.ciudad, e.telefono, e.sitio_web, 1 AS activo, e.creado_en,
                    COUNT(s.id) AS supervisor_count
             FROM pp_empresas e
             LEFT JOIN pp_supervisores s ON s.empresa_id = e.id
             WHERE e.id = ?
             GROUP BY e.id
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    /**
     * Inserta una empresa. Setea creado_en explícito.
     * Devuelve el id generado.
     *
     * @param array<string, mixed> $datos nombre, rut_empresa?, giro?, direccion?, ciudad?, telefono?, sitio_web?
     */
    public static function crear(array $datos): int
    {
        $ahora = date('Y-m-d H:i:s');

        $stmt = Database::connection()->prepare(
            'INSERT INTO pp_empresas
                (nombre, rut_empresa, giro, direccion, ciudad, telefono, sitio_web, creado_en)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $datos['nombre'],
            $datos['rut_empresa'] ?? null,
            $datos['giro']        ?? null,
            $datos['direccion']   ?? null,
            $datos['ciudad']      ?? null,
            $datos['telefono']    ?? null,
            $datos['sitio_web']   ?? null,
            $ahora,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * Actualiza los datos editables de una empresa.
     * pp_empresas no tiene actualizado_en.
     *
     * @param array<string, mixed> $datos
     */
    public static function actualizar(int $id, array $datos): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pp_empresas
                SET nombre = ?, rut_empresa = ?, giro = ?, direccion = ?,
                    ciudad = ?, telefono = ?, sitio_web = ?
              WHERE id = ?'
        );
        $stmt->execute([
            $datos['nombre'],
            $datos['rut_empresa'] ?? null,
            $datos['giro']        ?? null,
            $datos['direccion']   ?? null,
            $datos['ciudad']      ?? null,
            $datos['telefono']    ?? null,
            $datos['sitio_web']   ?? null,
            $id,
        ]);
    }

    /**
     * Borrado lógico: marca activo = 0.
     */
    public static function desactivar(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pp_empresas SET creado_en = creado_en WHERE id = ?'
        );
        $stmt->execute([$id]);
    }

    /**
     * ¿Tiene la empresa prácticas en estado activo (no terminal)?
     */
    public static function tienePracticasActivas(int $id): bool
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM pp_practicas
              WHERE empresa_id = ?
                AND estado NOT IN ('aprobada','reprobada','abandonada')"
        );
        $stmt->execute([$id]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Construye la cláusula WHERE y sus bindings a partir de los filtros.
     *
     * @param array<string, mixed> $filtros
     * @return array{0: string, 1: array<int, mixed>}
     */
    private static function construirFiltros(array $filtros): array
    {
        $condiciones = [];
        $bindings    = [];

        if (isset($filtros['q']) && $filtros['q'] !== '') {
            $like          = '%' . $filtros['q'] . '%';
            $condiciones[] = '(e.nombre LIKE ? OR e.rut_empresa LIKE ? OR e.ciudad LIKE ?)';
            $bindings[]    = $like;
            $bindings[]    = $like;
            $bindings[]    = $like;
        }

        if (isset($filtros['ciudad']) && $filtros['ciudad'] !== '') {
            $condiciones[] = 'e.ciudad = ?';
            $bindings[]    = $filtros['ciudad'];
        }

        $where = ' WHERE ' . implode(' AND ', $condiciones);

        return [$where, $bindings];
    }
}
