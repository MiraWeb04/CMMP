<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

$dump = $argv[1] ?? '';
if ($dump === '' || !is_file($dump)) {
    fwrite(STDERR, "Usage: php scripts/restore-database.php backups/cmmp_db-YYYYMMDD-HHMMSS.sql\n");
    exit(1);
}

require_once __DIR__ . '/../config/env.php';
cmmp_load_environment();

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'cmmp_db';
$user = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';

$mysql = getenv('MYSQL_PATH') ?: '';
if ($mysql === '' || !is_file($mysql)) {
    $found = trim((string) shell_exec('command -v mysql 2>/dev/null'));
    $mysql = $found !== '' ? $found : (is_file('C:\\xampp\\mysql\\bin\\mysql.exe') ? 'C:\\xampp\\mysql\\bin\\mysql.exe' : 'mysql');
}

$command = sprintf(
    '%s --host=%s --port=%s --user=%s %s',
    escapeshellcmd($mysql),
    escapeshellarg($host),
    escapeshellarg((string) $port),
    escapeshellarg($user),
    escapeshellarg($name)
);

$descriptor = [
    0 => ['file', $dump, 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];
$process = proc_open($command, $descriptor, $pipes, null, $password !== '' ? ['MYSQL_PWD' => $password] : []);
if (!is_resource($process)) {
    fwrite(STDERR, "Could not start mysql.\n");
    exit(1);
}
stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$code = proc_close($process);
if ($code !== 0) {
    fwrite(STDERR, "Restore failed.\n" . $stderr);
    exit(1);
}

fwrite(STDOUT, "Restored {$name} from {$dump}\n");
