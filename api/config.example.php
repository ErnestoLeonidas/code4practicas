<?php
/**
 * Plantilla de configuración.
 *
 * Copia este archivo como `config.php` y completa los valores.
 * NUNCA se debe commitear `config.php` (está en .gitignore).
 */

return [
    // Versión de la aplicación (la expone /api/health).
    'app_version' => '0.0.1',

    // Entorno: 'dev' o 'prod'. En 'prod' se ocultan errores PHP.
    'env' => 'dev',

    // URL base del frontend (para CORS y enlaces en correos).
    // En dev es el server de Vite (puerto 51731); el backend PHP corre en 18081.
    'app_url' => 'http://localhost:51731',

    /**
     * Base de datos.
     *
     * driver 'sqlite'  -> desarrollo local (archivo, sin servidor).
     * driver 'mysql'   -> producción (hosting compartido).
     */
    'db' => [
        'driver' => 'sqlite', // 'sqlite' | 'mysql'

        // --- SQLite (desarrollo) ---
        // Ruta del archivo de base de datos. Relativa a la carpeta api/.
        'sqlite_path' => __DIR__ . '/data/app.sqlite',

        // --- MySQL (producción) ---
        'host'     => 'localhost',
        'port'     => 3306,
        'database' => 'nombre_bd',
        'username' => 'usuario_bd',
        'password' => 'clave_bd',
        'charset'  => 'utf8mb4',
    ],

    // Dominios de correo institucionales permitidos para login (v0.1.0+).
    'dominios_permitidos' => ['duoc.cl', 'duocuc.cl', 'profesor.duoc.cl'],

    // Configuración SMTP para PHPMailer (v0.3.0+). Rellenar cuando corresponda.
    'smtp' => [
        'host'       => '',
        'port'       => 587,
        'username'   => '',
        'password'   => '',
        'from_email' => 'no-reply@example.com',
        'from_name'  => 'Seguimiento de Prácticas',
        'secure'     => 'tls', // 'tls' | 'ssl' | ''
    ],
];
