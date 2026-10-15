<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../models/Personnel.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);

$people = Personnel::list('COUNSELOR');
$details = [];
foreach ($people as $person) {
	$details[(int) $person['id']] = Personnel::find((int) $person['id']);
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
	require_valid_csrf_token();
	$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
	$person = $id ? Personnel::find((int) $id) : null;
	if (!$person || $person['personnel_type'] !== 'COUNSELOR') {
		$errors[] = 'Counselor record not found.';
	} else {
		$updates = [
			'user_id' => (int) $person['user_id'],
			'member_id' => (int) ($person['member_id'] ?? 0),
			'employee_number' => trim((string) ($_POST['employee_number'] ?? '')),
			'position_title' => trim((string) ($_POST['position_title'] ?? '')),
			'employment_status' => $person['employment_status'],
			'hired_at' => $person['hired_at'] ?? '',
			'personnel_type' => 'COUNSELOR',
			'specialization' => trim((string) ($_POST['specialization'] ?? '')),
			'credentials' => trim((string) ($_POST['credentials'] ?? '')),
		];
		if ($updates['employee_number'] === '' || $updates['position_title'] === '' || $updates['specialization'] === '') {
			$errors[] = 'Employee number, position, and specialization are required.';
		} else {
			try {
				Personnel::update((int) $id, $updates);
				header('Location: index.php?saved=1');
				exit;
			} catch (Throwable $exception) {
				$errors[] = $exception instanceof PDOException && (string) $exception->getCode() === '23000'
					? 'That employee number is already assigned.'
					: 'Counselor record could not be updated.';
			}
		}
	}
}
$pageTitle = 'Counselors';
$activeNav = 'counselors';
$bodyClass = 'cmmp-glass';
$breadcrumbItems = [['label' => 'Counselors', 'url' => 'index.php']];

require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header">
	<div><span class="cmmp-kicker">Church administration</span><h1>Counselors</h1><p>Manage counseling personnel connected to services, appointments, and schedules.</p></div>
	<a class="btn btn-primary" href="add.php"><i class="fa-solid fa-user-plus me-2"></i>Add counselor</a>
</div>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Counselor record created successfully.</div><?php endif; ?>
<?php if (isset($_GET['deactivated'])): ?><div class="alert alert-success">Counselor record deactivated.</div><?php endif; ?>
<?php if ($errors !== []): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="cmmp-table-wrap">
	<div class="table-responsive">
		<table class="table table-hover align-middle mb-0">
			<thead><tr><th>Name</th><th>Portal email</th><th>Employee number</th><th>Position</th><th>Specialization</th><th>Credentials</th><th class="text-end">Actions</th></tr></thead>
			<tbody>
			<?php if ($people === []): ?><tr><td colspan="7" class="text-center text-muted py-5"><i class="fa-solid fa-comments d-block fs-2 mb-3"></i><strong class="d-block">No active counselors found</strong><span>Create a counselor record to make it available for service assignments.</span></td></tr><?php endif; ?>
			<?php foreach ($people as $person): ?>
				<tr>
					<td><?= e($person['display_name']) ?></td>
					<td><?= e($person['email'] ?? 'No email') ?></td>
					<td><?= e($person['employee_number']) ?></td>
					<td><?= e($person['position_title']) ?></td>
					<td><?= e($person['specialization'] ?? 'Not set') ?></td>
					<td><?= e($person['credentials'] ?? 'Not set') ?></td>
					<td class="text-end"><div class="cmmp-row-actions">
						<button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#viewCounselor<?= (int) $person['id'] ?>">View</button>
						<button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#editCounselor<?= (int) $person['id'] ?>">Edit</button>
						<a class="btn btn-sm btn-outline-primary" href="assignments.php?id=<?= (int) $person['id'] ?>">Assignments</a>
						<a class="btn btn-sm btn-outline-primary" href="schedule.php?id=<?= (int) $person['id'] ?>">Schedule</a>
						<form method="post" action="../staff/deactivate.php" class="d-inline" onsubmit="return confirm('Deactivate this counselor record?');">
							<?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $person['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Deactivate</button>
						</form>
					</div></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
<?php foreach ($people as $person): ?><?php $detail = $details[(int) $person['id']]; ?>
<div class="modal fade" id="viewCounselor<?= (int) $person['id'] ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5">Counselor profile</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><dl class="row mb-0"><dt class="col-5">Name</dt><dd class="col-7"><?= e($detail['display_name']) ?></dd><dt class="col-5">Email</dt><dd class="col-7"><?= e($detail['email'] ?? 'No email') ?></dd><dt class="col-5">Employee number</dt><dd class="col-7"><?= e($detail['employee_number']) ?></dd><dt class="col-5">Position</dt><dd class="col-7"><?= e($detail['position_title']) ?></dd><dt class="col-5">Specialization</dt><dd class="col-7"><?= e($detail['specialization'] ?? 'Not set') ?></dd><dt class="col-5">Credentials</dt><dd class="col-7"><?= e($detail['credentials'] ?? 'Not set') ?></dd></dl></div></div></div></div>
<div class="modal fade" id="editCounselor<?= (int) $person['id'] ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><h2 class="modal-title fs-5">Edit counselor</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><?= csrf_field() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= (int) $detail['id'] ?>"><div class="mb-3"><label class="form-label" for="counselor-number-<?= (int) $detail['id'] ?>">Employee number</label><input class="form-control" id="counselor-number-<?= (int) $detail['id'] ?>" name="employee_number" value="<?= e($detail['employee_number']) ?>" required></div><div class="mb-3"><label class="form-label" for="counselor-position-<?= (int) $detail['id'] ?>">Position</label><input class="form-control" id="counselor-position-<?= (int) $detail['id'] ?>" name="position_title" value="<?= e($detail['position_title']) ?>" required></div><div class="mb-3"><label class="form-label" for="counselor-specialization-<?= (int) $detail['id'] ?>">Counseling specialization</label><select class="form-select" id="counselor-specialization-<?= (int) $detail['id'] ?>" name="specialization" required><?php foreach (['Pastoral Counseling', 'Pre-Marriage Counseling', 'Marriage & Family Counseling', 'Youth Counseling', 'Grief & Bereavement Counseling'] as $specialization): ?><option value="<?= e($specialization) ?>" <?= $detail['specialization'] === $specialization ? 'selected' : '' ?>><?= e($specialization) ?></option><?php endforeach; ?></select></div><div><label class="form-label" for="counselor-credentials-<?= (int) $detail['id'] ?>">Credentials</label><input class="form-control" id="counselor-credentials-<?= (int) $detail['id'] ?>" name="credentials" value="<?= e($detail['credentials'] ?? '') ?>"></div></div><div class="modal-footer"><button class="btn btn-primary" type="submit">Save changes</button><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button></div></form></div></div></div>
<?php endforeach; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
