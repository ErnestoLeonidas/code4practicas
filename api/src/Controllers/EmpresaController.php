<?php

namespace App\Controllers;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Models\Empresa;
use App\Models\Supervisor;
use App\Support\Validaciones;

/**
 * CRUD de empresas.
 *
 * GET    /api/empresas              — listado paginado (admin + docente)
 * POST   /api/empresas              — crear (solo admin)
 * GET    /api/empresas/{id}         — detalle + supervisores activos (admin + docente)
 * PUT    /api/empresas/{id}         — actualizar (solo admin)
 * DELETE /api/empresas/{id}         — borrado lógico (solo admin)
 */
final class EmpresaController
{
    private const PER_PAGE_DEF = 20;
    private const PER_PAGE_MAX = 100;

    /**
     * GET /api/empresas?page=&per_page=&q=&ciudad=
     */
    public function index(array $params): void
    {
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

        $ciudad = trim((string) ($_GET['ciudad'] ?? ''));
        if ($ciudad !== '') {
            $filtros['ciudad'] = $ciudad;
        }

        $total  = Empresa::contar($filtros);
        $offset = ($page - 1) * $perPage;
        $filas  = Empresa::listar($filtros, $perPage, $offset);

        Response::json([
            'data'     => array_map([self::class, 'publico'], $filas),
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => $total,
        ]);
    }

    /**
     * POST /api/empresas  (solo admin)
     */
    public function store(array $params): void
    {
        $cuerpo = Request::json();
        $datos  = $this->validarDatos($cuerpo);

        $id = Empresa::crear($datos);

        Response::json(['empresa' => self::publico(Empresa::porId($id))], 201);
    }

    /**
     * GET /api/empresas/{id}
     * Incluye supervisores activos de la empresa.
     */
    public function show(array $params): void
    {
        $id      = (int) ($params['id'] ?? 0);
        $empresa = $this->obtenerOFallar($id);

        $supervisores = Supervisor::porEmpresa($id);

        $respuesta               = self::publico($empresa);
        $respuesta['supervisores'] = array_map([self::class, 'publicoSupervisor'], $supervisores);

        Response::json(['empresa' => $respuesta]);
    }

    /**
     * PUT /api/empresas/{id}  (solo admin)
     */
    public function update(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $this->obtenerOFallar($id);

        $cuerpo = Request::json();
        $datos  = $this->validarDatos($cuerpo);

        Empresa::actualizar($id, $datos);

        Response::json(['empresa' => self::publico(Empresa::porId($id))]);
    }

    /**
     * DELETE /api/empresas/{id}  (solo admin)
     * Si tiene prácticas activas → 409. Si no, borrado lógico.
     */
    public function destroy(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $this->obtenerOFallar($id);

        if (Empresa::tienePracticasActivas($id)) {
            throw new HttpException(409, 'empresa_en_uso', 'La empresa tiene prácticas activas y no puede desactivarse.');
        }

        Empresa::desactivar($id);

        Response::json(['ok' => true]);
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    /**
     * Busca una empresa activa por id; lanza 404 si no existe o está inactiva.
     *
     * @return array<string, mixed>
     */
    private function obtenerOFallar(int $id): array
    {
        $empresa = Empresa::porId($id);
        if ($empresa === null) {
            throw new HttpException(404, 'no_encontrado', 'Empresa no encontrada.');
        }

        return $empresa;
    }

    /**
     * Valida y normaliza los campos de una empresa.
     *
     * @param array<string, mixed> $cuerpo
     * @return array<string, mixed>
     */
    private function validarDatos(array $cuerpo): array
    {
        $nombre = trim((string) ($cuerpo['nombre'] ?? ''));
        if ($nombre === '') {
            throw new HttpException(422, 'datos_invalidos', 'El nombre de la empresa es obligatorio.');
        }
        if (mb_strlen($nombre) > 200) {
            throw new HttpException(422, 'datos_invalidos', 'El nombre no puede superar los 200 caracteres.');
        }

        // RUT empresa (opcional).
        $rutEmpresa = isset($cuerpo['rut_empresa']) ? trim((string) $cuerpo['rut_empresa']) : null;
        if ($rutEmpresa !== null && $rutEmpresa !== '') {
            if (!Validaciones::rutValido($rutEmpresa)) {
                throw new HttpException(422, 'rut_invalido', 'El RUT de la empresa no es válido.');
            }
        } else {
            $rutEmpresa = null;
        }

        // Sitio web (opcional).
        $sitioWeb = isset($cuerpo['sitio_web']) ? trim((string) $cuerpo['sitio_web']) : null;
        if ($sitioWeb !== null && $sitioWeb !== '') {
            if (filter_var($sitioWeb, FILTER_VALIDATE_URL) === false) {
                throw new HttpException(422, 'url_invalida', 'El sitio web no es una URL válida.');
            }
        } else {
            $sitioWeb = null;
        }

        return [
            'nombre'      => $nombre,
            'rut_empresa' => $rutEmpresa,
            'giro'        => isset($cuerpo['giro'])      ? trim((string) $cuerpo['giro'])      ?: null : null,
            'direccion'   => isset($cuerpo['direccion']) ? trim((string) $cuerpo['direccion']) ?: null : null,
            'ciudad'      => isset($cuerpo['ciudad'])    ? trim((string) $cuerpo['ciudad'])    ?: null : null,
            'telefono'    => isset($cuerpo['telefono'])  ? trim((string) $cuerpo['telefono'])  ?: null : null,
            'sitio_web'   => $sitioWeb,
        ];
    }

    /**
     * Proyección pública de una empresa.
     *
     * @param array<string, mixed> $e
     * @return array<string, mixed>
     */
    private static function publico(array $e): array
    {
        return [
            'id'               => (int) $e['id'],
            'nombre'           => $e['nombre'],
            'rut_empresa'      => $e['rut_empresa'],
            'giro'             => $e['giro'],
            'direccion'        => $e['direccion'],
            'ciudad'           => $e['ciudad'],
            'telefono'         => $e['telefono'],
            'sitio_web'        => $e['sitio_web'],
            'activo'           => (bool) (int) $e['activo'],
            'supervisor_count' => (int) $e['supervisor_count'],
            'creado_en'        => $e['creado_en'],
        ];
    }

    /**
     * Proyección pública de un supervisor (usada en show).
     *
     * @param array<string, mixed> $s
     * @return array<string, mixed>
     */
    private static function publicoSupervisor(array $s): array
    {
        return [
            'id'         => (int) $s['id'],
            'empresa_id' => (int) $s['empresa_id'],
            'nombre'     => $s['nombre'],
            'apellido'   => $s['apellido'],
            'profesion'  => $s['profesion'],
            'cargo'      => $s['cargo'],
            'telefono'   => $s['telefono'],
            'correo'     => $s['correo'],
            'activo'     => (bool) (int) $s['activo'],
            'creado_en'  => $s['creado_en'],
        ];
    }
}
