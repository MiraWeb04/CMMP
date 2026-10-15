<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/event.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed.'); }
require_valid_csrf_token();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$status = (string) ($_POST['status'] ?? '');
if (!$id || !in_array($status, EVENT_STATUSES, true)) { http_response_code(422); exit('Invalid event status.'); }
$event = Event::find($id);
if (!$event) { http_response_code(404); exit('Event not found.'); }
try {
	Event::setStatus($id, $status);
	header('Location: view.php?id=' . $id . '&status_changed=1');
} catch (CalendarConflictException $exception) {
	header('Location: view.php?id=' . $id . '&conflict=' . urlencode($exception->getMessage()));
}
exit;
