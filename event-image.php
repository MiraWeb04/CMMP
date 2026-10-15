<?php
declare(strict_types=1);

$file = basename((string) ($_GET['file'] ?? ''));
if (!preg_match('/^[a-f0-9]{32}\.(jpg|jpeg|png|webp)$/', $file)) {
    http_response_code(404);
    exit;
}

$path = __DIR__ . '/uploads/event-images/' . $file;
if (!is_file($path)) {
    http_response_code(404);
    exit;
}

$types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
];
$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
header('Content-Type: ' . $types[$extension]);
header('Content-Length: ' . (string) filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=86400');
readfile($path);
