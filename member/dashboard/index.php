<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/service_request.php';
require_once __DIR__ . '/../../models/ServiceRequest.php';
require_once __DIR__ . '/../../models/Event.php';
require_once __DIR__ . '/../../models/Announcement.php';
require_once __DIR__ . '/../../models/Notification.php';
require_role('MEMBER');

$memberId = ServiceRequest::memberIdForUser((int) current_user_id());
if (!$memberId) {
    http_response_code(404);
    exit('Your member profile has not been created yet.');
}

$requests = ServiceRequest::paginateForMember($memberId);
$openRequests = array_values(array_filter($requests, static fn(array $item): bool => !in_array($item['status'], ['COMPLETED', 'REJECTED', 'CANCELLED'], true)));
$events = Event::upcoming($memberId);
$announcements = Announcement::listForUser(current_user_roles());
$unread = Notification::unreadCount((int) current_user_id());

$dashboardTitle = 'Member dashboard';
$dashboardLead = 'Check the church calendar, send a service or facility request, and follow your request status.';
$dashboardRoleLabel = 'Church Member';
$activeNav = 'dashboard';
$dashboardMetrics = [
    ['label' => 'My open requests', 'value' => count($openRequests), 'url' => APP_BASE_URL . '/member/services/'],
    ['label' => 'Upcoming events', 'value' => count($events), 'url' => APP_BASE_URL . '/member/events/'],
    ['label' => 'Announcements', 'value' => count($announcements), 'url' => APP_BASE_URL . '/member/announcements/'],
    ['label' => 'Unread notifications', 'value' => $unread, 'url' => APP_BASE_URL . '/member/notifications/'],
];
$dashboardActions = [
    ['title' => 'Church calendar', 'description' => 'See available and booked dates before sending a request.', 'icon' => 'fa-calendar-days', 'url' => APP_BASE_URL . '/member/services/calendar.php'],
    ['title' => 'Request a service', 'description' => 'Submit a wedding, baptism, counseling, house blessing, or funeral request.', 'icon' => 'fa-hands-praying', 'url' => APP_BASE_URL . '/member/services/request.php'],
    ['title' => 'Reserve a facility', 'description' => 'Request a church venue for an approved church event.', 'icon' => 'fa-building', 'url' => APP_BASE_URL . '/member/services/request.php?service_code=FACILITY_RESERVATION'],
    ['title' => 'My requests', 'description' => 'Track the status of every service or facility request you sent.', 'icon' => 'fa-inbox', 'url' => APP_BASE_URL . '/member/services/'],
    ['title' => 'Events', 'description' => 'View upcoming church events and register when available.', 'icon' => 'fa-calendar-plus', 'url' => APP_BASE_URL . '/member/events/'],
    ['title' => 'Ministries', 'description' => 'View ministry groups. Administration assigns leaders and members.', 'icon' => 'fa-people-group', 'url' => APP_BASE_URL . '/member/ministries/'],
    ['title' => 'Announcements', 'description' => 'Read important updates about church schedules.', 'icon' => 'fa-bullhorn', 'url' => APP_BASE_URL . '/member/announcements/'],
    ['title' => 'My profile', 'description' => 'View and update your basic personal information.', 'icon' => 'fa-user', 'url' => APP_BASE_URL . '/member/profile/'],
];
$dashboardRecentTitle = 'Recent requests';
$dashboardRecentEmpty = 'You have not submitted a service or facility request yet.';
$dashboardRecentItems = [];
foreach (array_slice($requests, 0, 6) as $request) {
    $dashboardRecentItems[] = [
        'title' => $request['request_number'] . ' · ' . $request['service_name'],
        'meta' => service_status_label((string) $request['status']) . ' · ' . $request['requested_at'],
        'url' => APP_BASE_URL . '/member/services/view.php?id=' . (int) $request['id'],
    ];
}

require __DIR__ . '/../../includes/role-dashboard.php';
