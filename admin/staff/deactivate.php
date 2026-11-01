<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../models/Personnel.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

require_valid_csrf_token();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$action = (string) ($_POST['action'] ?? 'deactivate');
$person = $id ? Personnel::find((int) $id) : null;
if (!$person) {
    http_response_code(404);
    exit('Personnel record not found.');
}

$type = (string) ($person['personnel_type'] ?? 'STAFF');
$redirect = match ($type) {
    'PASTOR' => '../pastors/index.php',
    'COUNSELOR' => '../counselors/index.php',
    default => 'index.php',
};
$statusFilter = trim((string) ($_POST['status_filter'] ?? ''));
$query = $statusFilter !== '' ? '&status=' . urlencode($statusFilter) : '';

try {
    if ($action === 'reactivate') {
        Personnel::reactivate((int) $id, current_user_id());
        header('Location: ' . $redirect . '?reactivated=1' . $query);
        exit;
    }
    Personnel::deactivate((int) $id, current_user_id());
    header('Location: ' . $redirect . '?deactivated=1' . $query);
    exit;
} catch (InvalidArgumentException $exception) {
    header('Location: ' . $redirect . '?error=' . urlencode($exception->getMessage()) . $query);
    exit;
}
