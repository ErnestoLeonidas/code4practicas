<?php

namespace App\Controllers;

use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Models\Carrera;

/**
 * CRUD de carreras (solo admin).
 *
 * GET    /api/carreras           — lista activas.
 * POST   /api/carreras           — crea carrera.
 * PUT    /api/carreras/{id}      — actualiza carrera.
 * DELETE /api/carreras/{id}      — borrado lógico (rechaza si tiene estudiantes activos).
 */
final class CarreraController
{
    private const NOMBRE_MAX = 150;

    /**
     * GET /api/carreras — lista todas las carreras activas (sin paginación).
     */
    public function index(array $params): void
    {
        $carreras = Carrera::listar();

        Response::json(['data' => array_map([self::class, 'publico'], $carreras)]);
    }

    /**
     * POST /api/carreras  { nombre, escuela? }
     */
    public function store(array $params): void
    {
        $datos = $this->validarDatos(Request::json());

        $id = Carrera::crear($datos);

        Response::json(['carrera' => self::publico(Carrera::porId($id))], 201);
    }

    /**
     * PUT /api/carreras/{id}  { nombre, escuela? }
     */
    public function update(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        $carrera = Carrera::porId($id);
        if ($carrera === null || (int) $carrera['activo'] === 0) {
            throw new HttpException(404, 'no_encontrado', 'Carrera no encontrada.');
        }

        $datos = $this->validarDatos(Request::json());

        Carrera::actualizar($id, $datos);

        Response::json(['carrera' => self::publico(Carrera::porId($id))]);
    }

    /**
     * DELETE /api/carreras/{id} — borrado lógico.
     * Rechaza si la carrera tiene estudiantes activos (409 carrera_en_uso).
     */
    public function destroy(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        $carrera = Carrera::porId($id);
        if ($carrera === null || (int) $carrera['activo'] === 0) {
            throw new HttpException(404, 'no_encontrado', 'Carrera no encontrada.');
        }

        // Verifica si hay estudiantes activos en esta carrera.
        $stmt = \App\Database::connection()->prepare(
            'SELECT COUNT(*) FROM pp_estudiantes WHERE carrera_id = ? AND activo = 1'
        );
        $stmt->execute([$id]);
        $enUso = (int) $stmt->fetchColumn() > 0;

        if ($enUso) {
            throw new HttpException(409, 'carrera_en_uso', 'No se puede eliminar: hay estudiantes activos en esta carrera.');
        }

        Carrera::desactivar($id);

        Response::json(['ok' => true]);
    }

    /**
     * Valida y normaliza los campos comunes de crear/actualizar.
     * Lanza HttpException si la validación falla.
     *
     * @param array<string, mixed> $cuerpo
     * @return array{nombre: string, escuela: string|null}
     */
    private function validarDatos(array $cuerpo): array
    {
        $nombre  = trim((string) ($cuerpo['nombre'] ?? ''));
        $escuela = isset($cuerpo['escuela']) ? trim((string) $cuerpo['escuela']) : null;

        if ($nombre === '') {
            throw new HttpException(422, 'datos_invalidos', 'El nombre de la carrera es obligatorio.');
        }

        if (mb_strlen($nombre) > self::NOMBRE_MAX) {
            throw new HttpException(422, 'datos_invalidos', 'El nombre no puede superar ' . self::NOMBRE_MAX . ' caracteres.');
        }

        return [
            'nombre'  => $nombre,
            'escuela' => ($escuela === '' || $escuela === null) ? null : $escuela,
        ];
    }

    /**
     * Proyección pública de una carrera (castea tipos).
     *
     * @param array<string, mixed> $c
     * @return array<string, mixed>
     */
    private static function publico(array $c): array
    {
        return [
            'id'      => (int) $c['id'],
            'nombre'  => $c['nombre'],
            'escuela' => $c['escuela'],
            'activo'  => (bool) (int) $c['activo'],
        ];
    }
}
