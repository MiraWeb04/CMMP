<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

require_once __DIR__ . '/../config/env.php';
cmmp_load_environment();

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'cmmp_db';
$user = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';

$directory = __DIR__ . '/../backups';
if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
    fwrite(STDERR, "Could not create backups directory.\n");
    exit(1);
}

$stamp = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Ymd-His');
$target = $directory . '/cmmp_db-' . $stamp . '.sql';
$mysqldump = getenv('MYSQLDUMP_PATH') ?: '';
if ($mysqldump === '' || !is_file($mysqldump)) {
    $found = trim((string) shell_exec('command -v mysqldump 2>/dev/null'));
    $windows = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
    $mysqldump = $found !== '' ? $found : (is_file($windows) ? $windows : 'mysqldump');
}

$command = sprintf(
    '%s --host=%s --port=%s --user=%s --single-transaction --routines --triggers --databases %s',
    escapeshellcmd($mysqldump),
    escapeshellarg($host),
    escapeshellarg((string) $port),
    escapeshellarg($user),
    escapeshellarg($name)
);

$descriptor = [
    0 => ['pipe', 'r'],
    1 => ['file', $target, 'w'],
    2 => ['pipe', 'w'],
];
$process = proc_open($command, $descriptor, $pipes, null, $password !== '' ? ['MYSQL_PWD' => $password] : []);
if (!is_resource($process)) {
    fwrite(STDERR, "Could not start mysqldump.\n");
    exit(1);
}
fclose($pipes[0]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[2]);
$code = proc_close($process);

if ($code !== 0 || !is_file($target) || filesize($target) < 32) {
    @unlink($target);
    fwrite(STDERR, "Backup failed.\n" . $stderr);
    exit(1);
}

fwrite(STDOUT, "Backup written: {$target}\n");
