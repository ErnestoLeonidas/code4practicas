<?php

namespace App\Controllers;

use App\Http\Response;
use App\Models\Practica;
use App\Services\Auth;

final class DashboardController
{
    public function index(array $params): void
    {
        $usuario = Auth::usuario();
        $docenteId = null;
        if (($usuario['rol'] ?? null) === 'docente') {
            $docenteId = (int) $usuario['id'];
        }

        Response::json([
            'totales_por_estado' => Practica::dashboardTotalesPorEstado($docenteId),
            'practicas_en_riesgo' => Practica::dashboardPracticasEnRiesgo(14, $docenteId),
            'entregas_proximas' => Practica::dashboardEntregasProximas(7, $docenteId),
            'entregas_atrasadas' => Practica::dashboardEntregasAtrasadas($docenteId),
            'distribucion_por_carrera' => Practica::dashboardDistribucionPorCarrera($docenteId),
            'distribucion_por_semestre' => Practica::dashboardDistribucionPorSemestre($docenteId),
        ]);
    }
}
