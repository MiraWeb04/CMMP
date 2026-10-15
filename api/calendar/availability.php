<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../models/ChurchCalendar.php';
require_once __DIR__ . '/../_json.php';

if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Authentication required.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['success' => false, 'message' => 'GET required.'], 405);
}

$start = trim((string) ($_GET['start'] ?? ''));
$end = trim((string) ($_GET['end'] ?? ''));
$facilityId = filter_var($_GET['facility_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
if ($start === '' || $end === '') {
    json_response(['success' => false, 'message' => 'Start and end dates are required.'], 422);
}

try {
    json_response(['success' => true, 'events' => ChurchCalendar::events($start, $end, $facilityId)]);
} catch (Throwable $exception) {
    json_response(['success' => false, 'message' => $exception->getMessage()], 422);
}