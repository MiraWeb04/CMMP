<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../services/SchedulingService.php';
require_once __DIR__ . '/../../api/_json.php';

if (!is_logged_in()) { json_response(['success' => false, 'message' => 'Authentication required.'], 401); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_response(['success' => false, 'message' => 'POST required.'], 405); }
if (!verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null)) { json_response(['success' => false, 'message' => 'Invalid CSRF token.'], 419); }
$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : $_POST;
$payload['created_by_user_id'] = (int) current_user_id();
try {
    $appointmentId = SchedulingService::createAppointment($payload);
    json_response(['success' => true, 'appointment_id' => $appointmentId, 'message' => 'Appointment confirmed.'], 201);
} catch (SchedulingConflict $exception) {
    json_response(['success' => false, 'available' => false, 'message' => $exception->getMessage()], 409);
} catch (InvalidArgumentException $exception) {
    json_response(['success' => false, 'message' => $exception->getMessage()], 422);
} catch (Throwable $exception) {
    error_log('CMMP appointment creation failed: ' . $exception->getMessage());
    json_response(['success' => false, 'message' => 'The appointment could not be created.'], 500);
}
