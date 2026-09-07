<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $pdo = null;

    public static function getConnection(): ?PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $host = config('db_host', '127.0.0.1');
        $db = config('db_name', '360management_db');
        $user = config('db_user', 'root');
        $pass = config('db_pass', '');
        $port = config('db_port', 3306);

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            self::$pdo = new PDO($dsn, $user, $pass, $options);
            return self::$pdo;
        } catch (PDOException $e) {
            if (function_exists('app_is_live') && app_is_live()) {
                throw new \RuntimeException('Live database connection failed. Check DB_HOST, DB_NAME, DB_USER, DB_PASS and DB_PORT.', 0, $e);
            }

            // Attempt auto-creating database if missing
            try {
                $rootDsn = "mysql:host={$host};port={$port};charset=utf8mb4";
                $tmpPdo = new PDO($rootDsn, $user, $pass, $options);
                $tmpPdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                self::$pdo = new PDO($dsn, $user, $pass, $options);
                self::runMigrations();
                return self::$pdo;
            } catch (PDOException $ex) {
                if (function_exists('app_requires_database') && app_requires_database()) {
                    throw new \RuntimeException('Database connection is required but unavailable. Session fallback is disabled for this environment.', 0, $ex);
                }

                // Return null if database engine is unavailable; services will gracefully fallback
                return null;
            }
        }
    }

    public static function runMigrations(): void
    {
        $pdo = self::getConnection();
        if (!$pdo) {
            return;
        }
        $schemaFile = dirname(__DIR__, 2) . '/database/schema.sql';
        if (file_exists($schemaFile)) {
            $sql = file_get_contents($schemaFile);
            if (!empty($sql)) {
                try {
                    $pdo->exec($sql);
                } catch (\Throwable $t) {
                    // Suppress migration warnings if already applied
                }
            }
        }
    }

    public static function isConnected(): bool
    {
        return self::getConnection() !== null;
    }
}
