<?php

namespace App\Controllers;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Models\Practica;
use App\Services\Auth;
use DateTimeImmutable;

/**
 * CRUD de prácticas y transición de estados.
 */
final class PracticaController
{
    private const PER_PAGE_DEF = 20;
    private const PER_PAGE_MAX = 100;

    /**
     * GET /api/practicas?page=&per_page=&q=&estado=&semestre=
     */
    public function index(array $params): void
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = (int) ($_GET['per_page'] ?? self::PER_PAGE_DEF);
        if ($perPage < 1) {
            $perPage = self::PER_PAGE_DEF;
        }
        $perPage = min($perPage, self::PER_PAGE_MAX);

        $filtros = [];

        $q = trim((string) ($_GET['q'] ?? ''));
        if ($q !== '') {
            $filtros['q'] = $q;
        }
        $estado = trim((string) ($_GET['estado'] ?? ''));
        if ($estado !== '') {
            $filtros['estado'] = $estado;
        }
        $semestre = trim((string) ($_GET['semestre'] ?? ''));
        if ($semestre !== '') {
            $filtros['semestre'] = $semestre;
        }

        $usuario = Auth::usuario();
        $docenteId = null;
        if (($usuario['rol'] ?? null) === 'docente') {
            $docenteId = (int) $usuario['id'];
        }

        $total = Practica::contar($filtros, $docenteId);
        $offset = ($page - 1) * $perPage;
        $filas = Practica::listar($filtros, $perPage, $offset, $docenteId);

        Response::json([
            'data' => array_map([self::class, 'publico'], $filas),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
        ]);
    }

    /**
     * POST /api/practicas
     */
    public function store(array $params): void
    {
        $cuerpo = Request::json();
        $datos = $this->validarDatos($cuerpo);

        $usuario = Auth::usuario();
        if (($usuario['rol'] ?? null) === 'docente') {
            $this->validarPermisoDocente((int) $datos['estudiante_id']);
        }

        $datos['usuario_id'] = $usuario['id'] ?? null;
        $id = Practica::crear($datos);

        Response::json(['practica' => self::publico(Practica::porId($id))], 201);
    }

    /**
     * GET /api/practicas/{id}
     */
    public function show(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $practica = $this->obtenerOFallar($id);
        $this->verificarAcceso($practica);

        Response::json([
            'practica' => [
                ...self::publico($practica),
                'seguimiento' => Practica::seguimientoPorPractica($id),
                'resumen' => Practica::resumenSeguimiento($id),
                'entregas' => Practica::entregasPorPractica($id),
                'resumen_entregas' => Practica::resumenEntregas($id),
                'bitacora' => Practica::bitacoraPorPractica($id),
            ],
        ]);
    }

    /**
     * PUT /api/practicas/{id}
     */
    public function update(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $practica = $this->obtenerOFallar($id);
        $this->verificarAcceso($practica);

        $cuerpo = Request::json();
        $datos = $this->validarDatos($cuerpo);

        $usuario = Auth::usuario();
        if (($usuario['rol'] ?? null) === 'docente') {
            $this->validarPermisoDocente((int) $datos['estudiante_id']);
        }

        Practica::actualizar($id, $datos);
        Response::json(['practica' => self::publico(Practica::porId($id))]);
    }

    /**
     * PATCH /api/practicas/{id}/estado
     */
    public function estado(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $practica = $this->obtenerOFallar($id);
        $this->verificarAcceso($practica);

        $cuerpo = Request::json();
        $siguiente = trim((string) ($cuerpo['estado'] ?? ''));
        if ($siguiente === '') {
            throw new HttpException(422, 'datos_invalidos', 'El estado es obligatorio.');
        }

        $estadoActual = (string) $practica['estado'];
        if (!Practica::transicionValida($estadoActual, $siguiente)) {
            throw new HttpException(422, 'estado_invalido', 'La transición de estado no es válida.');
        }

        $usuario = Auth::usuario();
        Practica::actualizarEstado($id, $siguiente, $usuario ? (int) $usuario['id'] : null);
        Response::json(['practica' => self::publico(Practica::porId($id))]);
    }

    /**
     * GET /api/practicas/{id}/seguimiento
     */
    public function seguimiento(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $practica = $this->obtenerOFallar($id);
        $this->verificarAcceso($practica);

        Response::json([
            'semanas' => Practica::seguimientoPorPractica($id),
            'resumen' => Practica::resumenSeguimiento($id),
        ]);
    }

    /**
     * PUT /api/practicas/{id}/seguimiento/{semana}
     */
    public function actualizarSeguimiento(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $practica = $this->obtenerOFallar($id);
        $this->verificarAcceso($practica);

        $semana = (int) ($params['semana'] ?? 0);
        if ($semana < 1 || $semana > 12) {
            throw new HttpException(422, 'datos_invalidos', 'La semana debe estar entre 1 y 12.');
        }

        $cuerpo = Request::json();
        $usuario = Auth::usuario();
        $semanaActualizada = Practica::actualizarSeguimientoSemana($id, $semana, $cuerpo, $usuario ? (int) $usuario['id'] : null);

        Response::json([
            'semana' => $semanaActualizada,
            'resumen' => Practica::resumenSeguimiento($id),
        ]);
    }

    /**
     * PUT /api/practicas/{id}/entregas/{tipo}
     */
    public function actualizarEntrega(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $practica = $this->obtenerOFallar($id);
        $this->verificarAcceso($practica);

        $tipo = trim((string) ($params['tipo'] ?? ''));
        if (!in_array($tipo, ['avance_1', 'avance_2', 'informe_final'], true)) {
            throw new HttpException(422, 'datos_invalidos', 'El tipo de entrega es inválido.');
        }

        $cuerpo = Request::json();
        if (isset($cuerpo['nota']) && $cuerpo['nota'] !== '') {
            $nota = (float) $cuerpo['nota'];
            if ($nota < 1.0 || $nota > 7.0) {
                throw new HttpException(422, 'datos_invalidos', 'La nota debe estar entre 1.0 y 7.0.');
            }
        }

        $usuario = Auth::usuario();
        $entrega = Practica::actualizarEntrega($id, $tipo, $cuerpo, $usuario ? (int) $usuario['id'] : null);

        if ($tipo === 'informe_final' && array_key_exists('nota', $cuerpo) && $cuerpo['nota'] !== '' && (string) $practica['estado'] === 'informe_final') {
            $estadoSiguiente = ((float) $cuerpo['nota']) >= 4.0 ? 'aprobada' : 'reprobada';
            Practica::actualizarEstado($id, $estadoSiguiente, $usuario ? (int) $usuario['id'] : null);
        }

        Response::json([
            'entrega' => $entrega,
            'resumen_entregas' => Practica::resumenEntregas($id),
        ]);
    }

    private function obtenerOFallar(int $id): array
    {
        $practica = Practica::porId($id);
        if ($practica === null) {
            throw new HttpException(404, 'no_encontrado', 'Práctica no encontrada.');
        }
        return $practica;
    }

    private function verificarAcceso(array $practica): void
    {
        $usuario = Auth::usuario();
        if ($usuario === null) {
            throw new HttpException(401, 'no_autenticado', 'Debes iniciar sesión.');
        }
        if (($usuario['rol'] ?? null) === 'docente') {
            $estudianteId = (int) ($practica['estudiante_id'] ?? 0);
            $pdo = \App\Database::connection();
            $stmt = $pdo->prepare('SELECT docente_id FROM pp_estudiantes WHERE id = ? LIMIT 1');
            $stmt->execute([$estudianteId]);
            $docenteAsignado = (int) $stmt->fetchColumn();
            if ($docenteAsignado !== (int) $usuario['id']) {
                throw new HttpException(403, 'sin_permiso', 'No tienes permiso para acceder a esta práctica.');
            }
        }
    }

    private function validarPermisoDocente(int $estudianteId): void
    {
        $usuario = Auth::usuario();
        if ($usuario === null) {
            throw new HttpException(401, 'no_autenticado', 'Debes iniciar sesión.');
        }
        if (($usuario['rol'] ?? null) !== 'docente') {
            return;
        }
        $pdo = \App\Database::connection();
        $stmt = $pdo->prepare('SELECT docente_id FROM pp_estudiantes WHERE id = ? LIMIT 1');
        $stmt->execute([$estudianteId]);
        $docenteAsignado = (int) $stmt->fetchColumn();
        if ($docenteAsignado !== (int) $usuario['id']) {
            throw new HttpException(403, 'sin_permiso', 'Solo puedes vincular prácticas a tus estudiantes asignados.');
        }
    }

    /**
     * @param array<string, mixed> $cuerpo
     * @return array<string, mixed>
     */
    private function validarDatos(array $cuerpo): array
    {
        $estudianteId = (int) ($cuerpo['estudiante_id'] ?? 0);
        if ($estudianteId <= 0) {
            throw new HttpException(422, 'datos_invalidos', 'Debes seleccionar un estudiante.');
        }
        $empresaId = (int) ($cuerpo['empresa_id'] ?? 0);
        if ($empresaId <= 0) {
            throw new HttpException(422, 'datos_invalidos', 'Debes seleccionar una empresa.');
        }

        $supervisorId = isset($cuerpo['supervisor_id']) ? (int) $cuerpo['supervisor_id'] : null;
        if ($supervisorId !== null && $supervisorId <= 0) {
            $supervisorId = null;
        }

        $this->validarReferencias($estudianteId, $empresaId, $supervisorId);

        $semestre = trim((string) ($cuerpo['semestre'] ?? ''));
        if (!preg_match('/^\d{4}-(1|2)$/', $semestre)) {
            throw new HttpException(422, 'semestre_invalido', 'El semestre debe tener formato AAAA-1 o AAAA-2.');
        }

        $fechaInicio = trim((string) ($cuerpo['fecha_inicio'] ?? ''));
        $fechaTermino = trim((string) ($cuerpo['fecha_termino'] ?? ''));
        $this->validarFecha($fechaInicio, 'La fecha de inicio es obligatoria.');
        $this->validarFecha($fechaTermino, 'La fecha de término es obligatoria.');

        $inicioObj = new DateTimeImmutable($fechaInicio);
        $terminoObj = new DateTimeImmutable($fechaTermino);
        if ($terminoObj < $inicioObj) {
            throw new HttpException(422, 'datos_invalidos', 'La fecha de término no puede ser anterior a la fecha de inicio.');
        }

        $horasTotales = isset($cuerpo['horas_totales']) ? (int) $cuerpo['horas_totales'] : null;
        if ($horasTotales !== null && $horasTotales < 0) {
            throw new HttpException(422, 'datos_invalidos', 'Las horas totales no pueden ser negativas.');
        }

        return [
            'estudiante_id' => $estudianteId,
            'empresa_id' => $empresaId,
            'supervisor_id' => $supervisorId,
            'semestre' => $semestre,
            'fecha_inicio' => $fechaInicio,
            'fecha_termino' => $fechaTermino,
            'horas_totales' => $horasTotales,
            'observaciones' => isset($cuerpo['observaciones']) ? trim((string) $cuerpo['observaciones']) : null,
        ];
    }

    private function validarReferencias(int $estudianteId, int $empresaId, ?int $supervisorId): void
    {
        $pdo = \App\Database::connection();

        $stmt = $pdo->prepare('SELECT id FROM pp_estudiantes WHERE id = ? AND activo = 1 LIMIT 1');
        $stmt->execute([$estudianteId]);
        if ($stmt->fetchColumn() === false) {
            throw new HttpException(422, 'estudiante_invalido', 'El estudiante no existe o está inactivo.');
        }

        $stmt = $pdo->prepare('SELECT id FROM pp_empresas WHERE id = ? LIMIT 1');
        $stmt->execute([$empresaId]);
        if ($stmt->fetchColumn() === false) {
            throw new HttpException(422, 'empresa_invalida', 'La empresa no existe.');
        }

        if ($supervisorId !== null) {
            $stmt = $pdo->prepare('SELECT id FROM pp_supervisores WHERE id = ? AND empresa_id = ? LIMIT 1');
            $stmt->execute([$supervisorId, $empresaId]);
            if ($stmt->fetchColumn() === false) {
                throw new HttpException(422, 'supervisor_invalido', 'El supervisor no pertenece a la empresa seleccionada.');
            }
        }
    }

    private function validarFecha(string $valor, string $mensaje): void
    {
        if ($valor === '') {
            throw new HttpException(422, 'datos_invalidos', $mensaje);
        }
        $fecha = DateTimeImmutable::createFromFormat('Y-m-d', $valor);
        if ($fecha === false || $fecha->format('Y-m-d') !== $valor) {
            throw new HttpException(422, 'datos_invalidos', 'La fecha debe venir en formato YYYY-MM-DD.');
        }
    }

    /**
     * @param array<string, mixed> $p
     * @return array<string, mixed>
     */
    private static function publico(array $p): array
    {
        return [
            'id' => (int) $p['id'],
            'estudiante_id' => (int) $p['estudiante_id'],
            'empresa_id' => (int) $p['empresa_id'],
            'supervisor_id' => $p['supervisor_id'] !== null ? (int) $p['supervisor_id'] : null,
            'semestre' => $p['semestre'],
            'fecha_inicio' => $p['fecha_inicio'],
            'fecha_termino' => $p['fecha_termino'],
            'estado' => $p['estado'],
            'horas_totales' => $p['horas_totales'] !== null ? (int) $p['horas_totales'] : null,
            'observaciones' => $p['observaciones'],
            'estudiante_nombre' => $p['estudiante_nombre'],
            'estudiante_apellido' => $p['estudiante_apellido'],
            'empresa_nombre' => $p['empresa_nombre'],
            'supervisor_nombre' => $p['supervisor_nombre'],
            'supervisor_apellido' => $p['supervisor_apellido'],
            'creado_en' => $p['creado_en'],
            'actualizado_en' => $p['actualizado_en'],
        ];
    }
}
