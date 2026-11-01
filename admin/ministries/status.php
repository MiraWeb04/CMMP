<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../models/Ministry.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
require_valid_csrf_token();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$status = strtoupper(trim((string) ($_POST['status'] ?? '')));
if (!$id || !in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
    http_response_code(422);
    exit('Invalid ministry status.');
}

try {
    Ministry::setStatus((int) $id, $status, current_user_id());
    header('Location: view.php?id=' . (int) $id . '&status_changed=1');
    exit;
} catch (InvalidArgumentException $exception) {
    header('Location: view.php?id=' . (int) $id . '&error=' . urlencode($exception->getMessage()));
    exit;
}
