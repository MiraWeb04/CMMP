<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/personnel_schedule.php';
require_once __DIR__ . '/../../models/Personnel.php';

require_role('PASTOR');

$person = Personnel::findForUser((int) current_user_id());
$pageTitle = 'Schedule';
$activeNav = 'pastor-schedule';
$breadcrumbItems = [['label' => 'Schedule', 'url' => 'index.php']];

require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';

if (!$person) {
    echo '<div class="cmmp-card"><p class="mb-0">Your pastor record has not been created yet, so there is no schedule to show.</p></div>';
    require __DIR__ . '/../../includes/footer.php';
    exit;
}

$scheduleMonth = personnel_schedule_month(isset($_GET['month']) ? (string) $_GET['month'] : null);
$scheduleItems = personnel_schedule_items((int) $person['id'], $scheduleMonth['start'], $scheduleMonth['end']);
$scheduleDays = personnel_schedule_by_day($scheduleItems);
$scheduleHeading = 'My schedule';
$scheduleLead = 'Pastoral schedule';
$schedulePath = 'index.php';
$scheduleParams = [];
require __DIR__ . '/../../includes/personnel-month-schedule.php';
require __DIR__ . '/../../includes/footer.php';
