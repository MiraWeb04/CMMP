<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/member.php';
require_once __DIR__ . '/../../helpers/fellowship.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);

if (!isset($_SESSION['cmmp_fellowship_backfill_v1'])) {
    fellowship_backfill_all_active_members();
    $_SESSION['cmmp_fellowship_backfill_v1'] = true;
}

$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'status' => (string) ($_GET['status'] ?? ''),
    'gender' => (string) ($_GET['gender'] ?? ''),
];
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$result = Member::paginate($filters, $page, $perPage);
$totalPages = max(1, (int) ceil($result['total'] / $perPage));
$page = min($page, $totalPages);

$pageTitle = 'Members';
$activeNav = 'members';
$bodyClass = 'cmmp-glass';
$breadcrumbItems = [['label' => 'Members', 'url' => APP_BASE_URL . '/admin/members/']];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header">
    <div><span class="cmmp-kicker">Church administration</span><h1>Members</h1><p>Search and manage church member records.</p></div>
    <a class="btn btn-primary" href="add.php"><i class="fa-solid fa-user-plus me-2"></i>Add member</a>
</div>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Member record saved successfully.</div><?php endif; ?>
<?php if (isset($_GET['status_changed'])): ?><div class="alert alert-success">Member status updated.</div><?php endif; ?>
<div class="cmmp-card mb-4">
    <form method="get" class="row g-3 align-items-end">
        <div class="col-lg-5"><label class="form-label" for="search">Search members</label><input class="form-control" id="search" name="search" value="<?= e($filters['search']) ?>" placeholder="Name, member code, or email"></div>
        <div class="col-md-3 col-lg-2"><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status"><option value="">All statuses</option><?php foreach (MEMBER_STATUSES as $status): ?><option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3 col-lg-2"><label class="form-label" for="gender">Gender</label><select class="form-select" id="gender" name="gender"><option value="">All genders</option><?php foreach (['Female', 'Male', 'Non-binary', 'Prefer not to say'] as $gender): ?><option value="<?= e($gender) ?>" <?= $filters['gender'] === $gender ? 'selected' : '' ?>><?= e($gender) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3 col-lg-2 d-flex gap-2"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter me-1"></i>Filter</button><a class="btn btn-outline-secondary" href="index.php">Reset</a></div>
    </form>
</div>
<div class="d-flex justify-content-between align-items-center mb-2"><h2 class="cmmp-section-title my-0">Member directory</h2><span class="text-muted small"><?= $result['total'] ?> record(s)</span></div>
<div class="cmmp-table-wrap">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>Member code</th><th>Name</th><th>Contact</th><th>Status</th><th>Joined</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php if ($result['items'] === []): ?><tr><td colspan="6" class="text-center py-5 text-muted">No members matched your filters.</td></tr><?php endif; ?>
            <?php foreach ($result['items'] as $member): ?>
                <tr>
                    <td class="fw-semibold"><?= e($member['member_number']) ?></td>
                    <td><?= e(trim($member['first_name'] . ' ' . ($member['middle_name'] ?? '') . ' ' . $member['last_name'])) ?></td>
                    <td><div><?= e($member['email'] ?? 'No email') ?></div><small class="text-muted"><?= e($member['phone'] ?? 'No phone') ?></small></td>
                    <td><span class="badge text-bg-<?= $member['membership_status'] === 'ACTIVE' ? 'success' : 'secondary' ?>"><?= e($member['membership_status']) ?></span></td>
                    <td><?= e($member['joined_at'] ?? 'Not set') ?></td>
                    <td class="text-end"><div class="cmmp-row-actions"><a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int) $member['id'] ?>">View</a> <a class="btn btn-sm btn-outline-primary" href="edit.php?id=<?= (int) $member['id'] ?>">Edit</a></div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if ($totalPages > 1): ?>
<nav class="mt-4" aria-label="Member pages"><ul class="pagination justify-content-end">
    <?php for ($number = 1; $number <= $totalPages; $number++): ?>
        <?php $query = http_build_query(array_merge($filters, ['page' => $number])); ?>
        <li class="page-item <?= $number === $page ? 'active' : '' ?>"><a class="page-link" href="?<?= e($query) ?>"><?= $number ?></a></li>
    <?php endfor; ?>
</ul></nav>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
