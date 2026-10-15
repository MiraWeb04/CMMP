<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../services/SchedulingService.php';
require_once __DIR__ . '/../_json.php';

if (!is_logged_in()) { json_response(['success' => false, 'message' => 'Authentication required.'], 401); }
if (!has_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF'])) { json_response(['success' => false, 'message' => 'Forbidden.'], 403); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_response(['success' => false, 'message' => 'POST required.'], 405); }
if (!verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null)) { json_response(['success' => false, 'message' => 'Invalid CSRF token.'], 419); }
$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : $_POST;
$appointmentId = filter_var($payload['appointment_id'] ?? null, FILTER_VALIDATE_INT);
$staffId = filter_var($payload['staff_id'] ?? null, FILTER_VALIDATE_INT);
if (!$appointmentId || !$staffId) { json_response(['success' => false, 'message' => 'Appointment and staff are required.'], 422); }
try {
    SchedulingService::assignStaffToAppointment($appointmentId, $staffId);
    json_response(['success' => true, 'message' => 'Personnel assigned to the appointment.']);
} catch (SchedulingConflict $exception) {
    json_response(['success' => false, 'message' => $exception->getMessage()], 409);
} catch (Throwable $exception) {
    json_response(['success' => false, 'message' => $exception->getMessage()], 422);
}
