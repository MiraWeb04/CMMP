<?php
declare(strict_types=1);
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../services/AvailabilityService.php';
require_role('STAFF');
$personnelType = strtoupper(trim((string) ($_GET['personnel_type'] ?? 'ALL')));
$requestedDate = trim((string) ($_GET['appointment_date'] ?? ''));
$startTime = trim((string) ($_GET['start_time'] ?? ''));
$endTime = trim((string) ($_GET['end_time'] ?? ''));
$pastors = [];
$counselors = [];
$error = null;
$checked = $requestedDate !== '' && $startTime !== '' && $endTime !== '';
if ($checked) {
	try {
		if (in_array($personnelType, ['ALL', 'PASTOR'], true)) {
			$pastors = AvailabilityService::availablePersonnel('PASTOR', $requestedDate, $startTime, $endTime);
		}
		if (in_array($personnelType, ['ALL', 'COUNSELOR'], true)) {
			$counselors = AvailabilityService::availablePersonnel('COUNSELOR', $requestedDate, $startTime, $endTime);
		}
	} catch (Throwable $exception) {
		$error = $exception->getMessage();
	}
}
$pageTitle = 'Personnel Availability'; $activeNav = 'staff-availability'; $breadcrumbItems = [['label' => 'Personnel Availability', 'url' => 'availability.php']];
require __DIR__ . '/../../includes/header.php'; require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header"><div><h1>Personnel Availability</h1><p>Check real Pastor and Counselor availability before assigning a schedule.</p></div></div>
<div class="cmmp-card">
	<form method="get" class="row g-3 align-items-end">
		<div class="col-md-3"><label class="form-label" for="personnel_type">Personnel type</label><select class="form-select" id="personnel_type" name="personnel_type"><option value="ALL" <?= $personnelType === 'ALL' ? 'selected' : '' ?>>Pastors and Counselors</option><option value="PASTOR" <?= $personnelType === 'PASTOR' ? 'selected' : '' ?>>Pastors only</option><option value="COUNSELOR" <?= $personnelType === 'COUNSELOR' ? 'selected' : '' ?>>Counselors only</option></select></div>
		<div class="col-md-3"><label class="form-label" for="appointment_date">Date</label><input class="form-control" id="appointment_date" name="appointment_date" type="date" value="<?= e($requestedDate) ?>" required></div>
		<div class="col-md-2"><label class="form-label" for="start_time">Start time</label><input class="form-control" id="start_time" name="start_time" type="time" value="<?= e($startTime) ?>" required></div>
		<div class="col-md-2"><label class="form-label" for="end_time">End time</label><input class="form-control" id="end_time" name="end_time" type="time" value="<?= e($endTime) ?>" required></div>
		<div class="col-md-2"><button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-magnifying-glass me-1"></i>Check</button></div>
	</form>
</div>
<?php if ($error !== null): ?><div class="alert alert-danger mt-3"><?= e($error) ?></div><?php endif; ?>
<?php if ($checked && $error === null): ?>
<div class="row g-3 mt-1">
	<?php if (in_array($personnelType, ['ALL', 'PASTOR'], true)): ?><div class="col-lg-6"><div class="cmmp-card"><h2 class="h5">Available Pastors</h2><?php if ($pastors === []): ?><p class="text-muted mb-0">No available personnel.</p><?php else: ?><div class="list-group list-group-flush"><?php foreach ($pastors as $pastor): ?><div class="list-group-item px-0"><strong><?= e($pastor['display_name']) ?></strong><span class="badge text-bg-light ms-2"><?= e($pastor['personnel_type']) ?></span><small class="d-block text-muted"><?= e($pastor['username']) ?><?= $pastor['email'] ? ' - ' . e($pastor['email']) : '' ?> · <?= e($pastor['position_title']) ?></small></div><?php endforeach; ?></div><?php endif; ?></div></div><?php endif; ?>
	<?php if (in_array($personnelType, ['ALL', 'COUNSELOR'], true)): ?><div class="col-lg-6"><div class="cmmp-card"><h2 class="h5">Available Counselors</h2><?php if ($counselors === []): ?><p class="text-muted mb-0">No available personnel.</p><?php else: ?><div class="list-group list-group-flush"><?php foreach ($counselors as $counselor): ?><div class="list-group-item px-0"><strong><?= e($counselor['display_name']) ?></strong><span class="badge text-bg-light ms-2"><?= e($counselor['personnel_type']) ?></span><small class="d-block text-muted"><?= e($counselor['username']) ?><?= $counselor['email'] ? ' - ' . e($counselor['email']) : '' ?> · <?= e($counselor['position_title']) ?></small></div><?php endforeach; ?></div><?php endif; ?></div></div><?php endif; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
