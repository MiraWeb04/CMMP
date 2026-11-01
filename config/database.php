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

    $name = cmmp_db_name();
    $user = cmmp_env('DB_USER', 'root') ?? 'root';
    $password = cmmp_db_password();
    $lastException = null;

    foreach (cmmp_db_dsns($name) as $dsn) {
        try {
            $connection = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            try {
                cmmp_ensure_schema($connection);
            } catch (Throwable $schemaException) {
                error_log('CMMP schema guard failed: ' . $schemaException->getMessage());
            }
            return $connection;
        } catch (PDOException $exception) {
            $lastException = $exception;
            error_log('CMMP database connection failed: ' . $exception->getMessage());
            if (cmmp_db_is_unknown_database($exception)) {
                try {
                    $connection = cmmp_db_create_and_connect($dsn, $name, $user, $password);
                    try {
                        cmmp_ensure_schema($connection);
                    } catch (Throwable $schemaException) {
                        error_log('CMMP schema guard failed: ' . $schemaException->getMessage());
                    }
                    return $connection;
                } catch (Throwable $createException) {
                    $lastException = $createException instanceof PDOException
                        ? $createException
                        : $exception;
                    error_log('CMMP database create failed: ' . $createException->getMessage());
                }
            }
        }
    }

    throw new RuntimeException(cmmp_db_unavailable_message($lastException), 0, $lastException);
}

function cmmp_db_name(): string
{
    $name = preg_replace('/[^A-Za-z0-9_]/', '', (string) (cmmp_env('DB_NAME', 'cmmp_db') ?? 'cmmp_db'));
    return $name !== '' ? $name : 'cmmp_db';
}

function cmmp_db_password(): string
{
    $password = cmmp_env('DB_PASSWORD', '') ?? '';
    if (in_array(strtolower($password), ['your-database-password', 'changeme', 'replace-me'], true)) {
        return '';
    }

    return $password;
}

function cmmp_db_hosts(): array
{
    $configured = trim((string) (cmmp_env('DB_HOST', '127.0.0.1') ?? '127.0.0.1'));
    $hosts = [$configured, '127.0.0.1', 'localhost'];
    $unique = [];
    foreach ($hosts as $host) {
        if ($host !== '' && !in_array($host, $unique, true)) {
            $unique[] = $host;
        }
    }

    return $unique;
}

function cmmp_db_dsns(string $name): array
{
    $port = trim((string) (cmmp_env('DB_PORT', '3306') ?? '3306'));
    $dsns = [];
    foreach (cmmp_db_hosts() as $host) {
        $dsns[] = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
        if ($host === 'localhost') {
            $dsns[] = sprintf('mysql:host=localhost;dbname=%s;charset=utf8mb4', $name);
        }
    }

    return array_values(array_unique($dsns));
}

function cmmp_db_is_unknown_database(PDOException $exception): bool
{
    return str_contains($exception->getMessage(), 'Unknown database')
        || (string) $exception->getCode() === '1049';
}

function cmmp_db_create_and_connect(string $failedDsn, string $name, string $user, string $password): PDO
{
    $adminDsn = (string) preg_replace('/;dbname=[^;]+/', '', $failedDsn);
    $admin = new PDO($adminDsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    $admin->exec('CREATE DATABASE IF NOT EXISTS `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

    return new PDO($failedDsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ]);
}

function cmmp_db_unavailable_message(?Throwable $exception): string
{
    $hint = 'Start MySQL in the XAMPP Control Panel, then confirm the database is cmmp_db.';
    if (!$exception instanceof Throwable) {
        return 'The CMMP database is temporarily unavailable. ' . $hint;
    }

    return 'The CMMP database is temporarily unavailable. ' . $hint . ' MySQL said: ' . $exception->getMessage();
}
