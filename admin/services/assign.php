<?php
declare(strict_types=1);
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/service_request.php';
require_once __DIR__ . '/../../services/AvailabilityService.php';

require_role(SERVICE_ASSIGN_ROLES);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
require_valid_csrf_token();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$staffId = filter_input(INPUT_POST, 'staff_id', FILTER_VALIDATE_INT);
if (!$id || !$staffId) {
    http_response_code(422);
    exit('Invalid assignment.');
}

$request = ServiceRequest::find($id);
if (!$request) {
    http_response_code(404);
    exit('Service request not found.');
}

$allowedRoles = service_assignment_roles((string) $request['service_code']);
if ($allowedRoles === []) {
    http_response_code(422);
    exit('This service does not require personnel assignment.');
}

$roleLookup = database()->prepare(
    "SELECT u.role
     FROM staff s
     INNER JOIN users u ON u.id = s.user_id OR u.id = (SELECT m.user_id FROM members m WHERE m.id = s.member_id LIMIT 1)
     WHERE s.id = :staff_id
       AND s.employment_status = 'ACTIVE'
       AND u.status = 'ACTIVE'
       AND u.role IN ('PASTOR', 'COUNSELOR')
     LIMIT 1"
);
$roleLookup->execute(['staff_id' => $staffId]);
$assignmentRole = $roleLookup->fetchColumn();
if ($assignmentRole === false || !in_array((string) $assignmentRole, $allowedRoles, true)) {
    http_response_code(422);
    exit('Selected personnel is not allowed for this service.');
}

$start = (string) ($request['preferred_start_datetime'] ?? '');
$end = (string) ($request['preferred_end_datetime'] ?? '');
if ($start !== '' && $end !== '') {
    $roster = AvailabilityService::rosterForWindow($allowedRoles, $start, $end, $id);
    $availableIds = array_map(static fn(array $person): int => (int) $person['id'], $roster['available']);
    if (!in_array($staffId, $availableIds, true)) {
        header('Location: view.php?id=' . $id . '&conflict=' . urlencode('The selected pastor or counselor is no longer available for this schedule.'));
        exit;
    }
}

ServiceRequest::assign($id, $staffId, (string) $assignmentRole, (int) current_user_id());
header('Location: view.php?id=' . $id . '&assigned=1');
exit;
