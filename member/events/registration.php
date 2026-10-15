<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/event.php';

require_role('MEMBER');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed.'); }
require_valid_csrf_token();
$memberId = member_id_for_user((int) current_user_id());
$eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
$action = (string) ($_POST['action'] ?? '');
if (!$memberId || !$eventId || !in_array($action, ['register', 'cancel'], true)) { http_response_code(422); exit('Invalid registration request.'); }
try {
    if ($action === 'register') {
        Event::register($eventId, $memberId);
        header('Location: view.php?id=' . $eventId . '&registered=1');
    } else {
        Event::cancelRegistration($eventId, $memberId);
        header('Location: view.php?id=' . $eventId . '&cancelled=1');
    }
    exit;
} catch (DomainException $exception) {
    header('Location: view.php?id=' . $eventId . '&error=' . urlencode($exception->getMessage()));
    exit;
} catch (Throwable $exception) {
    error_log('CMMP event registration failed: ' . $exception->getMessage());
    header('Location: view.php?id=' . $eventId . '&error=' . urlencode('This registration action could not be completed.'));
    exit;
}
