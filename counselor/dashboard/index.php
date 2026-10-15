<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../models/Personnel.php';
require_once __DIR__ . '/../../models/Counseling.php';
require_once __DIR__ . '/../../models/Notification.php';
require_once __DIR__ . '/../../services/SchedulingService.php';

require_role('COUNSELOR');

$person = Personnel::findForUser((int) current_user_id());
$cases = $person ? Counseling::casesForCounselor((int) $person['id']) : [];
$assignments = $person ? Personnel::serviceAssignments((int) $person['id']) : [];
$appointments = $person ? SchedulingService::appointmentsForStaff((int) $person['id']) : [];
$unread = Notification::unreadCount((int) current_user_id());

$dashboardTitle = 'Counselor dashboard';
$dashboardLead = 'Requests assigned to you appear here as soon as church staff assigns you.';
$dashboardRoleLabel = 'Counselor';
$activeNav = 'dashboard';
$dashboardMetrics = [
    ['label' => 'Assigned requests', 'value' => count($assignments), 'url' => APP_BASE_URL . '/counselor/sessions/'],
    ['label' => 'Assigned couples', 'value' => count($cases), 'url' => APP_BASE_URL . '/counselor/couples/'],
    ['label' => 'Scheduled appointments', 'value' => count($appointments), 'url' => APP_BASE_URL . '/counselor/schedule/'],
    ['label' => 'Unread notifications', 'value' => $unread],
];
$dashboardActions = [
    ['title' => 'Couples', 'description' => 'Open counseling cases assigned to you.', 'icon' => 'fa-people-arrows', 'url' => APP_BASE_URL . '/counselor/couples/'],
    ['title' => 'Sessions', 'description' => 'Review and complete counseling sessions.', 'icon' => 'fa-comments', 'url' => APP_BASE_URL . '/counselor/sessions/'],
    ['title' => 'Schedule', 'description' => 'Check your counseling appointments and availability.', 'icon' => 'fa-calendar-week', 'url' => APP_BASE_URL . '/counselor/schedule/'],
];
$dashboardRecentTitle = 'Assigned requests';
$dashboardRecentEmpty = 'No requests are assigned to you yet.';
$dashboardRecentItems = [];
foreach (array_slice($assignments, 0, 6) as $item) {
    $caseId = (int) ($item['counseling_case_id'] ?? 0);
    $dashboardRecentItems[] = [
        'title' => $item['request_number'] . ' · ' . ($item['member_name'] !== '' ? $item['member_name'] : $item['service_name']),
        'meta' => $item['service_name'] . ' · ' . str_replace('_', ' ', (string) $item['status']),
        'url' => $caseId > 0
            ? APP_BASE_URL . '/counselor/sessions/view.php?id=' . $caseId
            : APP_BASE_URL . '/counselor/sessions/',
    ];
}

require __DIR__ . '/../../includes/role-dashboard.php';
