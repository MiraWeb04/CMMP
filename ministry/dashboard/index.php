<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../models/Ministry.php';
require_once __DIR__ . '/../../models/Notification.php';

require_role('MINISTRY_LEADER');

$ministries = Ministry::ledByUser((int) current_user_id());
$memberCount = 0;
$activityCount = 0;
$eventCount = 0;
foreach ($ministries as $ministry) {
    $memberCount += (int) ($ministry['member_count'] ?? 0);
    $details = Ministry::find((int) $ministry['id']);
    if (!$details) {
        continue;
    }
    $activityCount += count($details['activities'] ?? []);
    $eventCount += count($details['events'] ?? []);
}
$unread = Notification::unreadCount((int) current_user_id());
$firstMinistryId = $ministries !== [] ? (int) $ministries[0]['id'] : 0;

$dashboardTitle = 'Ministry leader dashboard';
$dashboardLead = 'Coordinate your ministry members, activities, and events.';
$dashboardRoleLabel = 'Ministry Leader';
$activeNav = 'dashboard';
$dashboardMetrics = [
    ['label' => 'Ministries you lead', 'value' => count($ministries)],
    ['label' => 'Ministry members', 'value' => $memberCount, 'url' => $firstMinistryId ? APP_BASE_URL . '/ministry/members/?ministry_id=' . $firstMinistryId : null],
    ['label' => 'Activities & events', 'value' => $activityCount + $eventCount, 'url' => APP_BASE_URL . '/ministry/activities/'],
    ['label' => 'Unread notifications', 'value' => $unread],
];
$dashboardActions = [
    ['title' => 'Ministry members', 'description' => 'See the people in the ministries you lead.', 'icon' => 'fa-users', 'url' => $firstMinistryId ? APP_BASE_URL . '/ministry/members/?ministry_id=' . $firstMinistryId : APP_BASE_URL . '/ministry/activities/'],
    ['title' => 'Activities', 'description' => 'Review planned and ongoing ministry activities.', 'icon' => 'fa-list-check', 'url' => APP_BASE_URL . '/ministry/activities/'],
    ['title' => 'Events', 'description' => 'Check ministry events connected to your group.', 'icon' => 'fa-calendar-days', 'url' => APP_BASE_URL . '/ministry/events/'],
];
$dashboardRecentTitle = 'Your ministries';
$dashboardRecentEmpty = 'No active ministry is assigned to your account yet.';
$dashboardRecentItems = [];
foreach ($ministries as $ministry) {
    $dashboardRecentItems[] = [
        'title' => $ministry['name'],
        'meta' => (int) $ministry['member_count'] . ' members · ' . $ministry['code'],
        'url' => APP_BASE_URL . '/ministry/members/?ministry_id=' . (int) $ministry['id'],
    ];
}

require __DIR__ . '/../../includes/role-dashboard.php';
