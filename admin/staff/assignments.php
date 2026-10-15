<?php
declare(strict_types=1);
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../models/Personnel.php';
require_once __DIR__ . '/../../models/ServiceRequest.php';
require_once __DIR__ . '/../../services/AvailabilityService.php';
require_role('STAFF');
$person = Personnel::findForUser((int) current_user_id());
$services = $person ? Personnel::serviceAssignments((int) $person['id']) : [];
$events = $person ? Personnel::eventAssignments((int) $person['id']) : [];
$selectedRequestId = filter_input(INPUT_GET, 'service_request_id', FILTER_VALIDATE_INT) ?: 0;
$requestedDate = trim((string) ($_GET['appointment_date'] ?? ''));
$startTime = trim((string) ($_GET['start_time'] ?? ''));
$endTime = trim((string) ($_GET['end_time'] ?? ''));
$availablePastors = [];
$availableCounselors = [];
$availabilityError = null;
if ($selectedRequestId && $requestedDate !== '' && $startTime !== '' && $endTime !== '') {
	try {
		$availablePastors = AvailabilityService::availablePersonnel('PASTOR', $requestedDate, $startTime, $endTime);
		$availableCounselors = AvailabilityService::availablePersonnel('COUNSELOR', $requestedDate, $startTime, $endTime);
	} catch (Throwable $exception) {
		$availabilityError = $exception->getMessage();
	}
}
$pageTitle = 'Assignments'; $activeNav = 'staff-assignments'; $breadcrumbItems = [['label' => 'Assignments', 'url' => 'assignments.php']];
require __DIR__ . '/../../includes/header.php'; require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header"><div><h1>Assignments</h1><p>Review your assignments and check Pastor or Counselor availability before scheduling.</p></div></div>
<div class="row g-3">
	<div class="col-lg-6">
		<div class="cmmp-card">
			<h2 class="h5">Service requests</h2>
			<?php foreach ($services as $item): ?>
				<p><a href="../services/view.php?id=<?= (int) $item['id'] ?>"><?= e($item['request_number']) ?></a><br><span class="text-muted"><?= e($item['service_name']) ?> - <?= e($item['status']) ?></span></p>
			<?php endforeach; ?>
			<?php if ($services === []): ?><p class="text-muted mb-0">No service assignments.</p><?php endif; ?>
		</div>
	</div>
	<div class="col-lg-6">
		<div class="cmmp-card">
			<h2 class="h5">Events</h2>
			<?php foreach ($events as $item): ?><p><?= e($item['title']) ?><br><span class="text-muted"><?= e($item['start_datetime']) ?> - <?= e($item['event_status']) ?></span></p><?php endforeach; ?>
			<?php if ($events === []): ?><p class="text-muted mb-0">No event assignments.</p><?php endif; ?>
		</div>
	</div>
</div>
<div class="cmmp-card mt-4">
	<h2 class="h5">Check personnel availability</h2>
	<p class="text-muted">Select a service request and schedule to load available Pastor and Counselor accounts.</p>
	<form method="get" class="row g-3 align-items-end" id="availability-form" data-csrf-token="<?= e(csrf_token()) ?>">
		<div class="col-lg-4"><label class="form-label" for="service_request_id">Service request</label><select class="form-select" id="service_request_id" name="service_request_id" required><option value="">Select request</option><?php foreach ($services as $item): ?><option value="<?= (int) $item['id'] ?>" <?= $selectedRequestId === (int) $item['id'] ? 'selected' : '' ?>><?= e($item['request_number'] . ' - ' . $item['service_name']) ?></option><?php endforeach; ?></select></div>
		<div class="col-md-4 col-lg-2"><label class="form-label" for="appointment_date">Date</label><input class="form-control" id="appointment_date" name="appointment_date" type="date" value="<?= e($requestedDate) ?>" required></div>
		<div class="col-md-4 col-lg-2"><label class="form-label" for="start_time">Start time</label><input class="form-control" id="start_time" name="start_time" type="time" value="<?= e($startTime) ?>" required></div>
		<div class="col-md-4 col-lg-2"><label class="form-label" for="end_time">End time</label><input class="form-control" id="end_time" name="end_time" type="time" value="<?= e($endTime) ?>" required></div>
		<div class="col-lg-2"><button class="btn btn-primary w-100" type="submit">Check availability</button></div>
	</form>
</div>
<?php if ($availabilityError !== null): ?><div class="alert alert-danger mt-3"><?= e($availabilityError) ?></div><?php endif; ?>
<?php if ($selectedRequestId && $availabilityError === null && $requestedDate !== '' && $startTime !== '' && $endTime !== ''): ?>
<div class="row g-3 mt-1">
	<div class="col-md-6"><div class="cmmp-card"><h2 class="h5">Available Pastors</h2><select class="form-select" id="available-pastors" name="available_pastor_id"><option value="">Select available Pastor</option><?php foreach ($availablePastors as $available): ?><option value="<?= (int) $available['id'] ?>"><?= e($available['display_name'] . ' - ' . $available['username'] . ' (' . $available['email'] . ')') ?></option><?php endforeach; ?></select><p class="text-muted mt-2 mb-0" id="pastor-availability-message"><?= $selectedRequestId && $requestedDate !== '' && $startTime !== '' && $endTime !== '' && $availablePastors === [] ? 'No available personnel.' : '' ?></p></div></div>
	<div class="col-md-6"><div class="cmmp-card"><h2 class="h5">Available Counselors</h2><select class="form-select" id="available-counselors" name="available_counselor_id"><option value="">Select available Counselor</option><?php foreach ($availableCounselors as $available): ?><option value="<?= (int) $available['id'] ?>"><?= e($available['display_name'] . ' - ' . $available['username'] . ' (' . $available['email'] . ')') ?></option><?php endforeach; ?></select><p class="text-muted mt-2 mb-0" id="counselor-availability-message"><?= $selectedRequestId && $requestedDate !== '' && $startTime !== '' && $endTime !== '' && $availableCounselors === [] ? 'No available personnel.' : '' ?></p></div></div>
</div>
<?php endif; ?>
<div class="text-muted small mt-3" id="availability-loading" hidden>Checking personnel availability...</div>
<script>
(() => {
	const form = document.getElementById('availability-form');
	const request = document.getElementById('service_request_id');
	const date = document.getElementById('appointment_date');
	const start = document.getElementById('start_time');
	const end = document.getElementById('end_time');
	const csrf = form?.dataset.csrfToken;
	const pastors = document.getElementById('available-pastors');
	const counselors = document.getElementById('available-counselors');
	const pastorMessage = document.getElementById('pastor-availability-message');
	const counselorMessage = document.getElementById('counselor-availability-message');
	const loading = document.getElementById('availability-loading');
	if (!form || !request || !date || !start || !end || !csrf) return;

	const setOptions = (select, people, label, message) => {
		if (!select) return;
		select.replaceChildren(new Option('Select available ' + label, ''));
		people.forEach((person) => {
			const account = person.username + (person.email ? ' (' + person.email + ')' : '');
			select.add(new Option(person.display_name + ' - ' + account, person.id));
		});
		if (message) message.textContent = people.length ? '' : 'No available personnel.';
	};

	const refreshAvailability = async () => {
		if (!request.value || !date.value || !start.value || !end.value) return;
		loading.hidden = false;
		if (pastorMessage) pastorMessage.textContent = '';
		if (counselorMessage) counselorMessage.textContent = '';
		try {
			const response = await fetch('<?= e(APP_BASE_URL) ?>/api/availability/personnel.php', {
				method: 'POST',
				headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
				body: JSON.stringify({
					csrf_token: csrf,
					service_request_id: request.value,
					appointment_date: date.value,
					start_time: start.value,
					end_time: end.value
				})
			});
			const result = await response.json();
			if (!response.ok || !result.success) throw new Error(result.message || 'Availability check failed.');
			setOptions(pastors, result.pastors || [], 'Pastor', pastorMessage);
			setOptions(counselors, result.counselors || [], 'Counselor', counselorMessage);
		} catch (error) {
			setOptions(pastors, [], 'Pastor', pastorMessage);
			setOptions(counselors, [], 'Counselor', counselorMessage);
			if (pastorMessage) pastorMessage.textContent = error.message;
		} finally {
			loading.hidden = true;
		}
	};

	[request, date, start, end].forEach((field) => field.addEventListener('change', refreshAvailability));
})();
</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
