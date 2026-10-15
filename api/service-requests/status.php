<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/service_request.php';
require_once __DIR__ . '/../_json.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'POST required.'], 405);
}
if (!verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null)) {
    json_response(['success' => false, 'message' => 'Invalid security token.'], 419);
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : $_POST;
$id = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT);
$status = (string) ($payload['status'] ?? '');
if (!$id || !in_array($status, SERVICE_REQUEST_STATUSES, true)) {
    json_response(['success' => false, 'message' => 'A valid request and status are required.'], 422);
}

try {
    ServiceRequest::updateStatus((int) $id, $status, (int) current_user_id(), trim((string) ($payload['note'] ?? '')) ?: null);
    json_response(['success' => true, 'request_id' => (int) $id, 'status' => $status]);
} catch (CalendarConflictException $exception) {
    json_response(['success' => false, 'code' => 'SCHEDULE_CONFLICT', 'message' => $exception->getMessage(), 'conflicts' => $exception->conflicts], 409);
} catch (Throwable $exception) {
    json_response(['success' => false, 'message' => $exception->getMessage()], 422);
}
