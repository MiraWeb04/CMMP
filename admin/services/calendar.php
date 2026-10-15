<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/service_request.php';
require_once __DIR__ . '/../../models/ServiceRequest.php';

require_role(SERVICE_CALENDAR_ROLES);
$facilities = ServiceRequest::facilities();
$selectedFacilityId = filter_input(INPUT_GET, 'facility_id', FILTER_VALIDATE_INT) ?: null;
$pageTitle = 'Church calendar';
$activeNav = has_role('PASTOR') ? 'pastor-calendar' : (has_role('STAFF') && !has_role(['SUPER_ADMIN', 'CHURCH_ADMIN']) ? 'staff-church-calendar' : 'church-calendar');
$breadcrumbItems = [['label' => 'Church calendar', 'url' => 'calendar.php']];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-calendar-hero">
    <div>
        <span class="cmmp-calendar-kicker"><i class="fa-solid fa-calendar-check me-2"></i>Schedule management</span>
        <h1>Church calendar</h1>
        <p>Review booked schedules, pending requests, and facility reservations in one place.</p>
    </div>
    <a class="btn btn-light" href="index.php"><i class="fa-solid fa-inbox me-2"></i>Review requests</a>
</div>
<div class="cmmp-calendar-toolbar">
    <div class="cmmp-calendar-year-nav">
        <div class="cmmp-calendar-year-label"><span>Schedule view</span><strong>Church calendar</strong></div>
    </div>
    <div class="cmmp-calendar-filter">
        <label for="calendar-facility">Show venue</label>
        <select class="form-select form-select-sm" id="calendar-facility">
            <option value="">All venues</option>
            <?php foreach ($facilities as $facility): ?><option value="<?= (int) $facility['id'] ?>" <?= $selectedFacilityId === (int) $facility['id'] ? 'selected' : '' ?>><?= e($facility['name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="cmmp-calendar-legend" aria-label="Calendar legend">
        <span><i class="cmmp-calendar-dot cmmp-calendar-dot-available"></i>Available</span>
        <span><i class="cmmp-calendar-dot cmmp-calendar-dot-blocked"></i>Booked</span>
        <span><i class="cmmp-calendar-dot cmmp-calendar-dot-today"></i>Today</span>
    </div>
</div>
<div class="cmmp-calendar-note"><i class="fa-solid fa-circle-info"></i><span>Green days are open. Red days already have confirmed church bookings. Pending requests are not shown on this calendar.</span></div>
<div class="cmmp-calendar-shell"><div id="church-calendar" data-api-url="<?= e(APP_BASE_URL) ?>/api/calendar/availability.php" data-facility-id="<?= $selectedFacilityId ? (int) $selectedFacilityId : '' ?>"></div></div>
<div id="calendar-message" class="alert alert-danger mt-3" hidden></div>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script src="<?= e(APP_BASE_URL) ?>/assets/js/church-calendar.js?v=<?= (int) filemtime(__DIR__ . '/../../assets/js/church-calendar.js') ?>"></script>
<script>
window.cmmpInitChurchCalendar({});
</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
