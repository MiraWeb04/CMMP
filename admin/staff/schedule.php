<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/personnel_schedule.php';
require_once __DIR__ . '/../../models/Personnel.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);

$staffId = filter_input(INPUT_GET, 'staff_id', FILTER_VALIDATE_INT);
$person = $staffId ? Personnel::find((int) $staffId) : null;
if (!$person) {
    http_response_code(404);
    exit('Staff record not found.');
}

$month = isset($_GET['month']) ? (string) $_GET['month'] : '';
if ($person['personnel_type'] === 'PASTOR') {
    header('Location: ../pastors/schedule.php?id=' . (int) $person['id'] . ($month !== '' ? '&month=' . urlencode($month) : ''));
    exit;
}
if ($person['personnel_type'] === 'COUNSELOR') {
    header('Location: ../counselors/schedule.php?id=' . (int) $person['id'] . ($month !== '' ? '&month=' . urlencode($month) : ''));
    exit;
}

$scheduleMonth = personnel_schedule_month($month !== '' ? $month : null);
$scheduleItems = personnel_schedule_items((int) $person['id'], $scheduleMonth['start'], $scheduleMonth['end']);
$scheduleDays = personnel_schedule_by_day($scheduleItems);
$scheduleHeading = $person['display_name'] . ' schedule';
$scheduleLead = 'Staff schedule';
$schedulePath = 'schedule.php';
$scheduleParams = ['staff_id' => (int) $person['id']];
$pageTitle = 'Staff schedule';
$activeNav = 'staff';
$breadcrumbItems = [
    ['label' => 'Staff', 'url' => 'index.php'],
    ['label' => 'Schedule', 'url' => 'schedule.php?staff_id=' . (int) $person['id']],
];

require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
require __DIR__ . '/../../includes/personnel-month-schedule.php';
require __DIR__ . '/../../includes/footer.php';
