<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/pagination.php';
require_once __DIR__ . '/../../models/Personnel.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);

$page = list_page();
$perPage = list_per_page();
$result = Personnel::paginate('STAFF', $page, $perPage);
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
<?php if (isset($_GET['deactivated'])): ?><div class="alert alert-success">Staff record deactivated.</div><?php endif; ?>

<div class="cmmp-table-wrap">
	<div class="table-responsive">
		<table class="table table-hover align-middle mb-0">
			<thead>
				<tr>
					<th>Name</th>
					<th>Employee number</th>
					<th>Position</th>
					<th>Type</th>
					<th class="text-end">Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php if ($people === []): ?>
					<tr>
						<td colspan="5" class="text-center text-muted py-5">
							<i class="fa-solid fa-user-group d-block fs-2 mb-3"></i>
							<strong class="d-block">No active staff records found</strong>
							<span>Staff records will appear here after personnel are added and activated.</span>
						</td>
					</tr>
				<?php else: ?>
					<?php foreach ($people as $person): ?>
						<tr>
							<td><?= e($person['display_name']) ?></td>
							<td><?= e($person['employee_number']) ?></td>
							<td><?= e($person['position_title']) ?></td>
							<td><span class="badge text-bg-light"><?= e($person['personnel_type']) ?></span></td>
							<td class="text-end"><div class="cmmp-row-actions">
								<a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int) $person['id'] ?>">View</a>
								<a class="btn btn-sm btn-outline-primary" href="edit.php?id=<?= (int) $person['id'] ?>">Edit</a>
								<a class="btn btn-sm btn-outline-primary" href="schedule.php?staff_id=<?= (int) $person['id'] ?>">Schedule</a>
								<form method="post" action="deactivate.php" class="d-inline" onsubmit="return confirm('Deactivate this staff record?');">
									<?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $person['id'] ?>">
									<button class="btn btn-sm btn-outline-danger" type="submit">Deactivate</button>
								</form>
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
$paginationFilters = [];
$paginationLabel = 'Staff pages';
require __DIR__ . '/../../includes/pagination.php';
require __DIR__ . '/../../includes/footer.php';
?>
