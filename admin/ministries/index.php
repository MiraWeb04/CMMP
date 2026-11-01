<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/pagination.php';
require_once __DIR__ . '/../../models/Ministry.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN']);

$status = strtoupper(trim((string) ($_GET['status'] ?? '')));
if (!in_array($status, ['', 'ACTIVE', 'INACTIVE'], true)) {
    $status = '';
}
$page = list_page();
$perPage = list_per_page();
$result = Ministry::paginateAdmin($page, $perPage, $status);
$ministries = $result['items'];
$page = min($page, pagination_total_pages($result['total'], $perPage));
$pageTitle = 'Ministries';
$activeNav = 'ministries-admin';
$breadcrumbItems = [['label' => 'Ministries', 'url' => 'index.php']];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header">
    <div><h1>Ministries</h1><p>Assign ministry leaders and group members. Members cannot request to join.</p></div>
    <a class="btn btn-primary" href="add.php">Create ministry</a>
</div>
<?php if (isset($_GET['status_changed'])): ?><div class="alert alert-success">Ministry status updated. Historical membership and events were kept.</div><?php endif; ?>
<div class="cmmp-card mb-4">
    <form method="get" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">All statuses</option>
                <option value="ACTIVE" <?= $status === 'ACTIVE' ? 'selected' : '' ?>>ACTIVE</option>
                <option value="INACTIVE" <?= $status === 'INACTIVE' ? 'selected' : '' ?>>INACTIVE</option>
            </select>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" type="submit">Filter</button>
            <a class="btn btn-outline-secondary" href="index.php">Reset</a>
        </div>
    </form>
</div>
<div class="d-flex justify-content-between align-items-center mb-2">
    <h2 class="cmmp-section-title my-0">Ministry directory</h2>
    <span class="text-muted small"><?= (int) $result['total'] ?> record(s)</span>
</div>
<div class="cmmp-table-wrap">
    <table class="table table-hover">
        <thead>
            <tr><th>Name</th><th>Code</th><th>Leader</th><th>Members</th><th>Status</th><th>Action</th></tr>
        </thead>
        <tbody>
            <?php if ($ministries === []): ?><tr><td colspan="6" class="text-center py-5 text-muted">No ministries found.</td></tr><?php endif; ?>
            <?php foreach ($ministries as $ministry): ?>
                <tr>
                    <td><a href="view.php?id=<?= (int) $ministry['id'] ?>"><?= e($ministry['name']) ?></a></td>
                    <td><?= e($ministry['code']) ?></td>
                    <td><?= e($ministry['leader_name']) ?></td>
                    <td><?= (int) $ministry['member_count'] ?></td>
                    <td><span class="badge text-bg-<?= $ministry['ministry_status'] === 'ACTIVE' ? 'success' : 'secondary' ?>"><?= e($ministry['ministry_status']) ?></span></td>
                    <td class="text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int) $ministry['id'] ?>">View</a>
                        <a class="btn btn-sm btn-outline-secondary" href="edit.php?id=<?= (int) $ministry['id'] ?>">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
$paginationTotal = $result['total'];
$paginationPage = $page;
$paginationPerPage = $perPage;
$paginationFilters = ['status' => $status];
$paginationLabel = 'Ministry pages';
require __DIR__ . '/../../includes/pagination.php';
require __DIR__ . '/../../includes/footer.php';
