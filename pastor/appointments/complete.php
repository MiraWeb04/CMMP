<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../models/Personnel.php';
require_once __DIR__ . '/../../services/SchedulingService.php';

require_role('PASTOR');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

require_valid_csrf_token();
$person = Personnel::findForUser((int) current_user_id());
$appointmentId = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
if (!$person || !$appointmentId) {
    http_response_code(422);
    exit('Appointment is required.');
}

$return = (string) ($_POST['return'] ?? 'index.php');
if (!in_array($return, ['index.php', '../services/index.php'], true)) {
    $return = 'index.php';
}

try {
    SchedulingService::completeForStaff($appointmentId, (int) $person['id'], (int) current_user_id());
    header('Location: ' . $return . '?completed=1');
} catch (InvalidArgumentException $exception) {
    header('Location: ' . $return . '?error=' . urlencode($exception->getMessage()));
}
exit;
