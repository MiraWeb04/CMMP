<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../services/AvailabilityService.php';
require_once __DIR__ . '/../../api/_json.php';

if (!is_logged_in()) { json_response(['success' => false, 'message' => 'Authentication required.'], 401); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_response(['success' => false, 'message' => 'POST required.'], 405); }
$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : $_POST;
try {
    $result = AvailabilityService::check($payload);
    json_response(['success' => true, 'available' => $result['available'], 'conflicts' => $result['conflicts'], 'message' => $result['available'] ? 'Schedule is available.' : 'The selected schedule is unavailable.']);
} catch (Throwable $exception) {
    json_response(['success' => false, 'available' => false, 'message' => $exception->getMessage()], 422);
}
