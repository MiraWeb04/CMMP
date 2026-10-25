<?php
declare(strict_types=1);

function cmmp_load_environment(): void
{
    static $loaded = false;

    if ($loaded) {
        return;
    }

    $loaded = true;

    $root = realpath(dirname(__DIR__));
    if ($root === false) {
        return;
    }

    $path = $root . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $realPath = realpath($path);
    $normalizedRoot = strtolower(str_replace('/', '\\', $root));
    $normalizedPath = $realPath === false ? '' : strtolower(str_replace('/', '\\', $realPath));
    if ($normalizedPath === '' || !str_starts_with($normalizedPath, $normalizedRoot . '\\')) {
        error_log('CMMP environment file was skipped.');
        return;
    }

    $size = filesize($realPath);
    if ($size === false || $size > 65536) {
        error_log('CMMP environment file was skipped.');
        return;
    }

    $contents = file_get_contents($realPath);
    if (!is_string($contents)) {
        error_log('CMMP environment file could not be read.');
        return;
    }

    if (str_starts_with($contents, "\xEF\xBB\xBF")) {
        $contents = substr($contents, 3);
    }

    $lines = preg_split("/\r\n|\n|\r/", $contents);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $parsed = cmmp_parse_env_line($line);
        if ($parsed === null || cmmp_env_already_set($parsed['name'])) {
            continue;
        }

        putenv($parsed['name'] . '=' . $parsed['value']);
        $_ENV[$parsed['name']] = $parsed['value'];
        $_SERVER[$parsed['name']] = $parsed['value'];
    }
}

function cmmp_env(string $name, ?string $default = null): ?string
{
    cmmp_load_environment();

    return cmmp_env_value($name) ?? $default;
}

function cmmp_env_already_set(string $name): bool
{
    return cmmp_env_value($name) !== null;
}

function cmmp_env_value(string $name): ?string
{
    $candidates = [];
    if (array_key_exists($name, $_ENV)) {
        $candidates[] = $_ENV[$name];
    }
    if (array_key_exists($name, $_SERVER)) {
        $candidates[] = $_SERVER[$name];
    }

    $fromProcess = getenv($name);
    if ($fromProcess !== false) {
        $candidates[] = $fromProcess;
    }

    foreach ($candidates as $value) {
        if (!is_scalar($value)) {
            continue;
        }

        $value = trim((string) $value);
        if ($value !== '') {
            return $value;
        }
    }

    return null;
}

function cmmp_parse_env_line(string $line): ?array
{
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) {
        return null;
    }

    if (str_starts_with($line, 'export ')) {
        $line = trim(substr($line, 7));
    }

    $separator = strpos($line, '=');
    if ($separator === false) {
        return null;
    }

    $name = trim(substr($line, 0, $separator));
    if (!preg_match('/^(?:APP_|DB_|MAIL_)[A-Z0-9_]+$/', $name)) {
        return null;
    }

    $value = cmmp_parse_env_value(trim(substr($line, $separator + 1)));
    if (strlen($value) > 4096 || str_contains($value, "\0")) {
        return null;
    }

    return ['name' => $name, 'value' => $value];
}

function cmmp_parse_env_value(string $value): string
{
    if ($value === '') {
        return '';
    }

    $quote = $value[0];
    if (($quote === '"' || $quote === "'") && strlen($value) >= 2 && str_ends_with($value, $quote)) {
        $inner = substr($value, 1, -1);
        if ($quote === '"') {
            $inner = strtr($inner, [
                '\\n' => "\n",
                '\\r' => "\r",
                '\\t' => "\t",
                '\\"' => '"',
                '\\\\' => '\\',
            ]);
        }

        return str_replace("\0", '', $inner);
    }

    $comment = strpos($value, ' #');
    if ($comment !== false) {
        $value = substr($value, 0, $comment);
    }

    return str_replace("\0", '', trim($value));
}
