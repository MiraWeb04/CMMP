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
$decision = (string) ($_POST['decision'] ?? '');
if (!$person || !$requestId || !in_array($decision, ['approve', 'decline'], true)) {
    http_response_code(422);
    exit('Service request is required.');
}

try {
    ServiceRequest::decideByPastor($requestId, (int) $person['id'], (int) current_user_id(), $decision);
    header('Location: index.php?' . ($decision === 'approve' ? 'approved=1' : 'declined=1'));
} catch (InvalidArgumentException | CalendarConflictException $exception) {
    header('Location: index.php?error=' . urlencode($exception->getMessage()));
}
exit;
