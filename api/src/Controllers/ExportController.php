<?php

namespace App\Controllers;

use App\Http\HttpException;
use App\Http\Response;
use App\Models\Estudiante;
use App\Models\Practica;
use App\Services\Auth;

final class ExportController
{
    public function estudiantes(array $params): void
    {
        $usuario = Auth::usuario();
        $docenteId = ($usuario['rol'] ?? null) === 'docente' ? (int) $usuario['id'] : null;

        $rows = Estudiante::exportar($docenteId);

        Response::csv(
            'estudiantes.csv',
            ['ID', 'Nombre', 'Apellido', 'RUT', 'Correo Duoc', 'Telefono', 'Carrera', 'Semestre ingreso', 'Docente', 'Activo'],
            $rows
        );
    }

    public function practicas(array $params): void
    {
        $usuario = Auth::usuario();
        $docenteId = ($usuario['rol'] ?? null) === 'docente' ? (int) $usuario['id'] : null;

        $rows = Practica::exportarPracticas($docenteId);

        Response::csv(
            'practicas.csv',
            ['ID', 'Estudiante', 'Empresa', 'Supervisor', 'Semestre', 'Estado', 'Fecha inicio', 'Fecha termino', 'Horas totales', 'Avance 1', 'Avance 2', 'Informe final', 'Nota final', 'Entregas atrasadas'],
            $rows
        );
    }

    public function seguimientoPractica(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $practica = Practica::porId($id);
        if ($practica === null) {
            throw new HttpException(404, 'no_encontrado', 'Práctica no encontrada.');
        }

        $this->verificarAcceso($practica);
        $rows = Practica::exportarSeguimientoPractica($id);

        Response::csv(
            'practica-' . $id . '-seguimiento.csv',
            ['Práctica ID', 'Semana', 'Foco', 'Fecha registro', '1:1', 'Orientaciones claras', 'Retroalimentación', 'Evidencia', 'Disponibilidad', 'Ajuste', 'Reflexión', 'Ética', 'Puntaje', 'Porcentaje', 'Riesgo', 'Observaciones'],
            $rows
        );
    }

    /**
     * @param array<string, mixed> $practica
     */
    private function verificarAcceso(array $practica): void
    {
        $usuario = Auth::usuario();
        if ($usuario === null) {
            throw new HttpException(401, 'no_autenticado', 'Debes iniciar sesión.');
        }

        if (($usuario['rol'] ?? null) !== 'docente') {
            return;
        }

        $docenteId = Practica::docenteIdDePractica((int) $practica['id']);
        if ($docenteId !== (int) $usuario['id']) {
            throw new HttpException(403, 'sin_permiso', 'No tienes permiso para exportar esta práctica.');
        }
    }
}
