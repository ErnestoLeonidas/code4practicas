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
        $filas = $stmt->fetchAll();

        foreach ($filas as &$fila) {
            $fila['puntaje'] = self::puntajeSemana($fila);
            $fila['porcentaje'] = self::porcentajeSemana($fila);
            $fila['riesgo'] = self::riesgoSemana($fila);
        }
        unset($fila);

        return $filas;
    }

    /**
     * @return array<string, mixed>
     */
    public static function resumenSeguimiento(int $id): array
    {
        $semanas = self::seguimientoPorPractica($id);
        if ($semanas === []) {
            return [
                'cumplimiento_global' => 0,
                'semanas_en_riesgo_alto' => 0,
                'uno_a_uno_realizadas' => 0,
                'retroalimentaciones_entregadas' => 0,
                'semanas_registradas' => 0,
            ];
        }

        $registradas = count(array_filter($semanas, static function (array $semana): bool {
            return !empty($semana['fecha_registro'])
                || (int) ($semana['reunion_1a1'] ?? 0) > 0
                || (int) ($semana['orientaciones_claras'] ?? 0) > 0
                || (int) ($semana['retroalimentacion'] ?? 0) > 0
                || (int) ($semana['evidencia_registrada'] ?? 0) > 0
                || (int) ($semana['disponibilidad_comunicada'] ?? 0) > 0
                || (int) ($semana['ajuste_individual'] ?? 0) > 0
                || (int) ($semana['reflexion_guiada'] ?? 0) > 0
                || (int) ($semana['etica_valores'] ?? 0) > 0
                || !empty(trim((string) ($semana['observaciones'] ?? '')));
        }));

        $cumplimientoGlobal = $registradas === 0 ? 0 : round(array_sum(array_column($semanas, 'porcentaje')) / $registradas, 1);

        return [
            'cumplimiento_global' => $cumplimientoGlobal,
            'semanas_en_riesgo_alto' => count(array_filter($semanas, static function (array $semana): bool {
                return ($semana['riesgo'] ?? 'alto') === 'alto';
            })),
            'uno_a_uno_realizadas' => count(array_filter($semanas, static function (array $semana): bool {
                return (int) ($semana['reunion_1a1'] ?? 0) === 1;
            })),
            'retroalimentaciones_entregadas' => count(array_filter($semanas, static function (array $semana): bool {
                return (int) ($semana['retroalimentacion'] ?? 0) === 1;
            })),
            'semanas_registradas' => $registradas,
        ];
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    public static function actualizarSeguimientoSemana(int $id, int $semana, array $datos, ?int $usuarioId): array
    {
        $campos = [
            'reunion_1a1' => isset($datos['reunion_1a1']) ? (int) $datos['reunion_1a1'] : null,
            'orientaciones_claras' => isset($datos['orientaciones_claras']) ? (int) $datos['orientaciones_claras'] : null,
            'retroalimentacion' => isset($datos['retroalimentacion']) ? (int) $datos['retroalimentacion'] : null,
            'evidencia_registrada' => isset($datos['evidencia_registrada']) ? (int) $datos['evidencia_registrada'] : null,
            'disponibilidad_comunicada' => isset($datos['disponibilidad_comunicada']) ? (int) $datos['disponibilidad_comunicada'] : null,
            'ajuste_individual' => isset($datos['ajuste_individual']) ? (int) $datos['ajuste_individual'] : null,
            'reflexion_guiada' => isset($datos['reflexion_guiada']) ? (int) $datos['reflexion_guiada'] : null,
            'etica_valores' => isset($datos['etica_valores']) ? (int) $datos['etica_valores'] : null,
            'observaciones' => array_key_exists('observaciones', $datos) ? trim((string) $datos['observaciones']) : null,
            'fecha_registro' => array_key_exists('fecha_registro', $datos) ? trim((string) $datos['fecha_registro']) : null,
        ];

        $sets = [];
        $bindings = [];
        foreach ($campos as $campo => $valor) {
            if ($valor === null) {
                continue;
            }
            $sets[] = $campo . ' = ?';
            $bindings[] = $valor;
        }

        if ($sets === []) {
            return self::seguimientoPorPractica($id)[0] ?? [];
        }

        $bindings[] = $id;
        $bindings[] = $semana;
        $stmt = Database::connection()->prepare(
            'UPDATE pp_seguimiento_semanal SET ' . implode(', ', $sets) . ' WHERE practica_id = ? AND semana = ?'
        );
        $stmt->execute($bindings);

        self::registrarBitacora($id, $usuarioId, 'seguimiento_actualizado', 'Se actualizó la semana ' . $semana . '.');
        return self::semanaPorPractica($id, $semana);
    }

    /**
     * @param array<string, mixed> $fila
     */
    private static function puntajeSemana(array $fila): int
    {
        $campos = ['reunion_1a1', 'orientaciones_claras', 'retroalimentacion', 'evidencia_registrada', 'disponibilidad_comunicada', 'ajuste_individual', 'reflexion_guiada', 'etica_valores'];
        $puntaje = 0;
        foreach ($campos as $campo) {
            $puntaje += (int) ($fila[$campo] ?? 0);
        }
        return $puntaje;
    }

    /**
     * @param array<string, mixed> $fila
     */
    private static function porcentajeSemana(array $fila): int
    {
        $puntaje = self::puntajeSemana($fila);
        return $puntaje === 0 ? 0 : (int) round(($puntaje / 8) * 100);
    }

    /**
     * @param array<string, mixed> $fila
     */
    private static function riesgoSemana(array $fila): string
    {
        $porcentaje = self::porcentajeSemana($fila);
        if ($porcentaje >= 85) {
            return 'bajo';
        }
        if ($porcentaje >= 60) {
            return 'medio';
        }
        return 'alto';
    }

    /**
     * @return array<string, mixed>
     */
    private static function semanaPorPractica(int $id, int $semana): array
    {
        $semanas = self::seguimientoPorPractica($id);
        foreach ($semanas as $fila) {
            if ((int) ($fila['semana'] ?? 0) === $semana) {
                return $fila;
            }
        }
        return [];
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
     * @return array<string, mixed>
     */
    public static function resumenEntregas(int $id): array
    {
        $entregas = self::entregasPorPractica($id);
        $notas = [];
        $atrasadas = 0;
        $hoy = new DateTimeImmutable('today');

        foreach ($entregas as $entrega) {
            if ($entrega['nota'] !== null && $entrega['nota'] !== '') {
                $notas[$entrega['tipo']] = (float) $entrega['nota'];
            }
            if ((int) ($entrega['entregado'] ?? 0) !== 1 && !empty($entrega['fecha_limite'])) {
                $fechaLimite = new DateTimeImmutable((string) $entrega['fecha_limite']);
                if ($hoy > $fechaLimite) {
                    $atrasadas++;
                }
            }
        }

        $notaFinal = null;
        if (isset($notas['avance_1'], $notas['avance_2'], $notas['informe_final'])) {
            $notaFinal = round(($notas['avance_1'] * 0.25) + ($notas['avance_2'] * 0.25) + ($notas['informe_final'] * 0.5), 1);
        }

        return [
            'nota_final_ponderada' => $notaFinal,
            'entregas_atrasadas' => $atrasadas,
            'sugerencia_nota' => $atrasadas > 0 ? 1.0 : null,
        ];
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    public static function actualizarEntrega(int $id, string $tipo, array $datos, ?int $usuarioId): array
    {
        $sets = [];
        $bindings = [];

        if (array_key_exists('entregado', $datos)) {
            $sets[] = 'entregado = ?';
            $bindings[] = (int) $datos['entregado'];
        }

        if (array_key_exists('fecha_entrega', $datos)) {
            $fechaEntrega = trim((string) $datos['fecha_entrega']);
            $sets[] = 'fecha_entrega = ?';
            $bindings[] = $fechaEntrega === '' ? null : $fechaEntrega;
        }

        if (array_key_exists('nota', $datos)) {
            $nota = trim((string) $datos['nota']);
            $sets[] = 'nota = ?';
            $bindings[] = $nota === '' ? null : self::normalizarNota($nota);
        }

        if (array_key_exists('retroalimentacion', $datos)) {
            $sets[] = 'retroalimentacion = ?';
            $bindings[] = trim((string) $datos['retroalimentacion']);
        }

        if ($sets === []) {
            return self::entregaPorTipo($id, $tipo);
        }

        $sets[] = 'actualizado_en = ?';
        $bindings[] = date('Y-m-d H:i:s');
        $bindings[] = $id;
        $bindings[] = $tipo;

        $stmt = Database::connection()->prepare(
            'UPDATE pp_entregas SET ' . implode(', ', $sets) . ' WHERE practica_id = ? AND tipo = ?'
        );
        $stmt->execute($bindings);

        self::registrarBitacora($id, $usuarioId, 'entrega_actualizada', 'Se actualizó la entrega ' . $tipo . '.');
        return self::entregaPorTipo($id, $tipo);
    }

    /**
     * @return array<string, mixed>
     */
    private static function entregaPorTipo(int $id, string $tipo): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, practica_id, tipo, fecha_limite, fecha_entrega, entregado, nota, retroalimentacion, creado_en, actualizado_en
             FROM pp_entregas
             WHERE practica_id = ? AND tipo = ?
             LIMIT 1'
        );
        $stmt->execute([$id, $tipo]);
        $fila = $stmt->fetch();
        return is_array($fila) ? $fila : [];
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

    private static function normalizarNota(string $valor): string
    {
        $nota = (float) $valor;
        $nota = max(1.0, min(7.0, $nota));
        return number_format($nota, 1, '.', '');
    }

    private static function registrarBitacora(int $practicaId, ?int $usuarioId, string $evento, string $detalle): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO pp_bitacora (practica_id, usuario_id, evento, detalle, creado_en) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$practicaId, $usuarioId, $evento, $detalle, date('Y-m-d H:i:s')]);
    }
}
