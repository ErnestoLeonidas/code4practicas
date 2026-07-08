<?php

/**
 * Crea (o regenera) el usuario administrador inicial.  (CLI)
 *
 * Uso:
 *   php api/seed_admin.php [correo] [--force]
 *
 * - Correo por defecto: admin@profesor.duoc.cl
 * - Si ya existe un administrador y NO se pasa --force, aborta (exit 1).
 * - Con --force regenera la contraseña del administrador (correo indicado).
 *
 * La contraseña se genera al azar, se guarda solo su hash y se marca
 * debe_cambiar_password = 1. Se imprime UNA sola vez: guárdala.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo se ejecuta desde la línea de comandos.\n");
}

require __DIR__ . '/src/autoload.php';

use App\Config;
use App\Database;
use App\Services\Password;

// --- Parseo de argumentos ---
$force  = false;
$correo = null;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--force') {
        $force = true;
    } elseif (!str_starts_with($arg, '--') && $correo === null) {
        $correo = $arg;
    }
}
$correo = strtolower(trim($correo ?? 'admin@profesor.duoc.cl'));

// --- Advertencia de dominio (no bloquea) ---
if (!dominioPermitido($correo)) {
    fwrite(STDERR, "ADVERTENCIA: el dominio de '{$correo}' no está en 'dominios_permitidos'.\n");
    fwrite(STDERR, "  No podrás iniciar sesión con este correo hasta agregar su dominio en la config.\n\n");
}

$pdo = Database::connection();

// ¿Ya hay algún administrador?
$hayAdmin = (int) $pdo->query("SELECT COUNT(*) FROM pp_usuarios WHERE rol = 'admin'")->fetchColumn() > 0;

if ($hayAdmin && !$force) {
    fwrite(STDERR, "Ya existe un usuario administrador.\n");
    fwrite(STDERR, "Si quieres regenerar su contraseña, vuelve a ejecutar con --force:\n");
    fwrite(STDERR, "  php api/seed_admin.php {$correo} --force\n");
    exit(1);
}

// --- Generación de credenciales ---
$password = Password::generar(14);
$hash     = Password::hash($password);
$ahora    = date('Y-m-d H:i:s');

// ¿Existe un usuario con este correo? -> actualizar; si no, insertar.
$stmt = $pdo->prepare('SELECT id FROM pp_usuarios WHERE correo = ? LIMIT 1');
$stmt->execute([$correo]);
$id = $stmt->fetchColumn();

if ($id !== false) {
    $stmt = $pdo->prepare(
        'UPDATE pp_usuarios
            SET password_hash = ?, rol = ?, debe_cambiar_password = 1, activo = 1, actualizado_en = ?
          WHERE id = ?'
    );
    $stmt->execute([$hash, 'admin', $ahora, $id]);
    $accion = 'ACTUALIZADO';
} else {
    $stmt = $pdo->prepare(
        'INSERT INTO pp_usuarios
            (nombre, apellido, correo, password_hash, rol, debe_cambiar_password, activo, creado_en, actualizado_en)
         VALUES (?, ?, ?, ?, ?, 1, 1, ?, ?)'
    );
    $stmt->execute(['Administrador', 'Sistema', $correo, $hash, 'admin', $ahora, $ahora]);
    $accion = 'CREADO';
}

// --- Salida ---
imprimirRecuadro([
    "ADMINISTRADOR {$accion}",
    '',
    "Correo:      {$correo}",
    "Contraseña:  {$password}",
    '',
    'Guarda esta contraseña AHORA: no se volverá a mostrar.',
    'Deberás cambiarla en el primer inicio de sesión.',
]);

exit(0);

/**
 * ¿El dominio del correo está en dominios_permitidos?
 */
function dominioPermitido(string $correo): bool
{
    $pos = strrpos($correo, '@');
    if ($pos === false) {
        return false;
    }
    $dominio = strtolower(substr($correo, $pos + 1));
    return in_array($dominio, Config::get('dominios_permitidos', []), true);
}

/**
 * Imprime un recuadro con las líneas dadas.
 *
 * @param string[] $lineas
 */
function imprimirRecuadro(array $lineas): void
{
    $ancho = 0;
    foreach ($lineas as $linea) {
        $ancho = max($ancho, mb_strlen($linea));
    }
    $borde = str_repeat('=', $ancho + 4);

    echo "\n{$borde}\n";
    foreach ($lineas as $linea) {
        $relleno = str_repeat(' ', $ancho - mb_strlen($linea));
        echo "| {$linea}{$relleno} |\n";
    }
    echo "{$borde}\n\n";
}
