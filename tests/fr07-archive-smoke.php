<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Member.php';
require_once __DIR__ . '/../models/Personnel.php';
require_once __DIR__ . '/../models/Ministry.php';
require_once __DIR__ . '/../models/Announcement.php';
require_once __DIR__ . '/../models/ServiceRequest.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../models/Facility.php';
require_once __DIR__ . '/../models/Event.php';
require_once __DIR__ . '/../models/AuditLog.php';

$passed = 0;
$failed = 0;

function fr07_ok(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "PASS  {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
        return;
    }
    $failed++;
    echo "FAIL  {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
}

function fr07_throws(callable $callback, string $needle = ''): array
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
$suffix = strtoupper(bin2hex(random_bytes(3)));
$cleanup = [
    'notifications' => [],
    'service_requests' => [],
    'announcements' => [],
    'ministries' => [],
    'pastors' => [],
    'staff' => [],
    'members' => [],
    'users' => [],
    'facilities' => [],
    'activity_logs' => [],
];

function fr07_user(PDO $pdo, string $username, string $email, string $role, string $status, array &$cleanup): int
{
    $pdo->prepare(
        'INSERT INTO users (username, email, role, password_hash, status)
         VALUES (:username, :email, :role, :password_hash, :status)'
    )->execute([
        'username' => $username,
        'email' => $email,
        'role' => $role,
        'password_hash' => password_hash('Password123!', PASSWORD_DEFAULT),
        'status' => $status,
    ]);
    $id = (int) $pdo->lastInsertId();
    $cleanup['users'][] = $id;
    $roleId = (int) $pdo->query('SELECT id FROM roles WHERE code = ' . $pdo->quote($role))->fetchColumn();
    if ($roleId > 0) {
        $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)')
            ->execute(['user_id' => $id, 'role_id' => $roleId]);
    }
    return $id;
}

try {
    $adminId = fr07_user($pdo, "fr07-admin-{$suffix}", "fr07-admin-{$suffix}@cmmp.test", 'CHURCH_ADMIN', 'ACTIVE', $cleanup);
    $staffUserId = fr07_user($pdo, "fr07-staff-{$suffix}", "fr07-staff-{$suffix}@cmmp.test", 'STAFF', 'ACTIVE', $cleanup);
    $pastorUserId = fr07_user($pdo, "fr07-pastor-{$suffix}", "fr07-pastor-{$suffix}@cmmp.test", 'PASTOR', 'ACTIVE', $cleanup);
    $memberUserId = fr07_user($pdo, "fr07-member-{$suffix}", "fr07-member-{$suffix}@cmmp.test", 'MEMBER', 'ACTIVE', $cleanup);
    $unusedUserId = fr07_user($pdo, "fr07-unused-{$suffix}", "fr07-unused-{$suffix}@cmmp.test", 'MEMBER', 'PENDING', $cleanup);

    $memberId = Member::create([
        'user_id' => $memberUserId,
        'member_number' => 'FR07-' . $suffix,
        'first_name' => 'Archive',
        'middle_name' => null,
        'last_name' => 'Tester',
        'suffix' => null,
        'date_of_birth' => '1990-01-01',
        'gender' => 'Female',
        'phone' => null,
        'email' => "fr07-member-{$suffix}@cmmp.test",
        'address_line_1' => null,
        'address_line_2' => null,
        'city' => null,
        'state_province' => null,
        'postal_code' => null,
        'country' => 'Philippines',
        'baptism_date' => null,
        'baptism_church' => null,
        'baptism_notes' => null,
        'emergency_contact_name' => null,
        'emergency_contact_relationship' => null,
        'emergency_contact_phone' => null,
        'profile_photo' => null,
        'membership_status' => 'ACTIVE',
        'joined_at' => null,
    ]);
    $cleanup['members'][] = $memberId;

    $staffId = Personnel::create([
        'user_id' => $staffUserId,
        'member_id' => null,
        'employee_number' => 'ST-' . $suffix,
        'position_title' => 'Secretary',
        'employment_status' => 'ACTIVE',
        'hired_at' => null,
        'personnel_type' => 'STAFF',
    ]);
    $cleanup['staff'][] = $staffId;

    $pastorStaffId = Personnel::create([
        'user_id' => $pastorUserId,
        'member_id' => null,
        'employee_number' => 'PA-' . $suffix,
        'position_title' => 'Pastor',
        'employment_status' => 'ACTIVE',
        'hired_at' => null,
        'personnel_type' => 'PASTOR',
        'ordination_date' => '2018-01-01',
    ]);
    $cleanup['staff'][] = $pastorStaffId;
    $cleanup['pastors'][] = $pastorStaffId;

    Personnel::deactivate($pastorStaffId, $adminId);
    $inactivePastor = Personnel::find($pastorStaffId);
    fr07_ok('Pastors can be deactivated', ($inactivePastor['employment_status'] ?? '') === 'INACTIVE');
    [$blockedAssign, $assignMessage] = fr07_throws(
        static fn () => Personnel::assertAssignable($pastorStaffId),
        'Inactive personnel cannot receive new assignments'
    );
    fr07_ok('Inactive pastor is blocked from new assignments', $blockedAssign, $assignMessage);

    $activeOnly = Personnel::paginate('PASTOR', 1, 50, 'ACTIVE');
    $inactiveOnly = Personnel::paginate('PASTOR', 1, 50, 'INACTIVE');
    $activeIds = array_map(static fn (array $row): int => (int) $row['id'], $activeOnly['items']);
    $inactiveIds = array_map(static fn (array $row): int => (int) $row['id'], $inactiveOnly['items']);
    fr07_ok('Inactive pastor is hidden from the active filter', !in_array($pastorStaffId, $activeIds, true));
    fr07_ok('Inactive pastor appears in the archived/inactive filter', in_array($pastorStaffId, $inactiveIds, true));

    Personnel::reactivate($pastorStaffId, $adminId);
    fr07_ok('Pastor can be reactivated', Personnel::find($pastorStaffId)['employment_status'] === 'ACTIVE');
    Personnel::assertAssignable($pastorStaffId);
    fr07_ok('Reactivated pastor can receive assignments again', true);

    Personnel::deactivate($staffId, $adminId);
    fr07_ok('Staff can be deactivated', Personnel::find($staffId)['employment_status'] === 'INACTIVE');
    Personnel::reactivate($staffId, $adminId);

    Member::setStatus($memberId, 'INACTIVE', $adminId);
    [$blockedRequest] = fr07_throws(
        static fn () => Member::assertCanCreateRequests($memberId),
        'Inactive members cannot submit new service requests'
    );
    fr07_ok('Inactive members cannot create new requests', $blockedRequest);
    [$blockedEvent] = fr07_throws(
        static fn () => Member::assertActive($memberId, 'Inactive members cannot register for events.'),
        'Inactive members cannot register for events'
    );
    fr07_ok('Inactive members cannot register for events', $blockedEvent);
    Member::setStatus($memberId, 'ACTIVE', $adminId);
    Member::assertCanCreateRequests($memberId);
    fr07_ok('Reactivated members can create requests again', true);

    $ministryId = Ministry::create([
        'name' => 'FR07 Ministry ' . $suffix,
        'code' => 'FR07-' . $suffix,
        'description' => 'Archive test',
        'leader_staff_id' => null,
    ]);
    $cleanup['ministries'][] = $ministryId;
    Ministry::setStatus($ministryId, 'INACTIVE', $adminId);
    fr07_ok('Ministry can be deactivated', Ministry::find($ministryId)['ministry_status'] === 'INACTIVE');
    $inactiveMinistries = Ministry::paginateAdmin(1, 50, 'INACTIVE');
    $inactiveMinistryIds = array_map(static fn (array $row): int => (int) $row['id'], $inactiveMinistries['items']);
    fr07_ok('Inactive ministries can be filtered', in_array($ministryId, $inactiveMinistryIds, true));
    Ministry::setStatus($ministryId, 'ACTIVE', $adminId);
    fr07_ok('Ministry can be reactivated', Ministry::find($ministryId)['ministry_status'] === 'ACTIVE');

    Personnel::deactivate($staffId, $adminId);
    [$blockedLeader] = fr07_throws(
        static fn () => Ministry::assertStaffCanLeadMinistry($staffId, $ministryId),
        'Inactive personnel cannot be assigned as ministry leaders'
    );
    fr07_ok('Inactive staff cannot be assigned as ministry leaders', $blockedLeader);
    Personnel::reactivate($staffId, $adminId);

    $announcementId = Announcement::save([
        'user_id' => $adminId,
        'title' => 'FR07 notice ' . $suffix,
        'body' => 'Archive restore test',
        'audience_role' => 'ALL',
        'published_at' => date('Y-m-d H:i:s'),
        'expires_at' => null,
        'status' => 'PUBLISHED',
    ]);
    $cleanup['announcements'][] = $announcementId;
    $visibleNow = array_map(static fn (array $row): int => (int) $row['id'], Announcement::listForUser(['MEMBER']));
    fr07_ok('Published announcement appears in the member feed', in_array($announcementId, $visibleNow, true));
    Announcement::archive($announcementId, $adminId);
    $archived = $pdo->query('SELECT announcement_status FROM announcements WHERE id = ' . $announcementId)->fetchColumn();
    fr07_ok('Announcement can be archived', $archived === 'ARCHIVED');
    $visible = Announcement::listForUser(['MEMBER']);
    $visibleIds = array_map(static fn (array $row): int => (int) $row['id'], $visible);
    fr07_ok('Archived announcement is hidden from members', !in_array($announcementId, $visibleIds, true));
    Announcement::restore($announcementId, $adminId);
    $restored = $pdo->query('SELECT announcement_status FROM announcements WHERE id = ' . $announcementId)->fetchColumn();
    fr07_ok('Announcement can be restored', $restored === 'PUBLISHED');
    $visibleRestored = array_map(static fn (array $row): int => (int) $row['id'], Announcement::listForUser(['MEMBER']));
    fr07_ok('Restored announcement returns to the member feed', in_array($announcementId, $visibleRestored, true));

    $scheduledId = Announcement::save([
        'user_id' => $adminId,
        'title' => 'FR07 scheduled ' . $suffix,
        'body' => 'Future publication',
        'audience_role' => 'ALL',
        'published_at' => app_now()->modify('+2 hours')->format('Y-m-d H:i:s'),
        'expires_at' => null,
        'status' => 'PUBLISHED',
    ]);
    $cleanup['announcements'][] = $scheduledId;
    Announcement::archive($scheduledId, $adminId);
    Announcement::restore($scheduledId, $adminId);
    $visibleScheduled = array_map(static fn (array $row): int => (int) $row['id'], Announcement::listForUser(['MEMBER']));
    fr07_ok('Restored future-scheduled announcement stays hidden', !in_array($scheduledId, $visibleScheduled, true));

    $serviceTypeId = (int) $pdo->query('SELECT id FROM service_types WHERE is_active = 1 LIMIT 1')->fetchColumn();
    $pdo->prepare(
        'INSERT INTO service_requests (service_type_id, requested_by_member_id, request_number, status, subject)
         VALUES (:type_id, :member_id, :number, "PENDING_ASSIGNMENT", :subject)'
    )->execute([
        'type_id' => $serviceTypeId,
        'member_id' => $memberId,
        'number' => 'FR07-' . $suffix . '-NEW',
        'subject' => 'Unused request',
    ]);
    $freshRequestId = (int) $pdo->lastInsertId();
    $cleanup['service_requests'][] = $freshRequestId;
    fr07_ok('Unused pending request can be permanently deleted', ServiceRequest::canPermanentlyDelete(ServiceRequest::find($freshRequestId)));
    ServiceRequest::deletePermanently($freshRequestId, $adminId);
    $gone = (int) $pdo->query('SELECT COUNT(*) FROM service_requests WHERE id = ' . $freshRequestId)->fetchColumn();
    fr07_ok('Permanent delete removes only the unused request', $gone === 0);
    $cleanup['service_requests'] = array_values(array_filter($cleanup['service_requests'], static fn (int $id): bool => $id !== $freshRequestId));

    $pdo->prepare(
        'INSERT INTO service_requests (service_type_id, requested_by_member_id, request_number, status, subject)
         VALUES (:type_id, :member_id, :number, "ASSIGNED", :subject)'
    )->execute([
        'type_id' => $serviceTypeId,
        'member_id' => $memberId,
        'number' => 'FR07-' . $suffix . '-ASG',
        'subject' => 'Assigned request',
    ]);
    $assignedRequestId = (int) $pdo->lastInsertId();
    $cleanup['service_requests'][] = $assignedRequestId;
    $pdo->prepare(
        'INSERT INTO service_assignments (service_request_id, staff_id, assignment_role, assigned_by_user_id)
         VALUES (:request_id, :staff_id, "PASTOR", :user_id)'
    )->execute(['request_id' => $assignedRequestId, 'staff_id' => $pastorStaffId, 'user_id' => $adminId]);
    [$blockedHardDelete] = fr07_throws(
        static fn () => ServiceRequest::deletePermanently($assignedRequestId, $adminId),
        'can only be cancelled'
    );
    fr07_ok('Assigned requests cannot be hard-deleted', $blockedHardDelete);
    ServiceRequest::archive($assignedRequestId, $adminId);
    $cancelled = ServiceRequest::find($assignedRequestId);
    fr07_ok('Assigned request is cancelled instead of deleted', ($cancelled['status'] ?? '') === 'CANCELLED');
    $assignmentStillThere = (int) $pdo->query('SELECT COUNT(*) FROM service_assignments WHERE service_request_id = ' . $assignedRequestId)->fetchColumn();
    fr07_ok('Cancelling a request keeps assignment history', $assignmentStillThere === 1);

    Personnel::deactivate($pastorStaffId, $adminId);
    [$blockedServiceAssign] = fr07_throws(
        static fn () => ServiceRequest::assign($assignedRequestId, $pastorStaffId, 'PASTOR', $adminId),
        'Inactive personnel cannot receive new assignments'
    );
    fr07_ok('Service assignment backend blocks inactive pastors', $blockedServiceAssign);
    Personnel::reactivate($pastorStaffId, $adminId);

    $readId = 0;
    $unreadId = 0;
    Notification::create($memberUserId, 'Read FR07', 'Read notice', 'TEST');
    $readId = (int) $pdo->query('SELECT id FROM notifications WHERE user_id = ' . $memberUserId . ' ORDER BY id DESC LIMIT 1')->fetchColumn();
    $cleanup['notifications'][] = $readId;
    Notification::markRead($readId, $memberUserId);
    Notification::create($memberUserId, 'Unread FR07', 'Unread notice', 'TEST');
    $unreadId = (int) $pdo->query('SELECT id FROM notifications WHERE user_id = ' . $memberUserId . ' ORDER BY id DESC LIMIT 1')->fetchColumn();
    $cleanup['notifications'][] = $unreadId;
    [$blockedUnread] = fr07_throws(
        static fn () => Notification::deleteForUser($unreadId, $memberUserId),
        'Only read notifications can be deleted'
    );
    fr07_ok('Unread notification delete is rejected', $blockedUnread);
    [$blockedForeign] = fr07_throws(
        static fn () => Notification::deleteForUser($readId, $adminId),
        'Notification not found'
    );
    fr07_ok('Users cannot delete another user\'s notification', $blockedForeign);
    Notification::deleteForUser($readId, $memberUserId);
    $readGone = (int) $pdo->query('SELECT COUNT(*) FROM notifications WHERE id = ' . $readId)->fetchColumn();
    $requestStillThere = (int) $pdo->query('SELECT COUNT(*) FROM service_requests WHERE id = ' . $assignedRequestId)->fetchColumn();
    fr07_ok('Owner can delete a read notification', $readGone === 0);
    fr07_ok('Notification delete does not remove the service request', $requestStillThere === 1);

    User::deactivate($staffUserId, $adminId);
    fr07_ok('User account can be deactivated', User::find($staffUserId)['status'] === 'INACTIVE');
    [$blockedSelf] = fr07_throws(
        static fn () => User::deactivate($adminId, $adminId),
        'You cannot deactivate your own account'
    );
    fr07_ok('Users cannot deactivate themselves', $blockedSelf);
    [$blockedLinkedDelete] = fr07_throws(
        static fn () => User::deletePermanently($staffUserId, $adminId),
        'can only be deactivated'
    );
    fr07_ok('Users with staff records cannot be hard-deleted', $blockedLinkedDelete);
    User::reactivate($staffUserId, $adminId);
    fr07_ok('User account can be reactivated', User::find($staffUserId)['status'] === 'ACTIVE');
    fr07_ok('Unused pending accounts can be permanently deleted', User::canPermanentlyDelete($unusedUserId));
    User::deletePermanently($unusedUserId, $adminId);
    $unusedGone = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE id = ' . $unusedUserId)->fetchColumn();
    fr07_ok('Unused pending account is permanently deleted', $unusedGone === 0);
    $cleanup['users'] = array_values(array_filter($cleanup['users'], static fn (int $id): bool => $id !== $unusedUserId));

    $facilityId = Facility::create([
        'name' => 'FR07 Hall ' . $suffix,
        'code' => 'FR07-' . $suffix,
        'description' => 'Archive test',
        'capacity' => 20,
        'location' => 'Test',
        'facility_type' => 'OTHER',
        'facility_status' => 'AVAILABLE',
    ]);
    $cleanup['facilities'][] = $facilityId;
    Facility::setStatus($facilityId, 'UNAVAILABLE', $adminId);
    fr07_ok('Facility can be deactivated', Facility::find($facilityId)['facility_status'] === 'UNAVAILABLE');
    Facility::setStatus($facilityId, 'AVAILABLE', $adminId);
    fr07_ok('Facility can be reactivated', Facility::find($facilityId)['facility_status'] === 'AVAILABLE');

    $deactivatePhp = file_get_contents(__DIR__ . '/../admin/staff/deactivate.php');
    fr07_ok(
        'Personnel deactivate endpoint now handles clergy and reactivation',
        is_string($deactivatePhp)
        && str_contains($deactivatePhp, "action === 'reactivate'")
        && !str_contains($deactivatePhp, '!Personnel::isClergy')
    );
    $userIndex = file_get_contents(__DIR__ . '/../admin/users/index.php');
    fr07_ok(
        'User directory no longer hard-deletes every account',
        is_string($userIndex)
        && !str_contains($userIndex, 'DELETE FROM users WHERE id')
        && str_contains($userIndex, 'User::deactivate')
        && str_contains($userIndex, '$canArchiveUsers')
    );
    $serviceDelete = file_get_contents(__DIR__ . '/../admin/services/delete.php');
    fr07_ok(
        'Service request delete endpoint prefers archive over cascade delete',
        is_string($serviceDelete)
        && str_contains($serviceDelete, 'ServiceRequest::archive')
        && str_contains($serviceDelete, 'ServiceRequest::deletePermanently')
    );
    $inbox = file_get_contents(__DIR__ . '/../includes/notification-inbox.php');
    fr07_ok(
        'Notification inbox delete is limited to read items',
        is_string($inbox)
        && str_contains($inbox, 'Delete all read')
        && str_contains($inbox, 'Only read notifications can be deleted') === false
        && str_contains($inbox, 'name="delete_one"')
    );

    $auditActions = $pdo->query(
        'SELECT DISTINCT action FROM activity_logs WHERE action IN (
            "PERSONNEL_DEACTIVATED", "PERSONNEL_REACTIVATED", "ANNOUNCEMENT_ARCHIVED",
            "ANNOUNCEMENT_RESTORED", "SERVICE_REQUEST_ARCHIVED", "SERVICE_REQUEST_DELETED",
            "MEMBER_DEACTIVATED", "MEMBER_REACTIVATED", "USER_DEACTIVATED", "USER_REACTIVATED"
        )'
    )->fetchAll(PDO::FETCH_COLUMN);
    fr07_ok('Archive and restore actions write audit logs', count($auditActions) >= 6, implode(', ', $auditActions));
} catch (Throwable $exception) {
    $failed++;
    echo 'FAIL  FR-07 verification aborted — ' . $exception->getMessage() . "\n";
    echo $exception->getTraceAsString() . "\n";
} finally {
    if ($cleanup['notifications'] !== []) {
        $pdo->exec('DELETE FROM notifications WHERE id IN (' . implode(',', $cleanup['notifications']) . ')');
    }
    if ($cleanup['service_requests'] !== []) {
        $pdo->exec('DELETE FROM service_assignments WHERE service_request_id IN (' . implode(',', $cleanup['service_requests']) . ')');
        $pdo->exec('DELETE FROM service_status_history WHERE service_request_id IN (' . implode(',', $cleanup['service_requests']) . ')');
        $pdo->exec('DELETE FROM service_requests WHERE id IN (' . implode(',', $cleanup['service_requests']) . ')');
    }
    if ($cleanup['announcements'] !== []) {
        $idList = implode(',', $cleanup['announcements']);
        $pdo->exec('DELETE FROM notifications WHERE reference_type = "announcement" AND reference_id IN (' . $idList . ')');
        $pdo->exec('DELETE FROM announcements WHERE id IN (' . $idList . ')');
    }
    if ($cleanup['ministries'] !== []) {
        $pdo->exec('DELETE FROM ministries WHERE id IN (' . implode(',', $cleanup['ministries']) . ')');
    }
    if ($cleanup['pastors'] !== []) {
        $pdo->exec('DELETE FROM pastors WHERE staff_id IN (' . implode(',', $cleanup['pastors']) . ')');
    }
    if ($cleanup['staff'] !== []) {
        $pdo->exec('DELETE FROM staff WHERE id IN (' . implode(',', $cleanup['staff']) . ')');
    }
    if ($cleanup['members'] !== []) {
        $pdo->exec('DELETE FROM ministry_members WHERE member_id IN (' . implode(',', $cleanup['members']) . ')');
        $pdo->exec('DELETE FROM members WHERE id IN (' . implode(',', $cleanup['members']) . ')');
    }
    if ($cleanup['facilities'] !== []) {
        $pdo->exec('DELETE FROM facilities WHERE id IN (' . implode(',', $cleanup['facilities']) . ')');
    }
    if ($cleanup['users'] !== []) {
        $pdo->exec('DELETE FROM user_roles WHERE user_id IN (' . implode(',', $cleanup['users']) . ')');
        $pdo->exec('DELETE FROM activity_logs WHERE user_id IN (' . implode(',', $cleanup['users']) . ')');
        $pdo->exec('DELETE FROM notifications WHERE user_id IN (' . implode(',', $cleanup['users']) . ')');
        $pdo->exec('DELETE FROM users WHERE id IN (' . implode(',', $cleanup['users']) . ')');
    }
}

echo "\nFR-07 archive smoke: {$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);
