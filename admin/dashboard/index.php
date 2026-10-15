<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/service_request.php';
require_once __DIR__ . '/../../models/Report.php';
require_once __DIR__ . '/../../models/ServiceRequest.php';
require_once __DIR__ . '/../../models/Notification.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);

$metrics = Report::dashboard();
$pendingRequests = ServiceRequest::paginateForAdmin('', 'PENDING_ASSIGNMENT');
$legacyPending = ServiceRequest::paginateForAdmin('', 'PENDING');
$underReview = ServiceRequest::paginateForAdmin('', 'UNDER_REVIEW');
$queue = array_slice(array_merge($pendingRequests, $legacyPending, $underReview), 0, 6);
$unread = Notification::unreadCount((int) current_user_id());
$isSuperAdmin = has_role('SUPER_ADMIN');
$isChurchAdmin = has_role('CHURCH_ADMIN');
$isSecretary = has_role('STAFF') && !$isSuperAdmin && !$isChurchAdmin;

if ($isSuperAdmin) {
    $dashboardTitle = 'Super administrator dashboard';
    $dashboardLead = 'Manage accounts, church operations, and records from one place.';
    $dashboardRoleLabel = 'Super Administrator';
    $dashboardActions = [
        ['title' => 'Users & roles', 'description' => 'Create accounts and assign Member, Secretary, or Pastor access.', 'icon' => 'fa-user-shield', 'url' => APP_BASE_URL . '/admin/users/'],
        ['title' => 'Service requests', 'description' => 'Review, assign, approve, or decline incoming requests.', 'icon' => 'fa-hands-praying', 'url' => APP_BASE_URL . '/admin/services/'],
        ['title' => 'Church calendar', 'description' => 'Check booked and pending schedules across all venues.', 'icon' => 'fa-calendar-check', 'url' => APP_BASE_URL . '/admin/services/calendar.php'],
        ['title' => 'Members', 'description' => 'Maintain church member records used by the portal.', 'icon' => 'fa-users', 'url' => APP_BASE_URL . '/admin/members/'],
        ['title' => 'Staff', 'description' => 'Manage church secretaries and office staff.', 'icon' => 'fa-id-badge', 'url' => APP_BASE_URL . '/admin/staff/'],
        ['title' => 'Reports', 'description' => 'Open service, event, and membership reports.', 'icon' => 'fa-chart-column', 'url' => APP_BASE_URL . '/admin/reports/'],
        ['title' => 'Announcements', 'description' => 'Publish updates for members, staff, and pastors.', 'icon' => 'fa-bullhorn', 'url' => APP_BASE_URL . '/admin/announcements/'],
        ['title' => 'Notifications', 'description' => 'Read alerts about registrations, requests, and assignments.', 'icon' => 'fa-bell', 'url' => APP_BASE_URL . '/admin/staff/notifications.php'],
    ];
} elseif ($isChurchAdmin) {
    $dashboardTitle = 'Church administrator dashboard';
    $dashboardLead = 'Manage schedules, requests, events, facilities, and church records.';
    $dashboardRoleLabel = 'Church Administrator';
    $dashboardActions = [
        ['title' => 'Service requests', 'description' => 'Review incoming wedding, baptism, counseling, and facility requests.', 'icon' => 'fa-hands-praying', 'url' => APP_BASE_URL . '/admin/services/'],
        ['title' => 'Church calendar', 'description' => 'Check available and booked dates before approving a request.', 'icon' => 'fa-calendar-check', 'url' => APP_BASE_URL . '/admin/services/calendar.php'],
        ['title' => 'Events', 'description' => 'Publish upcoming church events and gatherings.', 'icon' => 'fa-calendar-days', 'url' => APP_BASE_URL . '/admin/events/'],
        ['title' => 'Facilities', 'description' => 'Keep church venues available for reservations.', 'icon' => 'fa-building', 'url' => APP_BASE_URL . '/admin/facilities/'],
        ['title' => 'Announcements', 'description' => 'Post schedule updates for church members.', 'icon' => 'fa-bullhorn', 'url' => APP_BASE_URL . '/admin/announcements/'],
        ['title' => 'Members', 'description' => 'View and maintain basic member records.', 'icon' => 'fa-users', 'url' => APP_BASE_URL . '/admin/members/'],
        ['title' => 'Reports', 'description' => 'Track services, events, and participation.', 'icon' => 'fa-chart-column', 'url' => APP_BASE_URL . '/admin/reports/'],
        ['title' => 'Ministries', 'description' => 'Assign ministry leaders and group members.', 'icon' => 'fa-people-group', 'url' => APP_BASE_URL . '/admin/ministries/'],
    ];
} else {
    $dashboardTitle = 'Secretary dashboard';
    $dashboardLead = 'Review incoming requests, update the church calendar, and post announcements.';
    $dashboardRoleLabel = 'Church Secretary';
    $dashboardActions = [
        ['title' => 'Service requests', 'description' => 'Check new service and facility requests for schedule conflicts.', 'icon' => 'fa-hands-praying', 'url' => APP_BASE_URL . '/admin/services/'],
        ['title' => 'Church calendar', 'description' => 'See booked, pending, and available church schedules.', 'icon' => 'fa-calendar-check', 'url' => APP_BASE_URL . '/admin/services/calendar.php'],
        ['title' => 'Events', 'description' => 'Add or update church events on the calendar.', 'icon' => 'fa-calendar-plus', 'url' => APP_BASE_URL . '/admin/events/'],
        ['title' => 'Facilities', 'description' => 'Manage church venues used for reservations.', 'icon' => 'fa-building', 'url' => APP_BASE_URL . '/admin/facilities/'],
        ['title' => 'Announcements', 'description' => 'Post notifications about schedules and church updates.', 'icon' => 'fa-bullhorn', 'url' => APP_BASE_URL . '/admin/announcements/'],
        ['title' => 'Assignments', 'description' => 'Assign a pastor or counselor when a request needs follow-up.', 'icon' => 'fa-list-check', 'url' => APP_BASE_URL . '/admin/staff/assignments.php'],
        ['title' => 'My schedule', 'description' => 'See your assigned schedule for the month.', 'icon' => 'fa-calendar-days', 'url' => APP_BASE_URL . '/admin/staff/calendar.php'],
        ['title' => 'Notifications', 'description' => 'Read alerts about new and updated requests.', 'icon' => 'fa-bell', 'url' => APP_BASE_URL . '/admin/staff/notifications.php'],
    ];
}

$pendingRegistrations = (int) database()->query(
    'SELECT COUNT(*)
     FROM users u
     WHERE u.status = "PENDING"
       AND EXISTS (
           SELECT 1 FROM user_roles ur
           INNER JOIN roles r ON r.id = ur.role_id
           WHERE ur.user_id = u.id AND r.code = "MEMBER"
       )'
)->fetchColumn();
$dashboardMetrics = [
    ['label' => 'Pending requests', 'value' => (int) $metrics['pending_services'], 'url' => APP_BASE_URL . '/admin/services/?status=PENDING_ASSIGNMENT'],
    ['label' => 'Upcoming events', 'value' => (int) $metrics['upcoming_events'], 'url' => APP_BASE_URL . '/admin/events/'],
    ['label' => 'Upcoming appointments', 'value' => (int) $metrics['upcoming_appointments']],
    ['label' => $isSecretary ? 'Unread notifications' : 'Active members', 'value' => $isSecretary ? $unread : (int) $metrics['active_members'], 'url' => $isSecretary ? APP_BASE_URL . '/admin/staff/notifications.php' : APP_BASE_URL . '/admin/members/'],
];
if (!$isSecretary) {
    $dashboardMetrics[] = ['label' => 'Pending registrations', 'value' => $pendingRegistrations, 'url' => APP_BASE_URL . '/admin/members/registrations.php'];
}

$dashboardRecentTitle = 'Requests waiting for review';
$dashboardRecentEmpty = 'No pending or under-review requests.';
$dashboardRecentItems = [];
foreach ($queue as $request) {
    $dashboardRecentItems[] = [
        'title' => $request['request_number'] . ' · ' . $request['service_name'],
        'meta' => $request['member_name'] . ' · ' . service_status_label((string) $request['status']),
        'url' => APP_BASE_URL . '/admin/services/view.php?id=' . (int) $request['id'],
    ];
}

$activeNav = has_role('STAFF') && !has_role(['SUPER_ADMIN', 'CHURCH_ADMIN']) ? 'admin-dashboard' : 'dashboard';
$bodyClass = 'cmmp-glass';
require __DIR__ . '/../../includes/role-dashboard.php';
