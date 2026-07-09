<?php

namespace App\Models;

use App\Database;
use DateTimeImmutable;

/**
 * Acceso a la tabla pp_practicas y datos derivados (seguimiento, entregas, bitácora).
 */
final class Practica
{
    /**
     * @param array<string, mixed> $filtros
     * @return array<int, array<string, mixed>>
     */
    public static function listar(array $filtros, int $limit, int $offset, ?int $docenteId = null): array
    {
        [$where, $bindings] = self::construirFiltros($filtros, $docenteId);

        $sql = 'SELECT p.id, p.estudiante_id, p.empresa_id, p.supervisor_id, p.semestre,
                       p.fecha_inicio, p.fecha_termino, p.estado, p.horas_totales,
                       p.observaciones, p.creado_en, p.actualizado_en,
                       e.nombre AS estudiante_nombre, e.apellido AS estudiante_apellido,
                       emp.nombre AS empresa_nombre,
                       s.nombre AS supervisor_nombre, s.apellido AS supervisor_apellido
                FROM pp_practicas p
                LEFT JOIN pp_estudiantes e ON e.id = p.estudiante_id
                LEFT JOIN pp_empresas emp ON emp.id = p.empresa_id
                LEFT JOIN pp_supervisores s ON s.id = p.supervisor_id'
            . $where
            . ' ORDER BY p.creado_en DESC LIMIT ? OFFSET ?';

        $stmt = Database::connection()->prepare($sql);

        $pos = 1;
        foreach ($bindings as $valor) {
            $stmt->bindValue($pos++, $valor);
        }
        $stmt->bindValue($pos++, $limit, \PDO::PARAM_INT);
        $stmt->bindValue($pos, $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $filtros
     */
    public static function contar(array $filtros, ?int $docenteId = null): int
    {
        [$where, $bindings] = self::construirFiltros($filtros, $docenteId);

        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM pp_practicas p LEFT JOIN pp_estudiantes e ON e.id = p.estudiante_id' . $where);
        $stmt->execute($bindings);
        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function porId(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.id, p.estudiante_id, p.empresa_id, p.supervisor_id, p.semestre,
                    p.fecha_inicio, p.fecha_termino, p.estado, p.horas_totales,
                    p.observaciones, p.creado_en, p.actualizado_en,
                    e.nombre AS estudiante_nombre, e.apellido AS estudiante_apellido,
                    emp.nombre AS empresa_nombre,
                    s.nombre AS supervisor_nombre, s.apellido AS supervisor_apellido
             FROM pp_practicas p
             LEFT JOIN pp_estudiantes e ON e.id = p.estudiante_id
             LEFT JOIN pp_empresas emp ON emp.id = p.empresa_id
             LEFT JOIN pp_supervisores s ON s.id = p.supervisor_id
             WHERE p.id = ?
             LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function crear(array $datos): int
    {
        $ahora = date('Y-m-d H:i:s');
        $stmt = Database::connection()->prepare(
            'INSERT INTO pp_practicas
                (estudiante_id, empresa_id, supervisor_id, semestre, fecha_inicio, fecha_termino, estado, horas_totales, observaciones, creado_en, actualizado_en)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $datos['estudiante_id'],
            $datos['empresa_id'],
            $datos['supervisor_id'],
            $datos['semestre'],
            $datos['fecha_inicio'],
            $datos['fecha_termino'],
            $datos['estado'] ?? 'pendiente',
            $datos['horas_totales'] ?? null,
            $datos['observaciones'] ?? null,
            $ahora,
            $ahora,
        ]);

        $id = (int) Database::connection()->lastInsertId();
        self::generarDatosIniciales($id, $datos['fecha_inicio'], $datos['fecha_termino']);
        self::registrarBitacora($id, $datos['usuario_id'] ?? null, 'practica_creada', 'Se creó la práctica.');
        return $id;
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function actualizar(int $id, array $datos): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pp_practicas
             SET estudiante_id = ?, empresa_id = ?, supervisor_id = ?, semestre = ?,
                 fecha_inicio = ?, fecha_termino = ?, horas_totales = ?, observaciones = ?, actualizado_en = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $datos['estudiante_id'],
            $datos['empresa_id'],
            $datos['supervisor_id'],
            $datos['semestre'],
            $datos['fecha_inicio'],
            $datos['fecha_termino'],
            $datos['horas_totales'] ?? null,
            $datos['observaciones'] ?? null,
            date('Y-m-d H:i:s'),
            $id,
        ]);
    }

    public static function actualizarEstado(int $id, string $estado, ?int $usuarioId): void
    {
        $stmt = Database::connection()->prepare('UPDATE pp_practicas SET estado = ?, actualizado_en = ? WHERE id = ?');
        $stmt->execute([$estado, date('Y-m-d H:i:s'), $id]);
        self::registrarBitacora($id, $usuarioId, 'estado_actualizado', 'Estado cambiado a ' . $estado . '.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function seguimientoPorPractica(int $id): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, practica_id, semana, foco, reunion_1a1, orientaciones_claras, retroalimentacion,
                    evidencia_registrada, disponibilidad_comunicada, ajuste_individual,
                    reflexion_guiada, etica_valores, observaciones, fecha_registro
             FROM pp_seguimiento_semanal
             WHERE practica_id = ?
             ORDER BY semana ASC'
        );
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function entregasPorPractica(int $id): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, practica_id, tipo, fecha_limite, fecha_entrega, entregado, nota, retroalimentacion, creado_en, actualizado_en
             FROM pp_entregas
             WHERE practica_id = ?
             ORDER BY CASE tipo WHEN "avance_1" THEN 1 WHEN "avance_2" THEN 2 ELSE 3 END ASC'
        );
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function bitacoraPorPractica(int $id): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT b.id, b.practica_id, b.usuario_id, b.evento, b.detalle, b.creado_en,
                    u.nombre AS usuario_nombre, u.apellido AS usuario_apellido
             FROM pp_bitacora b
             LEFT JOIN pp_usuarios u ON u.id = b.usuario_id
             WHERE b.practica_id = ?
             ORDER BY b.creado_en DESC, b.id DESC'
        );
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    public static function transicionValida(string $desde, string $hasta): bool
    {
        $permitidas = [
            'pendiente' => ['en_curso', 'abandonada'],
            'en_curso' => ['avance_1', 'abandonada'],
            'avance_1' => ['avance_2', 'abandonada'],
            'avance_2' => ['informe_final', 'abandonada'],
            'informe_final' => ['aprobada', 'reprobada', 'abandonada'],
            'aprobada' => [],
            'reprobada' => [],
            'abandonada' => [],
        ];

        return in_array($hasta, $permitidas[$desde] ?? [], true);
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array{0: string, 1: array<int, mixed>}
     */
    private static function construirFiltros(array $filtros, ?int $docenteId): array
    {
        $condiciones = [];
        $bindings = [];

        if ($docenteId !== null) {
            $condiciones[] = 'e.docente_id = ?';
            $bindings[] = $docenteId;
        }

        if (isset($filtros['estado']) && $filtros['estado'] !== '') {
            $condiciones[] = 'p.estado = ?';
            $bindings[] = $filtros['estado'];
        }

        if (isset($filtros['semestre']) && $filtros['semestre'] !== '') {
            $condiciones[] = 'p.semestre = ?';
            $bindings[] = $filtros['semestre'];
        }

        if (isset($filtros['q']) && $filtros['q'] !== '') {
            $like = '%' . $filtros['q'] . '%';
            $condiciones[] = '(e.nombre LIKE ? OR e.apellido LIKE ? OR emp.nombre LIKE ? OR p.semestre LIKE ?)';
            $bindings[] = $like;
            $bindings[] = $like;
            $bindings[] = $like;
            $bindings[] = $like;
        }

        $where = $condiciones === [] ? '' : ' WHERE ' . implode(' AND ', $condiciones);
        return [$where, $bindings];
    }

    private static function generarDatosIniciales(int $practicaId, string $fechaInicio, ?string $fechaTermino): void
    {
        $focos = [
            'Inducción y expectativas del proceso',
            'Definición de objetivos y alcance',
            'Planificación inicial y herramientas',
            'Primer acercamiento al entorno laboral',
            'Seguimiento del avance técnico',
            'Retroalimentación sobre desempeño',
            'Integración con el equipo',
            'Ajustes de trabajo y aprendizaje',
            'Cierre de tareas y evidencia',
            'Preparación de entregables',
            'Evaluación del proceso y aprendizajes',
            'Cierre y evaluación final',
        ];

        $pdo = Database::connection();
        $stmtSeguimiento = $pdo->prepare(
            'INSERT INTO pp_seguimiento_semanal (practica_id, semana, foco) VALUES (?, ?, ?)'
        );
        for ($semana = 1; $semana <= 12; $semana++) {
            $stmtSeguimiento->execute([$practicaId, $semana, $focos[$semana - 1] ?? 'Seguimiento semanal']);
        }

        $stmtEntregas = $pdo->prepare(
            'INSERT INTO pp_entregas (practica_id, tipo, fecha_limite, entregado, nota, creado_en, actualizado_en) VALUES (?, ?, ?, 0, NULL, ?, ?)'
        );
        $fechaInicioObj = new DateTimeImmutable($fechaInicio);
        $fechaAvance1 = $fechaInicioObj->modify('+35 days')->format('Y-m-d');
        $fechaAvance2 = $fechaInicioObj->modify('+56 days')->format('Y-m-d');
        $fechaInforme = $fechaTermino ?? $fechaInicioObj->modify('+84 days')->format('Y-m-d');
        $ahora = date('Y-m-d H:i:s');

        $stmtEntregas->execute([$practicaId, 'avance_1', $fechaAvance1, $ahora, $ahora]);
        $stmtEntregas->execute([$practicaId, 'avance_2', $fechaAvance2, $ahora, $ahora]);
        $stmtEntregas->execute([$practicaId, 'informe_final', $fechaInforme, $ahora, $ahora]);
    }

    private static function registrarBitacora(int $practicaId, ?int $usuarioId, string $evento, string $detalle): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO pp_bitacora (practica_id, usuario_id, evento, detalle, creado_en) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$practicaId, $usuarioId, $evento, $detalle, date('Y-m-d H:i:s')]);
    }
}
