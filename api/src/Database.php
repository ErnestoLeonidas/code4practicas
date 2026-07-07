<?php

namespace App;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Conexión PDO agnóstica de driver.
 *
 * - Desarrollo local: SQLite (archivo).
 * - Producción: MySQL/MariaDB.
 *
 * Se usa siempre con prepared statements. Las tablas llevan prefijo `pp_`.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $driver = Config::get('db.driver', 'sqlite');

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            if ($driver === 'sqlite') {
                self::$pdo = self::connectSqlite($options);
            } elseif ($driver === 'mysql') {
                self::$pdo = self::connectMysql($options);
            } else {
                throw new RuntimeException("Driver de BD no soportado: {$driver}");
            }
        } catch (PDOException $e) {
            throw new RuntimeException('No se pudo conectar a la base de datos: ' . $e->getMessage(), 0, $e);
        }

        return self::$pdo;
    }

    private static function connectSqlite(array $options): PDO
    {
        $path = Config::get('db.sqlite_path', __DIR__ . '/../data/app.sqlite');

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $pdo = new PDO('sqlite:' . $path, null, null, $options);
        // Integridad referencial (SQLite la desactiva por defecto).
        $pdo->exec('PRAGMA foreign_keys = ON');
        return $pdo;
    }

    private static function connectMysql(array $options): PDO
    {
        $host    = Config::get('db.host', 'localhost');
        $port    = (int) Config::get('db.port', 3306);
        $name    = Config::get('db.database', '');
        $charset = Config::get('db.charset', 'utf8mb4');

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

        return new PDO(
            $dsn,
            Config::get('db.username', ''),
            Config::get('db.password', ''),
            $options
        );
    }

    public static function driver(): string
    {
        return Config::get('db.driver', 'sqlite');
    }
}
