<?php

/**
 * Autoloader PSR-4 mínimo para el namespace App\ -> api/src/.
 *
 * En producción, si existe vendor/autoload.php (Composer), se carga también
 * para dependencias como PHPMailer.
 */

spl_autoload_register(static function (string $class): void {
    $prefix  = 'App\\';
    $baseDir = __DIR__ . '/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
}
