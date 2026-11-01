<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../models/ServiceRequest.php';

require_role('MEMBER');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

require_valid_csrf_token();

$memberId = ServiceRequest::memberIdForUser((int) current_user_id());
if (!$memberId) {
    http_response_code(404);
    exit('Your member profile has not been created yet.');
}

$requestId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$returnTo = trim((string) ($_POST['return'] ?? 'services'));
$destination = $returnTo === 'dashboard'
    ? APP_BASE_URL . '/member/dashboard/'
    : APP_BASE_URL . '/member/services/';

if (!$requestId) {
    header('Location: ' . $destination . '?hide_error=' . urlencode('Select a completed request to delete.'));
    exit;
}

try {
    ServiceRequest::hideFromMember($requestId, $memberId, (int) current_user_id());
    header('Location: ' . $destination . '?hidden=1');
    exit;
} catch (InvalidArgumentException $exception) {
    header('Location: ' . $destination . '?hide_error=' . urlencode($exception->getMessage()));
    exit;
} catch (Throwable $exception) {
    error_log('CMMP member hide service request failed: ' . $exception->getMessage());
    header('Location: ' . $destination . '?hide_error=' . urlencode('The request could not be deleted from your list.'));
    exit;
}
