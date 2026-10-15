<?php
declare(strict_types=1);

$administrators = ['SUPER_ADMIN', 'CHURCH_ADMIN'];
$allAuthenticated = ['SUPER_ADMIN', 'CHURCH_ADMIN', 'PASTOR', 'COUNSELOR', 'MINISTRY_LEADER', 'STAFF', 'MEMBER'];
$staffOnly = has_role('STAFF') && !has_role(['SUPER_ADMIN', 'CHURCH_ADMIN']);
$dashboardUrl = APP_BASE_URL . '/auth/protected-test.php';

if (function_exists('role_redirect_path') && function_exists('current_user_roles')) {
    $userRoles = current_user_roles();
    if ($userRoles !== []) {
        $dashboardUrl = APP_BASE_URL . role_redirect_path($userRoles);
    }
}

return [
    ...($staffOnly ? [
        [
            'label' => 'Overview',
            'items' => [
                ['key' => 'admin-dashboard', 'label' => 'Dashboard', 'icon' => 'fa-house', 'url' => APP_BASE_URL . '/admin/dashboard/', 'roles' => ['STAFF']],
            ],
        ],
        [
            'label' => 'Requests',
            'items' => [
                ['key' => 'service-requests', 'label' => 'Service Requests', 'icon' => 'fa-hands-praying', 'url' => APP_BASE_URL . '/admin/services/', 'roles' => ['STAFF']],
            ],
        ],
        [
            'label' => 'Scheduling',
            'items' => [
                ['key' => 'staff-church-calendar', 'label' => 'Church Calendar', 'icon' => 'fa-calendar-check', 'url' => APP_BASE_URL . '/admin/services/calendar.php', 'roles' => ['STAFF']],
                ['key' => 'staff-calendar', 'label' => 'My Schedule', 'icon' => 'fa-calendar-days', 'url' => APP_BASE_URL . '/admin/staff/calendar.php', 'roles' => ['STAFF']],
                ['key' => 'staff-assignments', 'label' => 'Assignments', 'icon' => 'fa-list-check', 'url' => APP_BASE_URL . '/admin/staff/assignments.php', 'roles' => ['STAFF']],
                ['key' => 'staff-events', 'label' => 'Events', 'icon' => 'fa-calendar-plus', 'url' => APP_BASE_URL . '/admin/events/', 'roles' => ['STAFF']],
                ['key' => 'staff-facilities', 'label' => 'Facilities', 'icon' => 'fa-building', 'url' => APP_BASE_URL . '/admin/facilities/', 'roles' => ['STAFF']],
                ['key' => 'staff-announcements', 'label' => 'Announcements', 'icon' => 'fa-bullhorn', 'url' => APP_BASE_URL . '/admin/announcements/', 'roles' => ['STAFF']],
            ],
        ],
        [
            'label' => 'Availability',
            'items' => [
                ['key' => 'staff-availability', 'label' => 'Personnel Availability', 'icon' => 'fa-clock', 'url' => APP_BASE_URL . '/admin/staff/availability.php', 'roles' => ['STAFF']],
            ],
        ],
        [
            'label' => 'Communication',
            'items' => [
                ['key' => 'staff-notifications', 'label' => 'Notifications', 'icon' => 'fa-bell', 'url' => APP_BASE_URL . '/admin/staff/notifications.php', 'roles' => ['STAFF']],
            ],
        ],
        [
            'label' => 'Account',
            'items' => [
                ['key' => 'staff-profile', 'label' => 'My Profile', 'icon' => 'fa-user', 'url' => APP_BASE_URL . '/admin/staff/profile.php', 'roles' => ['STAFF']],
                ['key' => 'logout', 'label' => 'Logout', 'icon' => 'fa-right-from-bracket', 'url' => APP_BASE_URL . '/auth/logout.php', 'roles' => ['STAFF']],
            ],
        ],
    ] : []),
    [
        'label' => 'Overview',
        'items' => [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-house', 'url' => $dashboardUrl, 'roles' => $allAuthenticated],
        ],
    ],
    [
        'label' => 'Member Portal',
        'items' => [
            ['key' => 'events', 'label' => 'Events', 'icon' => 'fa-calendar-days', 'url' => APP_BASE_URL . '/member/events/', 'roles' => ['MEMBER']],
            ['key' => 'registrations', 'label' => 'My Registrations', 'icon' => 'fa-ticket', 'url' => APP_BASE_URL . '/member/registrations/', 'roles' => ['MEMBER']],
            ['key' => 'services', 'label' => 'Church Services', 'icon' => 'fa-hands-praying', 'url' => APP_BASE_URL . '/member/services/', 'roles' => ['MEMBER']],
            ['key' => 'service-calendar', 'label' => 'Service Calendar', 'icon' => 'fa-calendar-days', 'url' => APP_BASE_URL . '/member/services/calendar.php', 'roles' => ['MEMBER']],
            ['key' => 'requests', 'label' => 'My Requests', 'icon' => 'fa-inbox', 'url' => APP_BASE_URL . '/member/services/', 'roles' => ['MEMBER']],
            ['key' => 'ministries', 'label' => 'Ministries', 'icon' => 'fa-people-group', 'url' => APP_BASE_URL . '/member/ministries/', 'roles' => ['MEMBER']],
            ['key' => 'member-announcements', 'label' => 'Announcements', 'icon' => 'fa-bullhorn', 'url' => APP_BASE_URL . '/member/announcements/', 'roles' => ['MEMBER']],
            ['key' => 'notifications', 'label' => 'Notifications', 'icon' => 'fa-bell', 'url' => APP_BASE_URL . '/member/notifications/', 'roles' => ['MEMBER']],
            ['key' => 'profile', 'label' => 'Profile', 'icon' => 'fa-user', 'url' => APP_BASE_URL . '/member/profile/', 'roles' => ['MEMBER']],
        ],
    ],
    [
        'label' => 'Administration',
        'items' => [
            ['key' => 'members', 'label' => 'Members', 'icon' => 'fa-users', 'url' => APP_BASE_URL . '/admin/members/', 'roles' => $administrators],
            ['key' => 'member-registrations', 'label' => 'New Members', 'icon' => 'fa-user-check', 'url' => APP_BASE_URL . '/admin/members/registrations.php', 'roles' => $administrators],
            ['key' => 'staff', 'label' => 'Staff', 'icon' => 'fa-id-badge', 'url' => APP_BASE_URL . '/admin/staff/', 'roles' => $administrators],
            ['key' => 'pastors', 'label' => 'Pastors', 'icon' => 'fa-person', 'url' => APP_BASE_URL . '/admin/pastors/', 'roles' => $administrators],
            ['key' => 'counselors', 'label' => 'Counselors', 'icon' => 'fa-comments', 'url' => APP_BASE_URL . '/admin/counselors/', 'roles' => $administrators],
            ['key' => 'ministries-admin', 'label' => 'Ministries', 'icon' => 'fa-people-group', 'url' => APP_BASE_URL . '/admin/ministries/', 'roles' => $administrators],
            ['key' => 'events-admin', 'label' => 'Events', 'icon' => 'fa-calendar-days', 'url' => APP_BASE_URL . '/admin/events/', 'roles' => $administrators],
            ['key' => 'service-requests', 'label' => 'Service Requests', 'icon' => 'fa-hands-praying', 'url' => APP_BASE_URL . '/admin/services/', 'roles' => $administrators],
            ['key' => 'church-calendar', 'label' => 'Church Calendar', 'icon' => 'fa-calendar-check', 'url' => APP_BASE_URL . '/admin/services/calendar.php', 'roles' => $administrators],
            ['key' => 'facilities', 'label' => 'Facilities', 'icon' => 'fa-building', 'url' => APP_BASE_URL . '/admin/facilities/', 'roles' => $administrators],
            ['key' => 'announcements', 'label' => 'Announcements', 'icon' => 'fa-bullhorn', 'url' => APP_BASE_URL . '/admin/announcements/', 'roles' => $administrators],
            ['key' => 'reports', 'label' => 'Reports', 'icon' => 'fa-chart-column', 'url' => APP_BASE_URL . '/admin/reports/', 'roles' => $administrators],
            ['key' => 'users', 'label' => 'Users & Roles', 'icon' => 'fa-user-shield', 'url' => APP_BASE_URL . '/admin/users/', 'roles' => $administrators],
            ['key' => 'admin-notifications', 'label' => 'Notifications', 'icon' => 'fa-bell', 'url' => APP_BASE_URL . '/admin/staff/notifications.php', 'roles' => $administrators],
        ],
    ],
    [
        'label' => 'Ministry & Care',
        'items' => [
            ['key' => 'pastor-review', 'label' => 'My Assignments', 'icon' => 'fa-clipboard-check', 'url' => APP_BASE_URL . '/pastor/services/', 'roles' => ['PASTOR']],
            ['key' => 'pastor-calendar', 'label' => 'Church Calendar', 'icon' => 'fa-calendar-check', 'url' => APP_BASE_URL . '/admin/services/calendar.php', 'roles' => ['PASTOR']],
            ['key' => 'pastor-services', 'label' => 'My Services', 'icon' => 'fa-hands-praying', 'url' => APP_BASE_URL . '/pastor/services/', 'roles' => ['PASTOR']],
            ['key' => 'pastor-appointments', 'label' => 'Appointments', 'icon' => 'fa-calendar-check', 'url' => APP_BASE_URL . '/pastor/appointments/', 'roles' => ['PASTOR']],
            ['key' => 'pastor-schedule', 'label' => 'Schedule', 'icon' => 'fa-calendar-week', 'url' => APP_BASE_URL . '/pastor/schedule/', 'roles' => ['PASTOR']],
            ['key' => 'counselor-couples', 'label' => 'Couples', 'icon' => 'fa-people-arrows', 'url' => APP_BASE_URL . '/counselor/couples/', 'roles' => ['COUNSELOR']],
            ['key' => 'counselor-sessions', 'label' => 'Sessions', 'icon' => 'fa-comments', 'url' => APP_BASE_URL . '/counselor/sessions/', 'roles' => ['COUNSELOR']],
            ['key' => 'counselor-schedule', 'label' => 'Schedule', 'icon' => 'fa-calendar-week', 'url' => APP_BASE_URL . '/counselor/schedule/', 'roles' => ['COUNSELOR']],
            ['key' => 'ministry-members', 'label' => 'Ministry Members', 'icon' => 'fa-users', 'url' => APP_BASE_URL . '/ministry/members/', 'roles' => ['MINISTRY_LEADER']],
            ['key' => 'ministry-activities', 'label' => 'Activities', 'icon' => 'fa-list-check', 'url' => APP_BASE_URL . '/ministry/activities/', 'roles' => ['MINISTRY_LEADER']],
            ['key' => 'ministry-events', 'label' => 'Events', 'icon' => 'fa-calendar-days', 'url' => APP_BASE_URL . '/ministry/events/', 'roles' => ['MINISTRY_LEADER']],
        ],
    ],
];