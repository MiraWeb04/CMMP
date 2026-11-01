<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../models/ServiceRequest.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
require_valid_csrf_token();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$action = (string) ($_POST['action'] ?? 'archive');
if (!$id) {
    http_response_code(422);
    exit('Invalid service request.');
}

try {
    if ($action === 'delete') {
        ServiceRequest::deletePermanently((int) $id, current_user_id());
        header('Location: index.php?deleted=1');
        exit;
    }
    ServiceRequest::archive((int) $id, current_user_id());
    header('Location: index.php?archived=1');
    exit;
} catch (InvalidArgumentException $exception) {
    header('Location: index.php?error=' . urlencode($exception->getMessage()));
    exit;
} catch (Throwable $exception) {
    error_log('CMMP service request archive/delete failed: ' . $exception->getMessage());
    header('Location: index.php?error=' . urlencode('The service request could not be changed.'));
    exit;
}
