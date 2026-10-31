<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

cmmp_load_environment();

const APP_NAME = 'CMMP';
const APP_TITLE = 'Church Management & Member Portal';
const APP_ENV = 'development';
const APP_TIMEZONE = 'Asia/Manila';
const APP_BASE_URL = 'http://localhost/CMMP';
const APP_UPLOAD_MAX_BYTES = 10485760;
const APP_UPLOAD_DIRECTORIES = [
	'profiles' => __DIR__ . '/../uploads/profiles',
	'service_documents' => __DIR__ . '/../uploads/service-documents',
	'event_images' => __DIR__ . '/../uploads/event-images',
	'resources' => __DIR__ . '/../uploads/resources',
];

date_default_timezone_set(getenv('APP_TIMEZONE') ?: APP_TIMEZONE);

if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}

set_exception_handler(static function (Throwable $exception): void {
    error_log('CMMP uncaught exception: ' . $exception->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    $detail = APP_ENV === 'production'
        ? 'Please try again, or contact the church office if it continues.'
        : htmlspecialchars($exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine(), ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>CMMP error</title></head>'
        . '<body style="font-family:Arial,sans-serif;max-width:640px;margin:64px auto;padding:0 16px">'
        . '<h1>The page could not be opened</h1><p>' . $detail . '</p>'
        . '<p><a href="/CMMP/auth/login.php">Return to login</a></p></body></html>';
});
