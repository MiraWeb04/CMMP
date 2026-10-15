<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../services/AvailabilityService.php';
require_once __DIR__ . '/../_json.php';

if (!is_logged_in() || !has_role('STAFF')) {
    json_response(['success' => false, 'message' => 'Authentication required.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'POST required.'], 405);
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : $_POST;
if (!verify_csrf_token(is_string($payload['csrf_token'] ?? null) ? $payload['csrf_token'] : null)) {
    json_response(['success' => false, 'message' => 'Invalid security token.'], 419);
}

$date = trim((string) ($payload['appointment_date'] ?? ''));
$startTime = trim((string) ($payload['start_time'] ?? ''));
$endTime = trim((string) ($payload['end_time'] ?? ''));
if ($date === '' || $startTime === '' || $endTime === '') {
    json_response(['success' => false, 'message' => 'Date, start time, and end time are required.'], 422);
}

try {
    json_response([
        'success' => true,
        'pastors' => AvailabilityService::availablePersonnel('PASTOR', $date, $startTime, $endTime),
        'counselors' => AvailabilityService::availablePersonnel('COUNSELOR', $date, $startTime, $endTime),
    ]);
} catch (Throwable $exception) {
    json_response(['success' => false, 'message' => $exception->getMessage()], 422);
}
