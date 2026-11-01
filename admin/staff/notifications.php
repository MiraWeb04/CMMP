<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/notification_inbox.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF', 'PASTOR', 'COUNSELOR', 'MINISTRY_LEADER']);

$notificationInbox = notification_inbox_prepare((int) current_user_id());
$notificationLead = 'Alerts about registrations, requests, and assignments.';
$pageTitle = 'Notifications';
$activeNav = has_role(['SUPER_ADMIN', 'CHURCH_ADMIN'])
    ? 'admin-notifications'
    : (has_role('STAFF') ? 'staff-notifications' : 'role-notifications');
$breadcrumbItems = [['label' => 'Notifications', 'url' => 'notifications.php']];

require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
require __DIR__ . '/../../includes/notification-inbox.php';
require __DIR__ . '/../../includes/footer.php';
