<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/pagination.php';
require_once __DIR__ . '/../../models/Personnel.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);

$page = list_page();
$perPage = list_per_page();
$status = strtoupper(trim((string) ($_GET['status'] ?? 'ACTIVE')));
if (!in_array($status, ['ACTIVE', 'INACTIVE', 'ON_LEAVE', 'ALL'], true)) {
	$status = 'ACTIVE';
}
$result = Personnel::paginate('STAFF', $page, $perPage, $status);
$people = $result['items'];
$page = min($page, pagination_total_pages($result['total'], $perPage));
$pageTitle = 'Staff';
$activeNav = 'staff';
$bodyClass = 'cmmp-glass';
$breadcrumbItems = [['label' => 'Staff', 'url' => 'index.php']];

require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header">
	<div>
		<span class="cmmp-kicker">Church administration</span>
		<h1>Staff</h1>
		<p>Church secretaries and office staff only. Pastors and counselors are managed in their own directories.</p>
	</div>
	<a class="btn btn-primary" href="add.php"><i class="fa-solid fa-user-plus me-2"></i>Add staff</a>
</div>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success">Staff record created successfully.</div>
<?php endif; ?>
<?php if (isset($_GET['updated'])): ?><div class="alert alert-success">Staff record updated successfully.</div><?php endif; ?>
<?php if (isset($_GET['deactivated'])): ?><div class="alert alert-success">Staff record deactivated. Historical assignments were kept.</div><?php endif; ?>
<?php if (isset($_GET['reactivated'])): ?><div class="alert alert-success">Staff record reactivated.</div><?php endif; ?>
<?php if (!empty($_GET['error'])): ?><div class="alert alert-danger"><?= e((string) $_GET['error']) ?></div><?php endif; ?>

<div class="cmmp-card mb-4">
	<form method="get" class="row g-3 align-items-end">
		<div class="col-md-4">
			<label class="form-label" for="status">Employment status</label>
			<select class="form-select" id="status" name="status">
				<?php foreach (['ACTIVE' => 'Active', 'INACTIVE' => 'Inactive', 'ON_LEAVE' => 'On leave', 'ALL' => 'All records'] as $value => $label): ?>
					<option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="col-auto">
			<button class="btn btn-primary" type="submit">Filter</button>
			<a class="btn btn-outline-secondary" href="index.php">Reset</a>
		</div>
	</form>
</div>

<div class="cmmp-table-wrap">
	<div class="table-responsive">
		<table class="table table-hover align-middle mb-0">
			<thead>
				<tr>
					<th>Name</th>
					<th>Employee number</th>
					<th>Position</th>
					<th>Type</th>
					<th>Status</th>
					<th class="text-end">Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php if ($people === []): ?>
					<tr>
						<td colspan="6" class="text-center text-muted py-5">
							<i class="fa-solid fa-user-group d-block fs-2 mb-3"></i>
							<strong class="d-block">No staff records found</strong>
							<span>Staff records will appear here after personnel are added.</span>
						</td>
					</tr>
				<?php else: ?>
					<?php foreach ($people as $person): ?>
						<tr>
							<td><?= e($person['display_name']) ?></td>
							<td><?= e($person['employee_number']) ?></td>
							<td><?= e($person['position_title']) ?></td>
							<td><span class="badge text-bg-light"><?= e($person['personnel_type']) ?></span></td>
							<td><span class="badge text-bg-<?= $person['employment_status'] === 'ACTIVE' ? 'success' : 'secondary' ?>"><?= e($person['employment_status']) ?></span></td>
							<td class="text-end"><div class="cmmp-row-actions">
								<a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int) $person['id'] ?>">View</a>
								<a class="btn btn-sm btn-outline-primary" href="edit.php?id=<?= (int) $person['id'] ?>">Edit</a>
								<a class="btn btn-sm btn-outline-primary" href="schedule.php?staff_id=<?= (int) $person['id'] ?>">Schedule</a>
								<?php if ($person['employment_status'] === 'ACTIVE'): ?>
								<form method="post" action="deactivate.php" class="d-inline" onsubmit="return confirm('Deactivate this staff record? They will not receive new assignments. Historical records stay in place.');">
									<?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $person['id'] ?>">
									<input type="hidden" name="action" value="deactivate">
									<input type="hidden" name="status_filter" value="<?= e($status) ?>">
									<button class="btn btn-sm btn-outline-danger" type="submit">Deactivate</button>
								</form>
								<?php else: ?>
								<form method="post" action="deactivate.php" class="d-inline" onsubmit="return confirm('Reactivate this staff record?');">
									<?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $person['id'] ?>">
									<input type="hidden" name="action" value="reactivate">
									<input type="hidden" name="status_filter" value="<?= e($status) ?>">
									<button class="btn btn-sm btn-outline-success" type="submit">Reactivate</button>
								</form>
								<?php endif; ?>
							</div></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
<?php
$paginationTotal = $result['total'];
$paginationPage = $page;
$paginationPerPage = $perPage;
$paginationFilters = ['status' => $status];
$paginationLabel = 'Staff pages';
require __DIR__ . '/../../includes/pagination.php';
require __DIR__ . '/../../includes/footer.php';
?>
