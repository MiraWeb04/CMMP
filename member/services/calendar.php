<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../models/ChurchCalendar.php';
require_once __DIR__ . '/../../models/ServiceRequest.php';

require_role('MEMBER');
$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT) ?: (int) date('Y');
$year = max(2000, min(2100, $year));
$serviceTypeId = filter_input(INPUT_GET, 'service_type_id', FILTER_VALIDATE_INT) ?: null;
$serviceCode = '';
foreach (ServiceRequest::types() as $type) {
    if ((int) $type['id'] === (int) $serviceTypeId) {
        $serviceCode = strtoupper((string) $type['code']);
        break;
    }
}
$facilities = ServiceRequest::facilities();
$selectedFacilityId = filter_input(INPUT_GET, 'facility_id', FILTER_VALIDATE_INT) ?: null;
$pageTitle = 'Church calendar';
$activeNav = 'services';
$breadcrumbItems = [['label' => 'Church services', 'url' => 'index.php'], ['label' => 'Calendar', 'url' => 'calendar.php']];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-calendar-hero">
    <div>
        <span class="cmmp-calendar-kicker"><i class="fa-solid fa-calendar-check me-2"></i>Service scheduling</span>
        <h1>Find an open date</h1>
        <p>Browse the church calendar and choose a day that works for your service request.</p>
    </div>
    <a class="btn btn-light" href="index.php"><i class="fa-solid fa-arrow-left me-2"></i>Back to services</a>
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
</div>
<div class="cmmp-calendar-note"><i class="fa-solid fa-circle-info"></i><span>Green = open for this service. Red = confirmed booking. Orange entries = pending requests (informational). Published church events are shown; pick a time that does not overlap.</span></div>
<div class="cmmp-calendar-shell">
    <div id="church-calendar" data-api-url="<?= e(APP_BASE_URL) ?>/api/calendar/availability.php" data-request-url="request.php" data-service-type-id="<?= $serviceTypeId ? (int) $serviceTypeId : '' ?>" data-service-code="<?= e($serviceCode) ?>" data-facility-id="<?= $selectedFacilityId ? (int) $selectedFacilityId : '' ?>"></div>
    <?php require __DIR__ . '/../../includes/calendar-legend.php'; ?>
</div>
<div id="calendar-message" class="alert alert-danger mt-3" hidden></div>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script src="<?= e(APP_BASE_URL) ?>/assets/js/church-calendar.js?v=<?= (int) filemtime(__DIR__ . '/../../assets/js/church-calendar.js') ?>"></script>
<script>
(() => {
    const element = document.getElementById('church-calendar');
    const serviceTypeId = element?.dataset.serviceTypeId;
    const requestUrl = element?.dataset.requestUrl;
    window.cmmpInitChurchCalendar({
        initialDate: '<?= e((string) $year) ?>-01-01',
        onDateClick: (info, calendar, bookedDates) => {
            const today = new Date().toISOString().slice(0, 10);
            if (info.dateStr < today) {
                return;
            }
            if (bookedDates.has(info.dateStr) && !confirm('This date already has a confirmed booking for this venue or service. Continue with a different time?')) {
                return;
            }
            const query = new URLSearchParams({preferred_date: info.dateStr});
            if (serviceTypeId) {
                query.set('service_type_id', serviceTypeId);
            }
            const facility = document.getElementById('calendar-facility');
            if (facility?.value) {
                query.set('preferred_facility_id', facility.value);
            }
            window.location.href = requestUrl + '?' + query.toString();
        },
    });
})();
</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>