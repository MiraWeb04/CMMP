<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/NotificationService.php';
require_once __DIR__ . '/../services/MailService.php';

$pdo = database();
$suffix = strtoupper(bin2hex(random_bytes(3)));
$userId = null;

try {
    $pdo->prepare(
        'INSERT INTO users (username, email, password_hash, status)
         VALUES (:username, :email, :password_hash, "ACTIVE")'
    )->execute([
        'username' => 'mail-' . $suffix,
        'email' => 'mail-' . $suffix . '@cmmp.test',
        'password_hash' => password_hash('Password123!', PASSWORD_DEFAULT),
    ]);
    $userId = (int) $pdo->lastInsertId();

    $recipient = NotificationService::emailForUser($userId);
    if (!$recipient || $recipient['email'] !== 'mail-' . $suffix . '@cmmp.test') {
        throw new RuntimeException('User email lookup failed.');
    }

    $notificationId = NotificationService::notifyUser(
        $userId,
        'Assignment confirmation',
        'You were assigned as PASTOR for a completed church service request.',
        'SERVICE_ASSIGNMENT'
    );
    if ($notificationId < 1) {
        throw new RuntimeException('Inbox notification was not created.');
    }

    $row = $pdo->query('SELECT title, message FROM notifications WHERE id = ' . $notificationId)->fetch();
    if (!$row || $row['title'] !== 'Assignment confirmation') {
        throw new RuntimeException('Inbox row was not stored.');
    }

    if (mail_is_configured() !== false && !is_bool(mail_is_configured())) {
        throw new RuntimeException('Mail configuration helper failed.');
    }

    echo "Mail notification smoke test passed.\n";
} finally {
    if ($userId) {
        $pdo->prepare('DELETE FROM notifications WHERE user_id = :id')->execute(['id' => $userId]);
        $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
    }
}
