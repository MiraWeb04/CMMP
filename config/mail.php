<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

function mail_config(): array
{
    cmmp_load_environment();

    $encryption = strtolower((string) cmmp_env('MAIL_ENCRYPTION', 'tls'));
    if (!in_array($encryption, ['tls', 'ssl'], true)) {
        $encryption = 'tls';
    }

    $port = (int) cmmp_env('MAIL_PORT', '587');
    if ($port < 1 || $port > 65535) {
        $port = 587;
    }

    $host = (string) cmmp_env('MAIL_HOST', 'smtp.gmail.com');
    if (!preg_match('/^[A-Za-z0-9.-]{1,253}$/', $host)) {
        $host = '';
    }

    return [
        'host' => $host,
        'port' => $port,
        'encryption' => $encryption,
        'username' => trim((string) cmmp_env('MAIL_USERNAME', ''), " \t\"'"),
        'password' => str_replace(' ', '', trim((string) cmmp_env('MAIL_PASSWORD', ''), " \t\"'")),
        'from_address' => trim((string) cmmp_env('MAIL_FROM_ADDRESS', ''), " \t\"'"),
        'from_name' => cmmp_single_line((string) cmmp_env('MAIL_FROM_NAME', 'CMMP Church Portal'), 'CMMP Church Portal'),
    ];
}

function mail_is_configured(?array $config = null): bool
{
    $config ??= mail_config();
    $placeholders = [
        'your-gmail-address@gmail.com',
        'your-gmail-app-password',
    ];

    foreach (['username', 'password', 'from_address'] as $key) {
        if (in_array(strtolower((string) $config[$key]), $placeholders, true)) {
            return false;
        }
    }

    return $config['host'] !== ''
        && $config['port'] > 0
        && $config['username'] !== ''
        && $config['password'] !== ''
        && filter_var($config['from_address'], FILTER_VALIDATE_EMAIL) !== false
        && filter_var($config['username'], FILTER_VALIDATE_EMAIL) !== false;
}

function mail_env_file_path(): string
{
    $root = realpath(dirname(__DIR__));

    return $root === false ? '' : $root . DIRECTORY_SEPARATOR . '.env';
}

function mail_password_shape(string $password): string
{
    $length = strlen($password);
    if ($length === 0) {
        return 'missing';
    }
    if ($length === 16 && preg_match('/^[a-z]+$/', $password) === 1) {
        return '16 lowercase letters (correct Gmail app-password shape)';
    }

    $parts = [$length . ' chars'];
    if (preg_match('/[A-Z]/', $password)) {
        $parts[] = 'has uppercase';
    }
    if (preg_match('/\d/', $password)) {
        $parts[] = 'has digits';
    }
    if (preg_match('/[^A-Za-z0-9]/', $password)) {
        $parts[] = 'has symbols or quotes';
    }

    return implode(', ', $parts) . ' — a Gmail App Password is exactly 16 lowercase letters';
}

function cmmp_single_line(string $value, string $fallback): string
{
    $value = trim(str_replace(["\r", "\n", "\0"], '', $value));

    return $value === '' ? $fallback : $value;
}
