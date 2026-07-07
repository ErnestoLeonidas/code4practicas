<?php

/**
 * Runner de migraciones (CLI).
 *
 * Uso:  php api/migrate.php
 *
 * Aplica en orden los archivos migrations/NNN_*.{driver}.sql que aún no se hayan
 * ejecutado, según el driver configurado (sqlite o mysql). Registra lo aplicado
 * en la tabla pp_migraciones para ser idempotente.
 *
 * En producción (hosting compartido sin CLI garantizada) las migraciones también
 * pueden ejecutarse manualmente en phpMyAdmin usando el archivo .mysql.sql.
 */

require __DIR__ . '/src/autoload.php';

use App\Database;

$driver = Database::driver();
$pdo    = Database::connection();

echo "Driver: {$driver}\n";

// Tabla de control de migraciones.
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS pp_migraciones (' .
    ' archivo VARCHAR(190) NOT NULL,' .
    ' aplicado_en ' . ($driver === 'mysql' ? 'DATETIME' : 'TEXT') . " NOT NULL)"
);

$aplicadas = $pdo->query('SELECT archivo FROM pp_migraciones')
    ->fetchAll(\PDO::FETCH_COLUMN);
$aplicadas = array_flip($aplicadas);

$patron  = __DIR__ . "/migrations/*.{$driver}.sql";
$archivos = glob($patron) ?: [];
sort($archivos);

if ($archivos === []) {
    echo "No se encontraron migraciones para el driver '{$driver}'.\n";
    exit(0);
}

$totalAplicadas = 0;

foreach ($archivos as $ruta) {
    $nombre = basename($ruta);
    if (isset($aplicadas[$nombre])) {
        echo "  = {$nombre} (ya aplicada)\n";
        continue;
    }

    $sql = file_get_contents($ruta);
    if ($sql === false) {
        fwrite(STDERR, "No se pudo leer {$ruta}\n");
        exit(1);
    }

    $sentencias = splitSql($sql);

    $pdo->beginTransaction();
    try {
        foreach ($sentencias as $sentencia) {
            $pdo->exec($sentencia);
        }
        $stmt = $pdo->prepare('INSERT INTO pp_migraciones (archivo, aplicado_en) VALUES (?, ?)');
        $stmt->execute([$nombre, date('Y-m-d H:i:s')]);
        $pdo->commit();
        echo "  + {$nombre} (aplicada)\n";
        $totalAplicadas++;
    } catch (\Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, "Error aplicando {$nombre}: {$e->getMessage()}\n");
        exit(1);
    }
}

echo "Listo. Migraciones nuevas aplicadas: {$totalAplicadas}.\n";

/**
 * Separa un archivo SQL en sentencias individuales.
 * Quita líneas de comentario (--) y separa por ';'.
 *
 * @return string[]
 */
function splitSql(string $sql): array
{
    $lineas = preg_split('/\r\n|\r|\n/', $sql);
    $limpio = [];
    foreach ($lineas as $linea) {
        if (preg_match('/^\s*--/', $linea)) {
            continue; // comentario de línea completa
        }
        $limpio[] = $linea;
    }
    $sqlLimpio = implode("\n", $limpio);

    $partes = array_map('trim', explode(';', $sqlLimpio));
    return array_values(array_filter($partes, static fn ($s) => $s !== ''));
}
