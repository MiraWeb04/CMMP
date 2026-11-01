<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Announcement.php';
require_once __DIR__ . '/../models/Notification.php';

$pdo = database();
$suffix = strtoupper(bin2hex(random_bytes(4)));
$userId = null;
$otherId = null;
$announcementId = null;
$requestId = null;
$memberId = null;

try {
    $pdo->prepare(
        'INSERT INTO users (username, email, password_hash, status)
         VALUES (:u, :e, :p, "ACTIVE")'
    )->execute([
        'u' => 'COMM-' . $suffix,
        'e' => 'comm-' . $suffix . '@test',
        'p' => password_hash('test-password', PASSWORD_DEFAULT),
    ]);
    $userId = (int) $pdo->lastInsertId();

    $announcementId = Announcement::save([
        'user_id' => $userId,
        'title' => 'Test announcement',
        'body' => 'Test body',
        'audience_role' => 'MEMBERS',
        'published_at' => date('Y-m-d H:i:s'),
        'expires_at' => null,
        'status' => 'PUBLISHED',
    ]);

    Notification::create($userId, 'Test notification', 'Test message', 'TEST');
    $items = Notification::forUser($userId);
    if ($items === []) {
        throw new RuntimeException('Notification creation failed.');
    }
    $notificationId = (int) $items[0]['id'];
    Notification::markRead($notificationId, $userId);
    if (Notification::forUser($userId)[0]['read_at'] === null) {
        throw new RuntimeException('Read state failed.');
    }
    Notification::deleteForUser($notificationId, $userId);
    if (count(array_filter(Notification::forUser($userId), static fn (array $row): bool => (int) $row['id'] === $notificationId)) !== 0) {
        throw new RuntimeException('Read notification delete failed.');
    }

    Notification::create($userId, 'Unread delete guard', 'Should stay', 'TEST');
    $unreadId = null;
    foreach (Notification::forUser($userId) as $row) {
        if ($row['read_at'] === null) {
            $unreadId = (int) $row['id'];
            break;
        }
    }
    if (!$unreadId) {
        throw new RuntimeException('Unread notification fixture missing.');
    }
    try {
        Notification::deleteForUser($unreadId, $userId);
        throw new RuntimeException('Unread notification delete must be blocked.');
    } catch (InvalidArgumentException $expected) {
        if (!str_contains($expected->getMessage(), 'Only read notifications can be deleted')) {
            throw $expected;
        }
    }
    if (count(array_filter(Notification::forUser($userId), static fn (array $row): bool => (int) $row['id'] === $unreadId)) !== 1) {
        throw new RuntimeException('Unread notification was deleted.');
    }

    try {
        Notification::deleteForUser(999999999, $userId);
        throw new RuntimeException('Forged notification ID must be blocked.');
    } catch (InvalidArgumentException $expected) {
        if (!str_contains($expected->getMessage(), 'Notification not found')) {
            throw $expected;
        }
    }

    $pdo->prepare(
        'INSERT INTO users (username, email, password_hash, status)
         VALUES (:u, :e, :p, "ACTIVE")'
    )->execute([
        'u' => 'COMM2-' . $suffix,
        'e' => 'comm2-' . $suffix . '@test',
        'p' => password_hash('test-password', PASSWORD_DEFAULT),
    ]);
    $otherId = (int) $pdo->lastInsertId();
    Notification::create($otherId, 'Other user notice', 'Must stay', 'TEST');
    $otherNotice = (int) $pdo->query('SELECT id FROM notifications WHERE user_id=' . $otherId . ' LIMIT 1')->fetchColumn();
    Notification::markRead($otherNotice, $otherId);
    try {
        Notification::deleteForUser($otherNotice, $userId);
        throw new RuntimeException('Cross-user notification delete must be blocked.');
    } catch (InvalidArgumentException $expected) {
        if (!str_contains($expected->getMessage(), 'Notification not found')) {
            throw $expected;
        }
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM notifications WHERE id=' . $otherNotice)->fetchColumn() !== 1) {
        throw new RuntimeException('Other user notification was deleted.');
    }

    $serviceTypeId = (int) $pdo->query('SELECT id FROM service_types WHERE is_active = 1 LIMIT 1')->fetchColumn();
    if ($serviceTypeId > 0) {
        $pdo->prepare(
            'INSERT INTO members (member_number, first_name, last_name, membership_status)
             VALUES (:number, "Comm", "Tester", "ACTIVE")'
        )->execute(['number' => 'COMM-' . $suffix]);
        $memberId = (int) $pdo->lastInsertId();
        $pdo->prepare(
            'INSERT INTO service_requests (service_type_id, requested_by_member_id, request_number, status, subject)
             VALUES (:type_id, :member_id, :number, "COMPLETED", :subject)'
        )->execute([
            'type_id' => $serviceTypeId,
            'member_id' => $memberId,
            'number' => 'COMM-' . $suffix,
            'subject' => 'Linked request',
        ]);
        $requestId = (int) $pdo->lastInsertId();
        Notification::create($userId, 'Linked notice', 'Keep the request', 'SERVICE', 'service_request', $requestId);
        $linkedId = 0;
        foreach (Notification::forUser($userId) as $row) {
            if ((string) $row['title'] === 'Linked notice') {
                $linkedId = (int) $row['id'];
                break;
            }
        }
        Notification::markRead($linkedId, $userId);
        Notification::deleteForUser($linkedId, $userId);
        if ((int) $pdo->query('SELECT COUNT(*) FROM service_requests WHERE id=' . $requestId)->fetchColumn() !== 1) {
            throw new RuntimeException('Notification delete removed the linked service request.');
        }
    }

    Notification::create($userId, 'Search stay', 'Inbox search still works', 'TEST');
    $search = Notification::filtered($userId, ['q' => 'Inbox search still works', 'status' => 'all', 'type' => '', 'since' => '']);
    if (!is_array($search) || count($search) !== 1) {
        throw new RuntimeException('Notification search failed after unique placeholders.');
    }

    Notification::create($userId, 'Bulk one', 'One', 'TEST');
    Notification::create($userId, 'Bulk two', 'Two', 'TEST');
    $readIds = [];
    foreach (Notification::forUser($userId) as $row) {
        Notification::markRead((int) $row['id'], $userId);
        $readIds[] = (int) $row['id'];
    }
    if (Notification::deleteManyForUser($readIds, $userId) < 1) {
        throw new RuntimeException('Selected notification delete failed.');
    }

    Notification::create($userId, 'Unread after bulk', 'Stay', 'TEST');
    Notification::create($userId, 'Clear me', 'Gone', 'TEST');
    foreach (Notification::forUser($userId) as $row) {
        if ((string) $row['title'] === 'Clear me') {
            Notification::markRead((int) $row['id'], $userId);
        }
    }
    Notification::deleteAllForUser($userId);
    $remaining = Notification::forUser($userId);
    if (count($remaining) !== 1 || $remaining[0]['read_at'] !== null) {
        throw new RuntimeException('Delete all must remove only the current user\'s read notifications.');
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM notifications WHERE id=' . $otherNotice)->fetchColumn() !== 1) {
        throw new RuntimeException('Delete all removed another user notification.');
    }

    echo "Communication smoke test passed.\n";
} finally {
    $pdo->prepare('DELETE FROM notifications WHERE user_id=:id')->execute(['id' => $userId ?? 0]);
    if (!empty($otherId)) {
        $pdo->prepare('DELETE FROM notifications WHERE user_id=:id')->execute(['id' => $otherId]);
        $pdo->prepare('DELETE FROM users WHERE id=:id')->execute(['id' => $otherId]);
    }
    if ($requestId) {
        $pdo->prepare('DELETE FROM service_requests WHERE id=:id')->execute(['id' => $requestId]);
    }
    if ($memberId) {
        $pdo->prepare('DELETE FROM members WHERE id=:id')->execute(['id' => $memberId]);
    }
    if ($announcementId) {
        $pdo->prepare('DELETE FROM notifications WHERE reference_type="announcement" AND reference_id=:id')->execute(['id' => $announcementId]);
        $pdo->prepare('DELETE FROM announcements WHERE id=:id')->execute(['id' => $announcementId]);
    }
    if ($userId) {
        $pdo->prepare('DELETE FROM users WHERE id=:id')->execute(['id' => $userId]);
    }
}
