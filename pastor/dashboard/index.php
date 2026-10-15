<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/service_request.php';
require_once __DIR__ . '/../../models/ServiceRequest.php';
require_once __DIR__ . '/../../models/Personnel.php';
require_once __DIR__ . '/../../models/Notification.php';
require_once __DIR__ . '/../../services/SchedulingService.php';

require_role('PASTOR');

$person = Personnel::findForUser((int) current_user_id());
$pending = array_merge(
    ServiceRequest::paginateForAdmin('', 'PENDING_ASSIGNMENT'),
    ServiceRequest::paginateForAdmin('', 'PENDING'),
    ServiceRequest::paginateForAdmin('', 'UNDER_REVIEW')
);
$queue = array_slice($pending, 0, 6);
$assignments = $person ? Personnel::serviceAssignments((int) $person['id']) : [];
$appointments = $person ? SchedulingService::appointmentsForStaff((int) $person['id']) : [];
$unread = Notification::unreadCount((int) current_user_id());

$dashboardTitle = 'Pastor dashboard';
$dashboardLead = 'Review church service requests, check the schedule, and follow your assigned pastoral work.';
$dashboardRoleLabel = 'Pastor';
$activeNav = 'dashboard';
$dashboardMetrics = [
    ['label' => 'Waiting for review', 'value' => count($pending), 'url' => APP_BASE_URL . '/admin/services/'],
    ['label' => 'Assigned services', 'value' => count($assignments), 'url' => APP_BASE_URL . '/pastor/services/'],
    ['label' => 'Upcoming appointments', 'value' => count($appointments), 'url' => APP_BASE_URL . '/pastor/appointments/'],
    ['label' => 'Unread notifications', 'value' => $unread],
];
$dashboardActions = [
    ['title' => 'Review requests', 'description' => 'Approve or decline wedding, baptism, counseling, and facility requests.', 'icon' => 'fa-clipboard-check', 'url' => APP_BASE_URL . '/pastor/services/'],
    ['title' => 'Church calendar', 'description' => 'Check booked and pending schedules before you decide.', 'icon' => 'fa-calendar-check', 'url' => APP_BASE_URL . '/admin/services/calendar.php'],
    ['title' => 'My assignments', 'description' => 'Open the services assigned to you for pastoral follow-up.', 'icon' => 'fa-hands-praying', 'url' => APP_BASE_URL . '/pastor/services/'],
    ['title' => 'Appointments', 'description' => 'Review your upcoming pastoral appointments.', 'icon' => 'fa-calendar-week', 'url' => APP_BASE_URL . '/pastor/appointments/'],
    ['title' => 'My schedule', 'description' => 'See your personal pastoral schedule.', 'icon' => 'fa-clock', 'url' => APP_BASE_URL . '/pastor/schedule/'],
];
$dashboardRecentTitle = 'Requests waiting for your review';
$dashboardRecentEmpty = 'No service or facility requests are waiting for review.';
$dashboardRecentItems = [];
foreach ($queue as $request) {
    $dashboardRecentItems[] = [
        'title' => $request['request_number'] . ' · ' . $request['service_name'],
        'meta' => $request['member_name'] . ' · ' . service_status_label((string) $request['status']),
        'url' => APP_BASE_URL . '/admin/services/view.php?id=' . (int) $request['id'],
    ];
}

require __DIR__ . '/../../includes/role-dashboard.php';
