<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../models/Personnel.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);

$people = Personnel::list('PASTOR');
$details = [];
foreach ($people as $person) {
	$details[(int) $person['id']] = Personnel::find((int) $person['id']);
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
	require_valid_csrf_token();
	$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
	$person = $id ? Personnel::find((int) $id) : null;
	if (!$person || $person['personnel_type'] !== 'PASTOR') {
		$errors[] = 'Pastor record not found.';
	} else {
		$updates = [
			'user_id' => (int) $person['user_id'],
			'member_id' => (int) ($person['member_id'] ?? 0),
			'employee_number' => trim((string) ($_POST['employee_number'] ?? '')),
			'position_title' => trim((string) ($_POST['position_title'] ?? '')),
			'employment_status' => $person['employment_status'],
			'hired_at' => $person['hired_at'] ?? '',
			'personnel_type' => 'PASTOR',
			'ordination_date' => trim((string) ($_POST['ordination_date'] ?? '')),
		];
		if ($updates['employee_number'] === '' || $updates['position_title'] === '') {
			$errors[] = 'Pastor number and position are required.';
		} else {
			try {
				Personnel::update((int) $id, $updates);
				header('Location: index.php?saved=1');
				exit;
			} catch (Throwable $exception) {
				$errors[] = $exception instanceof PDOException && (string) $exception->getCode() === '23000'
					? 'That pastor number is already assigned.'
					: 'Pastor record could not be updated.';
			}
		}
	}
}
$pageTitle = 'Pastors';
$activeNav = 'pastors';
$bodyClass = 'cmmp-glass';
$breadcrumbItems = [['label' => 'Pastors', 'url' => 'index.php']];

require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header">
	<div><span class="cmmp-kicker">Church administration</span><h1>Pastors</h1><p>Manage pastoral personnel connected to services, appointments, and schedules.</p></div>
	<a class="btn btn-primary" href="add.php"><i class="fa-solid fa-user-plus me-2"></i>Add pastor</a>
</div>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Pastor record created successfully.</div><?php endif; ?>
<?php if (isset($_GET['deactivated'])): ?><div class="alert alert-success">Pastor record deactivated.</div><?php endif; ?>
<?php if ($errors !== []): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="cmmp-table-wrap">
	<div class="table-responsive">
		<table class="table table-hover align-middle mb-0">
			<thead><tr><th>Name</th><th>Portal email</th><th>Pastor number</th><th>Position</th><th>Ordination date</th><th class="text-end">Actions</th></tr></thead>
			<tbody>
			<?php if ($people === []): ?><tr><td colspan="6" class="text-center text-muted py-5"><i class="fa-solid fa-person d-block fs-2 mb-3"></i><strong class="d-block">No active pastors found</strong><span>Create a pastor record to make it available for service assignments.</span></td></tr><?php endif; ?>
			<?php foreach ($people as $person): ?>
				<tr>
					<td><?= e($person['display_name']) ?></td>
					<td><?= e($person['email'] ?? 'No email') ?></td>
					<td><?= e($person['employee_number']) ?></td>
					<td><?= e($person['position_title']) ?></td>
					<td><?= e($person['ordination_date'] ?? 'Not set') ?></td>
					<td class="text-end"><div class="cmmp-row-actions">
						<button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#viewPastor<?= (int) $person['id'] ?>">View</button>
						<button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#editPastor<?= (int) $person['id'] ?>">Edit</button>
						<a class="btn btn-sm btn-outline-primary" href="assignments.php?id=<?= (int) $person['id'] ?>">Assignments</a>
						<a class="btn btn-sm btn-outline-primary" href="schedule.php?id=<?= (int) $person['id'] ?>">Schedule</a>
						<form method="post" action="../staff/deactivate.php" class="d-inline" onsubmit="return confirm('Deactivate this pastor record?');">
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
<div class="modal fade" id="viewPastor<?= (int) $person['id'] ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5">Pastor profile</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><dl class="row mb-0"><dt class="col-5">Name</dt><dd class="col-7"><?= e($detail['display_name']) ?></dd><dt class="col-5">Email</dt><dd class="col-7"><?= e($detail['email'] ?? 'No email') ?></dd><dt class="col-5">Pastor number</dt><dd class="col-7"><?= e($detail['employee_number']) ?></dd><dt class="col-5">Position</dt><dd class="col-7"><?= e($detail['position_title']) ?></dd><dt class="col-5">Ordination date</dt><dd class="col-7"><?= e($detail['ordination_date'] ?? 'Not set') ?></dd></dl></div></div></div></div>
<div class="modal fade" id="editPastor<?= (int) $person['id'] ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><h2 class="modal-title fs-5">Edit pastor</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><?= csrf_field() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= (int) $detail['id'] ?>"><div class="mb-3"><label class="form-label" for="pastor-number-<?= (int) $detail['id'] ?>">Pastor number</label><input class="form-control" id="pastor-number-<?= (int) $detail['id'] ?>" name="employee_number" value="<?= e($detail['employee_number']) ?>" required></div><div class="mb-3"><label class="form-label" for="pastor-position-<?= (int) $detail['id'] ?>">Position</label><input class="form-control" id="pastor-position-<?= (int) $detail['id'] ?>" name="position_title" value="<?= e($detail['position_title']) ?>" required></div><div><label class="form-label" for="pastor-ordination-<?= (int) $detail['id'] ?>">Ordination date</label><input class="form-control" id="pastor-ordination-<?= (int) $detail['id'] ?>" name="ordination_date" type="date" value="<?= e($detail['ordination_date'] ?? '') ?>"></div></div><div class="modal-footer"><button class="btn btn-primary" type="submit">Save changes</button><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button></div></form></div></div></div>
<?php endforeach; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
