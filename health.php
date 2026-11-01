<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/mail.php';
require_once __DIR__ . '/helpers/http.php';

send_security_headers();
header('Cache-Control: no-store');
header('Content-Type: application/json; charset=utf-8');

$started = microtime(true);
$checks = [
    'app' => 'ok',
    'database' => 'error',
    'mail' => mail_is_configured() ? 'configured' : 'not_configured',
];
$http = 200;

try {
    database()->query('SELECT 1');
    $checks['database'] = 'ok';
} catch (Throwable $exception) {
    $checks['database'] = 'error';
    $http = 503;
}

$payload = [
    'status' => $http === 200 ? 'ok' : 'degraded',
    'service' => APP_NAME,
    'checks' => $checks,
    'response_ms' => (int) round((microtime(true) - $started) * 1000),
];

http_response_code($http);
echo json_encode($payload, JSON_UNESCAPED_SLASHES);
