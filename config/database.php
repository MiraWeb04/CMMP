<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/../helpers/schema_guard.php';

cmmp_load_environment();

function database(): PDO
{
    static $connection;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'cmmp_db';
    $user = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $host,
        $port,
        $name
    );

    $attempts = 0;
    $lastException = null;
    while ($attempts < 3) {
        $attempts++;
        try {
            $connection = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            cmmp_ensure_schema($connection);
            return $connection;
        } catch (PDOException $exception) {
            $lastException = $exception;
            error_log('CMMP database connection failed (attempt ' . $attempts . '): ' . $exception->getMessage());
            if ($attempts < 3) {
                usleep(150000 * $attempts);
            }
        }
    }

    throw new RuntimeException('The CMMP database is temporarily unavailable.', 0, $lastException);
}
