<?php

namespace App\Controllers;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Models\Empresa;
use App\Models\Supervisor;
use App\Support\Validaciones;

/**
 * CRUD de supervisores (anidados bajo empresa).
 *
 * GET    /api/empresas/{id}/supervisores   — lista activos (admin + docente)
 * POST   /api/empresas/{id}/supervisores   — crear (solo admin)
 * PUT    /api/supervisores/{id}            — actualizar (solo admin)
 * DELETE /api/supervisores/{id}            — borrado lógico (solo admin)
 */
final class SupervisorController
{
    /**
     * GET /api/empresas/{id}/supervisores
     */
    public function index(array $params): void
    {
        $empresaId = (int) ($params['id'] ?? 0);
        $this->obtenerEmpresaOFallar($empresaId);

        $supervisores = Supervisor::porEmpresa($empresaId);

        Response::json([
            'data' => array_map([self::class, 'publico'], $supervisores),
        ]);
    }

    /**
     * POST /api/empresas/{id}/supervisores  (solo admin)
     */
    public function store(array $params): void
    {
        $empresaId = (int) ($params['id'] ?? 0);
        $this->obtenerEmpresaOFallar($empresaId);

        $cuerpo = Request::json();
        $datos  = $this->validarDatos($cuerpo);
        $datos['empresa_id'] = $empresaId;

        $id = Supervisor::crear($datos);

        Response::json(['supervisor' => self::publico(Supervisor::porId($id))], 201);
    }

    /**
     * PUT /api/supervisores/{id}  (solo admin)
     */
    public function update(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $this->obtenerSupervisorOFallar($id);

        $cuerpo = Request::json();
        $datos  = $this->validarDatos($cuerpo);

        Supervisor::actualizar($id, $datos);

        Response::json(['supervisor' => self::publico(Supervisor::porId($id))]);
    }

    /**
     * DELETE /api/supervisores/{id}  (solo admin)
     */
    public function destroy(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $this->obtenerSupervisorOFallar($id);

        Supervisor::desactivar($id);

        Response::json(['ok' => true]);
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    /**
     * Busca una empresa activa; lanza 404 si no existe o está inactiva.
     *
     * @return array<string, mixed>
     */
    private function obtenerEmpresaOFallar(int $id): array
    {
        $empresa = Empresa::porId($id);
        if ($empresa === null || (int) $empresa['activo'] === 0) {
            throw new HttpException(404, 'no_encontrado', 'Empresa no encontrada.');
        }

        return $empresa;
    }

    /**
     * Busca un supervisor activo; lanza 404 si no existe o está inactivo.
     *
     * @return array<string, mixed>
     */
    private function obtenerSupervisorOFallar(int $id): array
    {
        $supervisor = Supervisor::porId($id);
        if ($supervisor === null || (int) $supervisor['activo'] === 0) {
            throw new HttpException(404, 'no_encontrado', 'Supervisor no encontrado.');
        }

        return $supervisor;
    }

    /**
     * Valida y normaliza los campos de un supervisor.
     *
     * @param array<string, mixed> $cuerpo
     * @return array<string, mixed>
     */
    private function validarDatos(array $cuerpo): array
    {
        $nombre   = trim((string) ($cuerpo['nombre'] ?? ''));
        $apellido = trim((string) ($cuerpo['apellido'] ?? ''));

        if ($nombre === '' || $apellido === '') {
            throw new HttpException(422, 'datos_invalidos', 'Nombre y apellido son obligatorios.');
        }

        // Correo (opcional).
        $correo = isset($cuerpo['correo']) ? trim((string) $cuerpo['correo']) : null;
        if ($correo !== null && $correo !== '') {
            if (!Validaciones::emailValido($correo)) {
                throw new HttpException(422, 'correo_invalido', 'El correo electrónico no es válido.');
            }
        } else {
            $correo = null;
        }

        return [
            'nombre'    => $nombre,
            'apellido'  => $apellido,
            'profesion' => isset($cuerpo['profesion']) ? trim((string) $cuerpo['profesion']) ?: null : null,
            'cargo'     => isset($cuerpo['cargo'])     ? trim((string) $cuerpo['cargo'])     ?: null : null,
            'telefono'  => isset($cuerpo['telefono'])  ? trim((string) $cuerpo['telefono'])  ?: null : null,
            'correo'    => $correo,
        ];
    }

    /**
     * Proyección pública de un supervisor.
     *
     * @param array<string, mixed> $s
     * @return array<string, mixed>
     */
    private static function publico(array $s): array
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
