<?php

namespace App\Controllers;

use App\Database;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Models\Carrera;
use App\Models\Estudiante;
use App\Models\Usuario;
use App\Services\Auth;
use App\Support\Validaciones;

/**
 * CRUD de estudiantes.
 *
 * - admin: acceso total.
 * - docente: solo ve/edita sus propios estudiantes (docente_id == usuario activo).
 *
 * GET    /api/estudiantes             — listado paginado con filtros.
 * POST   /api/estudiantes             — crear (solo admin).
 * GET    /api/estudiantes/{id}        — detalle.
 * PUT    /api/estudiantes/{id}        — actualizar.
 * DELETE /api/estudiantes/{id}        — borrado lógico (solo admin).
 */
final class EstudianteController
{
    private const PER_PAGE_DEF = 20;
    private const PER_PAGE_MAX = 100;

    /**
     * GET /api/estudiantes?page=&per_page=&q=&semestre=&carrera_id=&docente_id=
     */
    public function index(array $params): void
    {
        $usuario = Auth::usuario();
        $esAdmin = $usuario !== null && $usuario['rol'] === 'admin';

        $page    = max(1, (int) ($_GET['page'] ?? 1));
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

        $semestre = trim((string) ($_GET['semestre'] ?? ''));
        if ($semestre !== '') {
            $filtros['semestre'] = $semestre;
        }

        $carreraId = (string) ($_GET['carrera_id'] ?? '');
        if ($carreraId !== '') {
            $filtros['carrera_id'] = (int) $carreraId;
        }

        if ($esAdmin) {
            // Admin puede filtrar por cualquier docente_id.
            $docenteId = (string) ($_GET['docente_id'] ?? '');
            if ($docenteId !== '') {
                $filtros['docente_id'] = (int) $docenteId;
            }
        } else {
            // Docente: fuerza filtro por su propio id, ignora el parámetro.
            $filtros['docente_id'] = (int) $usuario['id'];
        }

        $total  = Estudiante::contar($filtros);
        $offset = ($page - 1) * $perPage;
        $filas  = Estudiante::listar($filtros, $perPage, $offset);

        Response::json([
            'data'     => array_map([self::class, 'publico'], $filas),
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => $total,
        ]);
    }

    /**
     * POST /api/estudiantes  (solo admin)
     */
    public function store(array $params): void
    {
        $cuerpo = Request::json();
        $datos  = $this->validarDatos($cuerpo);

        // RUT duplicado.
        if (Estudiante::rutExiste($datos['rut'])) {
            throw new HttpException(409, 'rut_duplicado', 'Ya existe un estudiante con ese RUT.');
        }

        $id = Estudiante::crear($datos);

        Response::json(['estudiante' => self::publico(Estudiante::porId($id))], 201);
    }

    /**
     * GET /api/estudiantes/{id}
     * Docente solo puede ver sus propios estudiantes.
     */
    public function show(array $params): void
    {
        $id         = (int) ($params['id'] ?? 0);
        $estudiante = $this->obtenerOFallar($id);

        $this->verificarAcceso($estudiante);

        Response::json(['estudiante' => self::publico($estudiante)]);
    }

    /**
     * PUT /api/estudiantes/{id}
     * Admin puede editar cualquier campo. Docente solo sus propios estudiantes.
     */
    public function update(array $params): void
    {
        $id         = (int) ($params['id'] ?? 0);
        $estudiante = $this->obtenerOFallar($id);

        $this->verificarAcceso($estudiante);

        $cuerpo = Request::json();
        $datos  = $this->validarDatos($cuerpo, $id);

        // RUT duplicado (excluyendo el propio).
        if (Estudiante::rutExiste($datos['rut'], $id)) {
            throw new HttpException(409, 'rut_duplicado', 'Ya existe un estudiante con ese RUT.');
        }

        Estudiante::actualizar($id, $datos);

        Response::json(['estudiante' => self::publico(Estudiante::porId($id))]);
    }

    /**
     * DELETE /api/estudiantes/{id}  (solo admin — protegido en index.php)
     */
    public function destroy(array $params): void
    {
        $id         = (int) ($params['id'] ?? 0);
        $estudiante = $this->obtenerOFallar($id);

        // Borrado lógico — si ya estaba inactivo igual devuelve ok.
        Estudiante::desactivar($id);

        Response::json(['ok' => true]);
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    /**
     * Busca un estudiante activo por id; lanza 404 si no existe.
     *
     * @return array<string, mixed>
     */
    private function obtenerOFallar(int $id): array
    {
        $estudiante = Estudiante::porId($id);
        if ($estudiante === null || (int) $estudiante['activo'] === 0) {
            throw new HttpException(404, 'no_encontrado', 'Estudiante no encontrado.');
        }

        return $estudiante;
    }

    /**
     * Verifica que el usuario activo tiene derecho a ver/editar el estudiante.
     * Docente solo puede acceder a sus propios estudiantes.
     *
     * @param array<string, mixed> $estudiante
     */
    private function verificarAcceso(array $estudiante): void
    {
        $usuario = Auth::usuario();
        if ($usuario === null) {
            throw new HttpException(401, 'no_autenticado', 'Debes iniciar sesión.');
        }

        if ($usuario['rol'] === 'docente' && (int) $estudiante['docente_id'] !== (int) $usuario['id']) {
            throw new HttpException(403, 'sin_permiso', 'No tienes permiso para acceder a este estudiante.');
        }
    }

    /**
     * Valida y normaliza los campos de un estudiante.
     * $exceptoId excluye al propio estudiante de la verificación de RUT duplicado
     * (no lo verifica aquí, pero normaliza el RUT para la comprobación externa).
     *
     * @param array<string, mixed> $cuerpo
     * @param int|null             $exceptoId
     * @return array<string, mixed>
     */
    private function validarDatos(array $cuerpo, ?int $exceptoId = null): array
    {
        $nombre   = trim((string) ($cuerpo['nombre'] ?? ''));
        $apellido = trim((string) ($cuerpo['apellido'] ?? ''));
        $rut      = trim((string) ($cuerpo['rut'] ?? ''));

        if ($nombre === '' || $apellido === '') {
            throw new HttpException(422, 'datos_invalidos', 'Nombre y apellido son obligatorios.');
        }

        if ($rut === '') {
            throw new HttpException(422, 'datos_invalidos', 'El RUT es obligatorio.');
        }

        if (!Validaciones::rutValido($rut)) {
            throw new HttpException(422, 'rut_invalido', 'El RUT ingresado no es válido.');
        }

        // Normaliza el RUT (sin puntos, con guión), en mayúsculas.
        $rut = strtoupper(str_replace(['.', '-'], '', $rut));
        $rut = substr($rut, 0, -1) . '-' . substr($rut, -1);

        // Semestre.
        $semestre = isset($cuerpo['semestre_ingreso_practica'])
            ? trim((string) $cuerpo['semestre_ingreso_practica'])
            : null;

        if ($semestre !== null && $semestre !== '') {
            if (!preg_match('/^\d{4}-[12]$/', $semestre)) {
                throw new HttpException(422, 'semestre_invalido', 'El semestre debe tener el formato AAAA-1 o AAAA-2.');
            }
        } else {
            $semestre = null;
        }

        // Carrera.
        $carreraId = isset($cuerpo['carrera_id']) && $cuerpo['carrera_id'] !== '' && $cuerpo['carrera_id'] !== null
            ? (int) $cuerpo['carrera_id']
            : null;

        if ($carreraId !== null) {
            $carrera = Carrera::porId($carreraId);
            if ($carrera === null || (int) $carrera['activo'] === 0) {
                throw new HttpException(422, 'carrera_invalida', 'La carrera indicada no existe o no está activa.');
            }
        }

        // Docente.
        $docenteId = isset($cuerpo['docente_id']) && $cuerpo['docente_id'] !== '' && $cuerpo['docente_id'] !== null
            ? (int) $cuerpo['docente_id']
            : null;

        if ($docenteId !== null) {
            $docente = Usuario::porId($docenteId);
            if ($docente === null || (int) $docente['activo'] === 0 || $docente['rol'] !== 'docente') {
                throw new HttpException(422, 'docente_invalido', 'El docente indicado no existe, no está activo o no tiene rol de docente.');
            }
        }

        return [
            'nombre'                    => $nombre,
            'apellido'                  => $apellido,
            'rut'                       => $rut,
            'correo_duoc'               => isset($cuerpo['correo_duoc']) ? trim((string) $cuerpo['correo_duoc']) ?: null : null,
            'telefono'                  => isset($cuerpo['telefono'])    ? trim((string) $cuerpo['telefono'])    ?: null : null,
            'carrera_id'                => $carreraId,
            'semestre_ingreso_practica' => $semestre,
            'docente_id'                => $docenteId,
        ];
    }

    /**
     * Proyección pública de un estudiante.
     *
     * @param array<string, mixed> $e
     * @return array<string, mixed>
     */
    private static function publico(array $e): array
    {
        return [
            'id'                        => (int) $e['id'],
            'nombre'                    => $e['nombre'],
            'apellido'                  => $e['apellido'],
            'rut'                       => $e['rut'],
            'correo_duoc'               => $e['correo_duoc'],
            'telefono'                  => $e['telefono'],
            'carrera_id'                => $e['carrera_id'] !== null ? (int) $e['carrera_id'] : null,
            'carrera_nombre'            => $e['carrera_nombre'],
            'semestre_ingreso_practica' => $e['semestre_ingreso_practica'],
            'docente_id'                => $e['docente_id'] !== null ? (int) $e['docente_id'] : null,
            'docente_nombre'            => $e['docente_nombre'],
            'activo'                    => (bool) (int) $e['activo'],
            'creado_en'                 => $e['creado_en'],
            'actualizado_en'            => $e['actualizado_en'],
        ];
    }
}
