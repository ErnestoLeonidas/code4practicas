<?php

/**
 * Seed de carreras representativas de Duoc UC.  (CLI)
 *
 * Uso:
 *   php api/seed_carreras.php
 *
 * Idempotente: no duplica una carrera si ya existe con el mismo nombre.
 * Al finalizar imprime cuántas se insertaron y cuántas ya existían.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo se ejecuta desde la línea de comandos.\n");
}

require __DIR__ . '/src/autoload.php';

use App\Database;

$pdo = Database::connection();

// Carreras agrupadas por escuela.
$carreras = [
    'Escuela de Informática y Telecomunicaciones' => [
        'Ingeniería en Informática',
        'Técnico en Programación y Análisis de Sistemas',
        'Ingeniería en Conectividad y Redes',
        'Administración de Infraestructura Tecnológica',
    ],
    'Escuela de Administración y Negocios' => [
        'Administración de Empresas',
        'Contador General',
        'Comercio Internacional',
    ],
    'Escuela de Salud' => [
        'Enfermería',
        'Técnico en Enfermería de Nivel Superior',
        'Nutrición y Dietética',
    ],
    'Escuela de Ingeniería' => [
        'Construcción Civil',
        'Electricidad',
        'Mecánica Automotriz',
    ],
];

$insertadas  = 0;
$existentes  = 0;

foreach ($carreras as $escuela => $nombres) {
    foreach ($nombres as $nombre) {
        // Verificar si ya existe (por nombre, case-sensitive en SQLite por defecto).
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM pp_carreras WHERE nombre = ?');
        $stmt->execute([$nombre]);
        $existe = (int) $stmt->fetchColumn() > 0;

        if ($existe) {
            echo "  [ya existe] {$nombre}\n";
            $existentes++;
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO pp_carreras (nombre, escuela, activo) VALUES (?, ?, 1)'
            );
            $stmt->execute([$nombre, $escuela]);
            echo "  [insertada] {$nombre} ({$escuela})\n";
            $insertadas++;
        }
    }
}

echo "\n";
echo "Resumen: {$insertadas} insertada(s), {$existentes} ya existían.\n";
exit(0);
