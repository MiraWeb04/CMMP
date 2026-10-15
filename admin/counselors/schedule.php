<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/personnel_schedule.php';
require_once __DIR__ . '/../../models/Personnel.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$person = $id ? Personnel::find((int) $id) : null;
if (!$person || $person['personnel_type'] !== 'COUNSELOR') {
    http_response_code(404);
    exit('Counselor record not found.');
}

$scheduleMonth = personnel_schedule_month(isset($_GET['month']) ? (string) $_GET['month'] : null);
$scheduleItems = personnel_schedule_items((int) $person['id'], $scheduleMonth['start'], $scheduleMonth['end']);
$scheduleDays = personnel_schedule_by_day($scheduleItems);
$scheduleHeading = $person['display_name'] . ' schedule';
$scheduleLead = 'Counseling schedule';
$schedulePath = 'schedule.php';
$scheduleParams = ['id' => (int) $person['id']];
$pageTitle = 'Counselor schedule';
$activeNav = 'counselors';
$breadcrumbItems = [
    ['label' => 'Counselors', 'url' => 'index.php'],
    ['label' => 'Schedule', 'url' => 'schedule.php?id=' . (int) $person['id']],
];

require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
require __DIR__ . '/../../includes/personnel-month-schedule.php';
require __DIR__ . '/../../includes/footer.php';
