<?php

namespace App;

/**
 * Carga y acceso a la configuración (config.php).
 * Si config.php no existe, cae a config.example.php (útil para el primer arranque en dev).
 */
final class Config
{
    private static ?array $data = null;

    public static function load(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }

        $configFile  = __DIR__ . '/../config.php';
        $exampleFile = __DIR__ . '/../config.example.php';

        $file = is_file($configFile) ? $configFile : $exampleFile;
        self::$data = require $file;

        return self::$data;
    }

    /**
     * Acceso con notación de puntos: Config::get('db.driver', 'sqlite').
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $data = self::load();
        foreach (explode('.', $key) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return $default;
            }
            $data = $data[$segment];
        }
        return $data;
    }
}
