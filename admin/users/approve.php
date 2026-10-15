<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../models/AuditLog.php';
require_once __DIR__ . '/../../models/Notification.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

require_valid_csrf_token();
$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$decision = (string) ($_POST['decision'] ?? '');
if (!$userId || !in_array($decision, ['approve', 'decline'], true)) {
    http_response_code(422);
    exit('A pending registration is required.');
}

$connection = database();
$account = $connection->prepare('SELECT id, username, status FROM users WHERE id = :id LIMIT 1');
$account->execute(['id' => $userId]);
$user = $account->fetch();
if (!$user || $user['status'] !== 'PENDING') {
    $target = (string) ($_POST['return'] ?? '') === 'registrations' ? '../members/registrations.php' : 'index.php';
    header('Location: ' . $target . '?error=' . urlencode('That registration is no longer waiting for approval.'));
    exit;
}

if ($decision === 'approve') {
    $connection->prepare('UPDATE users SET status = "ACTIVE" WHERE id = :id AND status = "PENDING"')->execute(['id' => $userId]);
    $connection->prepare(
        'UPDATE members SET membership_status = "ACTIVE", joined_at = CURDATE() WHERE user_id = :id'
    )->execute(['id' => $userId]);
    AuditLog::record((int) current_user_id(), 'MEMBER_REGISTRATION_APPROVED', 'USER', $userId);
    Notification::create($userId, 'Registration approved', 'A church administrator approved your account. You can sign in now.', 'MEMBER_REGISTRATION_APPROVED', 'user', $userId);
    header('Location: ' . registration_decision_redirect('approved'));
    exit;
}

$connection->prepare('UPDATE users SET status = "INACTIVE" WHERE id = :id AND status = "PENDING"')->execute(['id' => $userId]);
$connection->prepare('UPDATE members SET membership_status = "INACTIVE" WHERE user_id = :id')->execute(['id' => $userId]);
AuditLog::record((int) current_user_id(), 'MEMBER_REGISTRATION_DECLINED', 'USER', $userId);
header('Location: ' . registration_decision_redirect('declined'));
exit;

function registration_decision_redirect(string $result): string
{
    if ((string) ($_POST['return'] ?? '') === 'registrations') {
        return '../members/registrations.php?' . $result . '=1';
    }

    return 'index.php?' . $result . '=1';
}
