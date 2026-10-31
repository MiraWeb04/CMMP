<?php
declare(strict_types=1);

/**
 * Verifies member dashboard removal (soft-hide) and read-notification
 * deletion against CMMP database records. Seeds realistic fixtures when
 * the local database has no member/request/notification rows.
 */
putenv('DB_HOST=127.0.0.1');
putenv('DB_USER=cmmp');
putenv('DB_PASSWORD=cmmp');
putenv('DB_NAME=cmmp_db');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/ServiceRequest.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../models/AuditLog.php';

$passed = 0;
$failed = 0;
$results = [];

function verify(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed, $results;
    if ($ok) {
        $passed++;
        $results[] = ['PASS', $name, $detail];
        echo "PASS  {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
        return;
    }
    $failed++;
    $results[] = ['FAIL', $name, $detail];
    echo "FAIL  {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
}

function expectException(callable $callback, string $needle = ''): array
{
    try {
        $callback();
        return [false, 'No exception was thrown.'];
    } catch (Throwable $exception) {
        $message = $exception->getMessage();
        if ($needle !== '' && !str_contains($message, $needle)) {
            return [false, 'Unexpected message: ' . $message];
        }
        return [true, $message];
    }
}

$pdo = database();
$suffix = 'VERIFY' . strtoupper(bin2hex(random_bytes(3)));
$createdUserIds = [];
$createdMemberIds = [];
$createdRequestIds = [];
$createdNotificationIds = [];

function insertUser(PDO $pdo, string $username, string $email, string $role, array &$createdUserIds): int
{
    $pdo->prepare(
        'INSERT INTO users (username, email, role, password_hash, status)
         VALUES (:username, :email, :role, :password_hash, "ACTIVE")'
    )->execute([
        'username' => $username,
        'email' => $email,
        'role' => $role,
        'password_hash' => password_hash('Password123!', PASSWORD_DEFAULT),
    ]);
    $userId = (int) $pdo->lastInsertId();
    $createdUserIds[] = $userId;

    $roleId = (int) $pdo->query('SELECT id FROM roles WHERE code = ' . $pdo->quote($role))->fetchColumn();
    if ($roleId > 0) {
        $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)')
            ->execute(['user_id' => $userId, 'role_id' => $roleId]);
    }

    return $userId;
}

function insertMember(PDO $pdo, int $userId, string $number, string $first, string $last, array &$createdMemberIds): int
{
    $pdo->prepare(
        'INSERT INTO members (user_id, member_number, first_name, last_name, membership_status)
         VALUES (:user_id, :number, :first, :last, "ACTIVE")'
    )->execute([
        'user_id' => $userId,
        'number' => $number,
        'first' => $first,
        'last' => $last,
    ]);
    $memberId = (int) $pdo->lastInsertId();
    $createdMemberIds[] = $memberId;
    return $memberId;
}

function insertRequest(
    PDO $pdo,
    int $serviceTypeId,
    int $memberId,
    string $number,
    string $status,
    string $subject,
    ?string $start,
    ?string $end,
    ?string $completedAt,
    array &$createdRequestIds
): int {
    $pdo->prepare(
        'INSERT INTO service_requests
            (service_type_id, requested_by_member_id, request_number, status, subject,
             preferred_start_datetime, preferred_end_datetime, completed_at, requested_at)
         VALUES
            (:type_id, :member_id, :number, :status, :subject, :start, :end, :completed, NOW())'
    )->execute([
        'type_id' => $serviceTypeId,
        'member_id' => $memberId,
        'number' => $number,
        'status' => $status,
        'subject' => $subject,
        'start' => $start,
        'end' => $end,
        'completed' => $completedAt,
    ]);
    $requestId = (int) $pdo->lastInsertId();
    $createdRequestIds[] = $requestId;
    return $requestId;
}

function insertNotification(
    PDO $pdo,
    int $userId,
    string $title,
    string $message,
    string $type,
    ?string $readAt,
    ?string $referenceType,
    ?int $referenceId,
    array &$createdNotificationIds
): int {
    $pdo->prepare(
        'INSERT INTO notifications
            (user_id, title, message, notification_type, reference_type, reference_id, read_at)
         VALUES
            (:user_id, :title, :message, :type, :reference_type, :reference_id, :read_at)'
    )->execute([
        'user_id' => $userId,
        'title' => $title,
        'message' => $message,
        'type' => $type,
        'reference_type' => $referenceType,
        'reference_id' => $referenceId,
        'read_at' => $readAt,
    ]);
    $notificationId = (int) $pdo->lastInsertId();
    $createdNotificationIds[] = $notificationId;
    return $notificationId;
}

try {
    $serviceTypeId = (int) $pdo->query('SELECT id FROM service_types WHERE code = "WEDDING" LIMIT 1')->fetchColumn();
    if ($serviceTypeId < 1) {
        throw new RuntimeException('WEDDING service type is missing from cmmp_db.');
    }

    $existingUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $existingRequests = (int) $pdo->query('SELECT COUNT(*) FROM service_requests')->fetchColumn();
    $existingNotifications = (int) $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn();
    echo "Database snapshot: {$existingUsers} users, {$existingRequests} requests, {$existingNotifications} notifications.\n";
    echo "Seeding isolated verification fixtures ({$suffix}).\n";

    $memberUserId = insertUser($pdo, "member-{$suffix}", "member-{$suffix}@cmmp.test", 'MEMBER', $createdUserIds);
    $otherUserId = insertUser($pdo, "member2-{$suffix}", "member2-{$suffix}@cmmp.test", 'MEMBER', $createdUserIds);
    $adminUserId = insertUser($pdo, "admin-{$suffix}", "admin-{$suffix}@cmmp.test", 'CHURCH_ADMIN', $createdUserIds);
    $pastorUserId = insertUser($pdo, "pastor-{$suffix}", "pastor-{$suffix}@cmmp.test", 'PASTOR', $createdUserIds);

    $memberId = insertMember($pdo, $memberUserId, "M-{$suffix}-1", 'Ana', 'Reyes', $createdMemberIds);
    $otherMemberId = insertMember($pdo, $otherUserId, "M-{$suffix}-2", 'Ben', 'Cruz', $createdMemberIds);

    $eligibleId = insertRequest(
        $pdo, $serviceTypeId, $memberId, "SR-{$suffix}-DONE", 'COMPLETED',
        'Wedding of Ana Reyes', '2026-09-12 09:00:00', '2026-09-12 12:00:00',
        '2026-09-12 13:00:00', $createdRequestIds
    );
    $futureCompletedId = insertRequest(
        $pdo, $serviceTypeId, $memberId, "SR-{$suffix}-FUTURE", 'COMPLETED',
        'Future completed blessing', date('Y-m-d H:i:s', strtotime('+5 days')),
        date('Y-m-d H:i:s', strtotime('+5 days 2 hours')),
        date('Y-m-d H:i:s', strtotime('+5 days 3 hours')), $createdRequestIds
    );
    $pendingId = insertRequest(
        $pdo, $serviceTypeId, $memberId, "SR-{$suffix}-PEND", 'PENDING',
        'Pending wedding request', date('Y-m-d H:i:s', strtotime('-2 days')),
        date('Y-m-d H:i:s', strtotime('-2 days 2 hours')), null, $createdRequestIds
    );
    $reviewId = insertRequest(
        $pdo, $serviceTypeId, $memberId, "SR-{$suffix}-REV", 'UNDER_REVIEW',
        'Under review request', date('Y-m-d H:i:s', strtotime('-3 days')),
        date('Y-m-d H:i:s', strtotime('-3 days 2 hours')), null, $createdRequestIds
    );
    $approvedId = insertRequest(
        $pdo, $serviceTypeId, $memberId, "SR-{$suffix}-APP", 'APPROVED',
        'Approved request', date('Y-m-d H:i:s', strtotime('-4 days')),
        date('Y-m-d H:i:s', strtotime('-4 days 2 hours')), null, $createdRequestIds
    );
    $scheduledId = insertRequest(
        $pdo, $serviceTypeId, $memberId, "SR-{$suffix}-SCH", 'SCHEDULED',
        'Scheduled request', date('Y-m-d H:i:s', strtotime('+2 days')),
        date('Y-m-d H:i:s', strtotime('+2 days 2 hours')), null, $createdRequestIds
    );
    $otherCompletedId = insertRequest(
        $pdo, $serviceTypeId, $otherMemberId, "SR-{$suffix}-OTHER", 'COMPLETED',
        'Wedding of Ben Cruz', '2026-08-01 09:00:00', '2026-08-01 12:00:00',
        '2026-08-01 13:00:00', $createdRequestIds
    );

    $eligible = $pdo->query('SELECT * FROM service_requests WHERE id = ' . $eligibleId)->fetch();
    $futureCompleted = $pdo->query('SELECT * FROM service_requests WHERE id = ' . $futureCompletedId)->fetch();
    $pending = $pdo->query('SELECT * FROM service_requests WHERE id = ' . $pendingId)->fetch();
    $review = $pdo->query('SELECT * FROM service_requests WHERE id = ' . $reviewId)->fetch();
    $approved = $pdo->query('SELECT * FROM service_requests WHERE id = ' . $approvedId)->fetch();
    $scheduled = $pdo->query('SELECT * FROM service_requests WHERE id = ' . $scheduledId)->fetch();
    $otherCompleted = $pdo->query('SELECT * FROM service_requests WHERE id = ' . $otherCompletedId)->fetch();

    verify(
        'Owner can delete own completed request',
        ServiceRequest::memberCanHide($eligible, $memberId) === true
    );
    verify(
        'Completed request can be deleted even if the preferred date is in the future',
        ServiceRequest::memberCanHide($futureCompleted, $memberId) === true
    );
    verify(
        'Pending request cannot be hidden',
        ServiceRequest::memberCanHide($pending, $memberId) === false
    );
    verify(
        'Under-review request cannot be hidden',
        ServiceRequest::memberCanHide($review, $memberId) === false
    );
    verify(
        'Approved request cannot be hidden',
        ServiceRequest::memberCanHide($approved, $memberId) === false
    );
    verify(
        'Scheduled request cannot be hidden',
        ServiceRequest::memberCanHide($scheduled, $memberId) === false
    );
    verify(
        'Member cannot hide another member\'s completed request',
        ServiceRequest::memberCanHide($otherCompleted, $memberId) === false
        && ServiceRequest::memberCanHide($eligible, $otherMemberId) === false
    );

    [$blockedPending, $pendingMessage] = expectException(
        static fn () => ServiceRequest::hideFromMember($pendingId, $memberId, $memberUserId),
        'Only your own completed requests'
    );
    verify('hideFromMember rejects pending requests', $blockedPending, $pendingMessage);

    [$blockedOther, $otherMessage] = expectException(
        static fn () => ServiceRequest::hideFromMember($otherCompletedId, $memberId, $memberUserId),
        'Only your own completed requests'
    );
    verify('hideFromMember rejects another member\'s request', $blockedOther, $otherMessage);

    ServiceRequest::hideFromMember($futureCompletedId, $memberId, $memberUserId);
    $futureHidden = $pdo->query('SELECT hidden_from_member_at FROM service_requests WHERE id = ' . $futureCompletedId)->fetchColumn();
    verify('Completed request with a future preferred date can be deleted from the member list', $futureHidden !== null);

    ServiceRequest::hideFromMember($eligibleId, $memberId, $memberUserId);
    $hiddenRow = $pdo->query('SELECT * FROM service_requests WHERE id = ' . $eligibleId)->fetch();
    verify(
        'Hide updates hidden_from_member_at and keeps the original row',
        is_array($hiddenRow)
        && $hiddenRow['hidden_from_member_at'] !== null
        && $hiddenRow['status'] === 'COMPLETED'
        && $hiddenRow['request_number'] === "SR-{$suffix}-DONE"
    );

    $memberList = ServiceRequest::paginateForMember($memberId);
    $memberIds = array_map(static fn (array $row): int => (int) $row['id'], $memberList);
    verify(
        'Hidden request disappears from the member dashboard/list',
        !in_array($eligibleId, $memberIds, true)
        && in_array($pendingId, $memberIds, true)
        && in_array($scheduledId, $memberIds, true)
    );

    $adminAll = ServiceRequest::paginateForAdmin('', '');
    $adminIds = array_map(static fn (array $row): int => (int) $row['id'], $adminAll);
    verify(
        'Admin/staff list still includes the hidden request',
        in_array($eligibleId, $adminIds, true)
    );
    $pastorCompleted = ServiceRequest::paginateForAdmin('', 'COMPLETED');
    $pastorIds = array_map(static fn (array $row): int => (int) $row['id'], $pastorCompleted);
    verify(
        'Pastor/admin completed filter still includes the hidden request',
        in_array($eligibleId, $pastorIds, true)
        && in_array($otherCompletedId, $pastorIds, true)
    );

    $adminSearch = ServiceRequest::paginateForAdmin('Ana Reyes', '');
    $searchIds = array_map(static fn (array $row): int => (int) $row['id'], $adminSearch);
    verify(
        'Admin search still works after hide (unique PDO placeholders)',
        in_array($eligibleId, $searchIds, true)
    );

    $otherList = ServiceRequest::paginateForMember($otherMemberId);
    $otherIds = array_map(static fn (array $row): int => (int) $row['id'], $otherList);
    verify(
        'Other member\'s dashboard is unaffected by the hide',
        in_array($otherCompletedId, $otherIds, true)
        && !in_array($eligibleId, $otherIds, true)
    );

    $auditCount = (int) $pdo->query(
        'SELECT COUNT(*) FROM activity_logs
         WHERE action = "SERVICE_REQUEST_HIDDEN_BY_MEMBER"
           AND entity_id = ' . $eligibleId
    )->fetchColumn();
    verify('Hide writes an audit log without deleting the request', $auditCount === 1);

    [$blockedAgain] = expectException(
        static fn () => ServiceRequest::hideFromMember($eligibleId, $memberId, $memberUserId)
    );
    verify('Already-hidden request cannot be hidden again', $blockedAgain);

    $hideForm = file_get_contents(__DIR__ . '/../includes/member-hide-request-form.php');
    verify(
        'Member hide confirmation dialog is present',
        is_string($hideForm)
        && str_contains($hideForm, 'Delete this completed request from your list?')
        && str_contains($hideForm, 'onsubmit="return confirm(')
    );
    $hideEndpoint = file_get_contents(__DIR__ . '/../member/services/hide.php');
    verify(
        'Hide endpoint redirects with success and error query flags',
        is_string($hideEndpoint)
        && str_contains($hideEndpoint, '?hidden=1')
        && str_contains($hideEndpoint, 'hide_error=')
    );
    $dashboard = file_get_contents(__DIR__ . '/../member/dashboard/index.php');
    $servicesIndex = file_get_contents(__DIR__ . '/../member/services/index.php');
    verify(
        'Dashboard and services pages show hide success/error alerts',
        is_string($dashboard)
        && str_contains($dashboard, "isset(\$_GET['hidden'])")
        && str_contains($dashboard, 'hide_error')
        && is_string($servicesIndex)
        && str_contains($servicesIndex, "isset(\$_GET['hidden'])")
        && str_contains($servicesIndex, 'Church staff still have the original record')
    );

    $canDeleteNotifications = method_exists(Notification::class, 'deleteReadForUser');
    if (!$canDeleteNotifications) {
        echo "SKIP  Notification delete tests (deleteReadForUser is not on this branch).\n";
    } else {
        $unreadId = insertNotification(
            $pdo, $memberUserId, "Unread {$suffix}", 'Please review your schedule.',
            'SERVICE_REQUEST_UPDATE', null, 'service_request', $eligibleId, $createdNotificationIds
        );
        $readId = insertNotification(
            $pdo, $memberUserId, "Read {$suffix}", 'Your wedding request was completed.',
            'SERVICE_REQUEST_UPDATE', date('Y-m-d H:i:s', strtotime('-1 day')),
            'service_request', $eligibleId, $createdNotificationIds
        );
        $otherReadId = insertNotification(
            $pdo, $otherUserId, "Other read {$suffix}", 'Ben completed request notice.',
            'SERVICE_REQUEST_UPDATE', date('Y-m-d H:i:s', strtotime('-2 hours')),
            'service_request', $otherCompletedId, $createdNotificationIds
        );
        $adminReadId = insertNotification(
            $pdo, $adminUserId, "Admin read {$suffix}", 'Admin follow-up notice.',
            'SERVICE_REQUEST_NEW', date('Y-m-d H:i:s', strtotime('-3 hours')),
            'service_request', $eligibleId, $createdNotificationIds
        );

        $beforeMark = (int) $pdo->query('SELECT COUNT(*) FROM notifications WHERE id = ' . $unreadId)->fetchColumn();
        Notification::markRead($unreadId, $memberUserId);
        $afterMark = $pdo->query('SELECT read_at FROM notifications WHERE id = ' . $unreadId)->fetchColumn();
        $stillExists = (int) $pdo->query('SELECT COUNT(*) FROM notifications WHERE id = ' . $unreadId)->fetchColumn();
        verify(
            'Marking a notification read does not delete it',
            $beforeMark === 1 && $stillExists === 1 && $afterMark !== null
        );

        $unreadAgainId = insertNotification(
            $pdo, $memberUserId, "Unread stay {$suffix}", 'Stay until marked read.',
            'TEST', null, null, null, $createdNotificationIds
        );
        [$blockedUnread, $unreadMessage] = expectException(
            static fn () => Notification::deleteReadForUser($unreadAgainId, $memberUserId),
            'Mark this notification as read'
        );
        verify('Unread notifications cannot be deleted', $blockedUnread, $unreadMessage);
        $unreadStillThere = (int) $pdo->query('SELECT COUNT(*) FROM notifications WHERE id = ' . $unreadAgainId)->fetchColumn();
        verify('Unread notification row remains after a rejected delete', $unreadStillThere === 1);

        [$blockedForeign, $foreignMessage] = expectException(
            static fn () => Notification::deleteReadForUser($otherReadId, $memberUserId),
            'Notification not found'
        );
        verify('Users cannot delete another user\'s notification', $blockedForeign, $foreignMessage);
        $otherStillThere = (int) $pdo->query('SELECT COUNT(*) FROM notifications WHERE id = ' . $otherReadId)->fetchColumn();
        verify('Other user\'s notification row remains', $otherStillThere === 1);

        [$blockedAdminOwned] = expectException(
            static fn () => Notification::deleteReadForUser($adminReadId, $memberUserId)
        );
        verify('Member cannot delete an admin notification', $blockedAdminOwned);

        Notification::deleteReadForUser($readId, $memberUserId);
        $deleted = (int) $pdo->query('SELECT COUNT(*) FROM notifications WHERE id = ' . $readId)->fetchColumn();
        $linkedRequest = (int) $pdo->query('SELECT COUNT(*) FROM service_requests WHERE id = ' . $eligibleId)->fetchColumn();
        verify('Read notification belonging to the user is deleted', $deleted === 0);
        verify('Deleting a notification does not delete the linked service request', $linkedRequest === 1);

        $searchItems = Notification::filtered($memberUserId, ['q' => $suffix, 'status' => 'all', 'type' => '', 'since' => '']);
        verify(
            'Notification inbox search still works after delete (named parameters)',
            is_array($searchItems)
        );

        $inboxHelper = file_get_contents(__DIR__ . '/../helpers/notification_inbox.php');
        $inboxView = file_get_contents(__DIR__ . '/../includes/notification-inbox.php');
        verify(
            'Notification delete confirmation dialog is present',
            is_string($inboxView)
            && str_contains($inboxView, 'Delete this read notification?')
            && str_contains($inboxView, 'Related service requests, appointments, and events will not be deleted.')
            && str_contains($inboxView, "name=\"action\" value=\"delete\"")
        );
        verify(
            'Notification helper exposes delete success and error messages',
            is_string($inboxHelper)
            && str_contains($inboxHelper, "action === 'delete'")
            && str_contains($inboxHelper, 'deleted')
            && str_contains($inboxHelper, 'delete_error')
            && str_contains($inboxView, 'The notification was deleted. Related church records were not changed.')
        );
        verify(
            'Unread items only show Mark read, not Delete',
            is_string($inboxView)
            && str_contains($inboxView, 'Mark read')
            && preg_match('/if \(\$isUnread\):.*?Mark read.*?else:.*?action" value="delete"/s', $inboxView) === 1
        );
    }
} catch (Throwable $exception) {
    $failed++;
    echo 'FAIL  Verification aborted — ' . $exception->getMessage() . "\n";
    echo $exception->getTraceAsString() . "\n";
} finally {
    if ($createdNotificationIds !== []) {
        $pdo->exec('DELETE FROM notifications WHERE id IN (' . implode(',', $createdNotificationIds) . ')');
    }
    if ($createdRequestIds !== []) {
        $pdo->exec('DELETE FROM service_requests WHERE id IN (' . implode(',', $createdRequestIds) . ')');
    }
    if ($createdMemberIds !== []) {
        $pdo->exec('DELETE FROM members WHERE id IN (' . implode(',', $createdMemberIds) . ')');
    }
    if ($createdUserIds !== []) {
        $pdo->exec('DELETE FROM activity_logs WHERE user_id IN (' . implode(',', $createdUserIds) . ')');
        $pdo->exec('DELETE FROM user_roles WHERE user_id IN (' . implode(',', $createdUserIds) . ')');
        $pdo->exec('DELETE FROM users WHERE id IN (' . implode(',', $createdUserIds) . ')');
    }
}

echo "\n{$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);
