<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/service_request.php';
require_once __DIR__ . '/../../models/Personnel.php';
require_once __DIR__ . '/../../models/Notification.php';
require_once __DIR__ . '/../../services/SchedulingService.php';

require_role('PASTOR');

$person = Personnel::findForUser((int) current_user_id());
$assignments = $person ? Personnel::serviceAssignments((int) $person['id']) : [];
$events = $person ? Personnel::eventAssignments((int) $person['id']) : [];
$appointments = $person ? SchedulingService::appointmentsForStaff((int) $person['id']) : [];
$unread = Notification::unreadCount((int) current_user_id());

$dashboardTitle = 'Pastor dashboard';
$dashboardLead = 'Review your assigned services, events, and appointments.';
$dashboardRoleLabel = 'Pastor';
$activeNav = 'dashboard';
$dashboardMetrics = [
    ['label' => 'Assigned services', 'value' => count($assignments), 'url' => APP_BASE_URL . '/pastor/services/'],
    ['label' => 'Assigned events', 'value' => count($events), 'url' => APP_BASE_URL . '/pastor/services/'],
    ['label' => 'Upcoming appointments', 'value' => count($appointments), 'url' => APP_BASE_URL . '/pastor/appointments/'],
    ['label' => 'Unread notifications', 'value' => $unread],
];
$dashboardActions = [
    ['title' => 'My assignments', 'description' => 'Approve, decline, or complete services assigned to you.', 'icon' => 'fa-hands-praying', 'url' => APP_BASE_URL . '/pastor/services/'],
    ['title' => 'Church calendar', 'description' => 'See confirmed bookings and open dates.', 'icon' => 'fa-calendar-check', 'url' => APP_BASE_URL . '/admin/services/calendar.php'],
    ['title' => 'Appointments', 'description' => 'Review your upcoming pastoral appointments.', 'icon' => 'fa-calendar-week', 'url' => APP_BASE_URL . '/pastor/appointments/'],
    ['title' => 'My schedule', 'description' => 'See your personal pastoral schedule.', 'icon' => 'fa-clock', 'url' => APP_BASE_URL . '/pastor/schedule/'],
];
$dashboardRecentTitle = 'Assigned services';
$dashboardRecentEmpty = 'No services are assigned to you yet.';
$dashboardRecentItems = [];
foreach (array_slice($assignments, 0, 6) as $request) {
    $dashboardRecentItems[] = [
        'title' => $request['request_number'] . ' · ' . $request['service_name'],
        'meta' => service_status_label((string) $request['status']) . ' · ' . ($request['assignment_role'] ?? 'Pastor'),
        'url' => APP_BASE_URL . '/admin/services/view.php?id=' . (int) $request['id'],
    ];
}

require __DIR__ . '/../../includes/role-dashboard.php';
