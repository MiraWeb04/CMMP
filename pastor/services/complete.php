<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../models/Personnel.php';
require_once __DIR__ . '/../../models/ServiceRequest.php';

require_role('PASTOR');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

require_valid_csrf_token();
$person = Personnel::findForUser((int) current_user_id());
$requestId = filter_input(INPUT_POST, 'service_request_id', FILTER_VALIDATE_INT);
if (!$person || !$requestId) {
    http_response_code(422);
    exit('Service request is required.');
}

try {
    ServiceRequest::completeByPastor($requestId, (int) $person['id'], (int) current_user_id());
    header('Location: index.php?completed=1');
} catch (InvalidArgumentException $exception) {
    header('Location: index.php?error=' . urlencode($exception->getMessage()));
}
exit;
