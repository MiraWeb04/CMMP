<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/service_request.php';
require_once __DIR__ . '/../_json.php';

require_role('MEMBER');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'POST required.'], 405);
}
if (!verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null)) {
    json_response(['success' => false, 'message' => 'Invalid security token.'], 419);
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : $_POST;
$memberId = ServiceRequest::memberIdForUser((int) current_user_id());
$typeId = filter_var($payload['service_type_id'] ?? null, FILTER_VALIDATE_INT);
$types = ServiceRequest::types();
$selected = null;
foreach ($types as $type) {
    if ((int) $type['id'] === (int) $typeId) {
        $selected = $type;
        break;
    }
}

if (!$memberId || !$selected) {
    json_response(['success' => false, 'message' => 'A valid member profile and service type are required.'], 422);
}

$data = service_request_input(array_merge($payload, is_array($payload['request_data'] ?? null) ? $payload['request_data'] : []));
$preferredStart = trim((string) ($payload['preferred_start_datetime'] ?? ''));
$preferredEnd = trim((string) ($payload['preferred_end_datetime'] ?? ''));
$errors = validate_service_request($selected['code'], $data, $preferredStart, $preferredEnd);
if ($errors !== []) {
    json_response(['success' => false, 'message' => implode(' ', $errors), 'errors' => $errors], 422);
}

try {
    $requestId = ServiceRequest::create([
        'service_type_id' => (int) $selected['id'],
        'requested_by_member_id' => $memberId,
        'request_number' => '',
        'subject' => $selected['name'],
        'description' => $data['additional_information'] ?? null,
        'request_data' => json_encode($data, JSON_THROW_ON_ERROR),
        'preferred_start_datetime' => date('Y-m-d H:i:s', strtotime($preferredStart)),
        'preferred_end_datetime' => date('Y-m-d H:i:s', strtotime($preferredEnd)),
        'preferred_facility_id' => filter_var($payload['preferred_facility_id'] ?? null, FILTER_VALIDATE_INT) ?: null,
        'requested_by_member_id_user' => (int) current_user_id(),
    ], service_request_participants($selected['code'], $data, $memberId));
    json_response(['success' => true, 'request_id' => $requestId, 'status' => 'PENDING_ASSIGNMENT'], 201);
} catch (CalendarConflictException $exception) {
    json_response(['success' => false, 'code' => 'SCHEDULE_CONFLICT', 'message' => $exception->getMessage(), 'conflicts' => $exception->conflicts], 409);
} catch (Throwable $exception) {
    error_log('CMMP API service request failed: ' . $exception->getMessage());
    json_response(['success' => false, 'message' => 'The service request could not be created.'], 500);
}
