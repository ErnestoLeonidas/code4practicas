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

    /**
     * Proyección para el panel de administración. Incluye estado y fechas,
     * pero NUNCA password_hash.
     *
     * @param array<string, mixed> $u
     * @return array<string, mixed>
     */
    public static function publicoAdmin(array $u): array
    {
        return [
            'id'                    => (int) $u['id'],
            'nombre'                => $u['nombre'],
            'apellido'              => $u['apellido'],
            'correo'                => $u['correo'],
            'rol'                   => $u['rol'],
            'debe_cambiar_password' => (bool) (int) $u['debe_cambiar_password'],
            'activo'                => (bool) (int) $u['activo'],
            'creado_en'             => $u['creado_en'],
            'actualizado_en'        => $u['actualizado_en'],
        ];
    }

    /**
     * Busca un usuario por id sin importar su estado (activo o no).
     *
     * @return array<string, mixed>|null
     */
    public static function porId(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM pp_usuarios WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    /**
     * ¿Ya existe un usuario con ese correo? Puede excluirse un id (para permitir
     * conservar el propio correo al actualizar).
     */
    public static function correoExiste(string $correo, ?int $exceptoId = null): bool
    {
        $sql      = 'SELECT COUNT(*) FROM pp_usuarios WHERE correo = ?';
        $bindings = [$correo];

        if ($exceptoId !== null) {
            $sql .= ' AND id <> ?';
            $bindings[] = $exceptoId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($bindings);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Listado paginado con filtros. Ordena por apellido, nombre.
     *
     * @param array<string, mixed> $filtros q|rol|activo
     * @return array<int, array<string, mixed>>
     */
    public static function listar(array $filtros, int $limit, int $offset): array
    {
        [$where, $bindings] = self::construirFiltros($filtros);

        $sql = 'SELECT * FROM pp_usuarios' . $where . ' ORDER BY apellido, nombre LIMIT ? OFFSET ?';

        $stmt = Database::connection()->prepare($sql);

        $pos = 1;
        foreach ($bindings as $valor) {
            $stmt->bindValue($pos++, $valor);
        }
        // LIMIT/OFFSET deben ir como enteros (EMULATE_PREPARES está desactivado).
        $stmt->bindValue($pos++, $limit, \PDO::PARAM_INT);
        $stmt->bindValue($pos, $offset, \PDO::PARAM_INT);

        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Cantidad total de usuarios que cumplen los filtros (para paginación).
     *
     * @param array<string, mixed> $filtros q|rol|activo
     */
    public static function contar(array $filtros): int
    {
        [$where, $bindings] = self::construirFiltros($filtros);

        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM pp_usuarios' . $where);
        $stmt->execute($bindings);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Inserta un usuario. Setea creado_en y actualizado_en explícitos (SQLite no
     * tiene ON UPDATE). Devuelve el id generado.
     *
     * @param array<string, mixed> $datos
     */
    public static function crear(array $datos): int
    {
        $ahora = date('Y-m-d H:i:s');

        $stmt = Database::connection()->prepare(
            'INSERT INTO pp_usuarios
                (nombre, apellido, correo, password_hash, rol, debe_cambiar_password, activo, creado_en, actualizado_en)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $datos['nombre'],
            $datos['apellido'],
            $datos['correo'],
            $datos['password_hash'],
            $datos['rol'],
            (int) ($datos['debe_cambiar_password'] ?? 1),
            (int) ($datos['activo'] ?? 1),
            $ahora,
            $ahora,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /**
     * Actualiza los datos editables de un usuario. NO toca la contraseña.
     * Setea actualizado_en explícito.
     *
     * @param array<string, mixed> $datos nombre|apellido|correo|rol|activo
     */
    public static function actualizar(int $id, array $datos): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pp_usuarios
                SET nombre = ?, apellido = ?, correo = ?, rol = ?, activo = ?, actualizado_en = ?
              WHERE id = ?'
        );
        $stmt->execute([
            $datos['nombre'],
            $datos['apellido'],
            $datos['correo'],
            $datos['rol'],
            (int) $datos['activo'],
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
            'UPDATE pp_usuarios SET activo = 0, actualizado_en = ? WHERE id = ?'
        );
        $stmt->execute([date('Y-m-d H:i:s'), $id]);
    }

    /**
     * Cantidad de administradores activos. Puede excluirse un id (para evaluar
     * si una operación dejaría el sistema sin administradores).
     */
    public static function contarAdminsActivos(?int $exceptoId = null): int
    {
        $sql      = "SELECT COUNT(*) FROM pp_usuarios WHERE rol = 'admin' AND activo = 1";
        $bindings = [];

        if ($exceptoId !== null) {
            $sql .= ' AND id <> ?';
            $bindings[] = $exceptoId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($bindings);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Cambia el hash de contraseña y el flag debe_cambiar_password.
     * Setea actualizado_en explícito.
     */
    public static function actualizarPassword(int $id, string $hash, int $debeCambiar): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pp_usuarios SET password_hash = ?, debe_cambiar_password = ?, actualizado_en = ? WHERE id = ?'
        );
        $stmt->execute([$hash, $debeCambiar, date('Y-m-d H:i:s'), $id]);
    }

    /**
     * Construye la cláusula WHERE y sus bindings a partir de los filtros.
     * Filtros soportados: q (texto libre en nombre/apellido/correo), rol, activo.
     *
     * @param array<string, mixed> $filtros
     * @return array{0: string, 1: array<int, mixed>}
     */
    private static function construirFiltros(array $filtros): array
    {
        $condiciones = [];
        $bindings    = [];

        if (isset($filtros['q']) && $filtros['q'] !== '') {
            $condiciones[] = '(nombre LIKE ? OR apellido LIKE ? OR correo LIKE ?)';
            $like = '%' . $filtros['q'] . '%';
            $bindings[] = $like;
            $bindings[] = $like;
            $bindings[] = $like;
        }

        if (isset($filtros['rol']) && $filtros['rol'] !== '') {
            $condiciones[] = 'rol = ?';
            $bindings[]    = $filtros['rol'];
        }

        if (isset($filtros['activo']) && $filtros['activo'] !== '') {
            $condiciones[] = 'activo = ?';
            $bindings[]    = (int) $filtros['activo'];
        }

        $where = $condiciones === [] ? '' : ' WHERE ' . implode(' AND ', $condiciones);

        return [$where, $bindings];
    }
}
