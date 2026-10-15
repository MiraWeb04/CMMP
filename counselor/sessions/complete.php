<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../models/Personnel.php';
require_once __DIR__ . '/../../models/Counseling.php';

require_role('COUNSELOR');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed.'); }
require_valid_csrf_token();
$person = Personnel::findForUser((int) current_user_id());
$sessionId = filter_input(INPUT_POST, 'session_id', FILTER_VALIDATE_INT);
if (!$person || !$sessionId) { http_response_code(422); exit('Invalid session.'); }
$caseQuery = database()->prepare('SELECT counseling_case_id FROM counseling_sessions WHERE id = :id');
$caseQuery->execute(['id' => $sessionId]);
$caseId = (int) $caseQuery->fetchColumn();
Counseling::completeSession($sessionId, (int) $person['id'], trim((string) ($_POST['private_notes'] ?? '')), (int) current_user_id());
header('Location: view.php?id=' . $caseId);
exit;
