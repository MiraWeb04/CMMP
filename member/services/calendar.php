<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../models/ChurchCalendar.php';
require_once __DIR__ . '/../../models/ServiceRequest.php';

require_role('MEMBER');
$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT) ?: (int) date('Y');
$year = max(2000, min(2100, $year));
$serviceTypeId = filter_input(INPUT_GET, 'service_type_id', FILTER_VALIDATE_INT) ?: null;
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
    <div class="cmmp-calendar-legend" aria-label="Calendar legend">
        <span><i class="cmmp-calendar-dot cmmp-calendar-dot-available"></i>Available</span>
        <span><i class="cmmp-calendar-dot cmmp-calendar-dot-pending"></i>Pending request</span>
        <span><i class="cmmp-calendar-dot cmmp-calendar-dot-blocked"></i>Booked</span>
        <span><i class="cmmp-calendar-dot cmmp-calendar-dot-today"></i>Today</span>
    </div>
</div>
<div class="cmmp-calendar-note"><i class="fa-solid fa-circle-info"></i><span>Booked and pending requests appear on the calendar. Choose an open date, then pick a time that does not overlap an existing booking.</span></div>
<div class="cmmp-calendar-shell"><div id="church-calendar" data-api-url="<?= e(APP_BASE_URL) ?>/api/calendar/availability.php" data-request-url="request.php" data-service-type-id="<?= $serviceTypeId ? (int) $serviceTypeId : '' ?>" data-facility-id="<?= $selectedFacilityId ? (int) $selectedFacilityId : '' ?>"></div></div>
<div id="calendar-message" class="alert alert-danger mt-3" hidden></div>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script>
(() => {
    const element = document.getElementById('church-calendar');
    const facility = document.getElementById('calendar-facility');
    const message = document.getElementById('calendar-message');
    if (!element || !window.FullCalendar) return;

    const params = new URLSearchParams();
    const serviceTypeId = element.dataset.serviceTypeId;
    if (serviceTypeId) params.set('service_type_id', serviceTypeId);
    const requestUrl = element.dataset.requestUrl;
    const calendar = new FullCalendar.Calendar(element, {
        initialView: window.innerWidth < 768 ? 'listMonth' : 'dayGridMonth',
        initialDate: '<?= e((string) $year) ?>-01-01',
        height: 'auto',
        fixedWeekCount: false,
        dayMaxEvents: 3,
        firstDay: 0,
        headerToolbar: {left: 'prev,next today', center: 'title', right: 'dayGridMonth,listMonth'},
        events: (info, success, failure) => {
            const query = new URLSearchParams({start: info.startStr, end: info.endStr});
            if (facility.value) query.set('facility_id', facility.value);
            fetch(element.dataset.apiUrl + '?' + query.toString(), {headers: {Accept: 'application/json'}})
                .then((response) => response.json().then((data) => ({response, data})))
                .then(({response, data}) => {
                    if (!response.ok || !data.success) throw new Error(data.message || 'Calendar data could not be loaded.');
                    success(data.events || []);
                    message.hidden = true;
                })
                .catch((error) => { message.textContent = error.message; message.hidden = false; failure(error); });
        },
        eventClick: (info) => {
            info.jsEvent.preventDefault();
            const details = info.event.extendedProps;
            alert(details.type + ': ' + info.event.title.replace(details.type + ': ', '') + '\n' + info.event.start.toLocaleString());
        },
        dateClick: (info) => {
            const today = new Date().toISOString().slice(0, 10);
            if (info.dateStr < today) return;
            const dayEvents = calendar.getEvents().filter((event) => {
                if (!event.start) return false;
                const start = event.start.toISOString().slice(0, 10);
                const end = event.end ? event.end.toISOString().slice(0, 10) : start;
                return info.dateStr >= start && info.dateStr <= end;
            });
            if (dayEvents.length > 0 && !confirm('This date already has a church booking or pending request. Continue and choose a different time?')) {
                return;
            }
            const query = new URLSearchParams({preferred_date: info.dateStr});
            if (serviceTypeId) query.set('service_type_id', serviceTypeId);
            if (facility.value) query.set('preferred_facility_id', facility.value);
            window.location.href = requestUrl + '?' + query.toString();
        }
    });
    facility.addEventListener('change', () => calendar.refetchEvents());
    calendar.render();
})();
</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>