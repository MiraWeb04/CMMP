<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/auth.php';

start_secure_session();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Location: ' . APP_BASE_URL . '/member-portal.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Location: ' . APP_BASE_URL . '/member-portal.php');
    exit;
}

http_response_code(405);
exit('Method not allowed.');
