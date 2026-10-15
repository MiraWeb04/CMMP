<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/facility.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);
$search = trim((string) ($_GET['search'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
$type = (string) ($_GET['type'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$result = Facility::paginate($search, $status, $type, $page, $perPage);
$totalPages = max(1, (int) ceil($result['total'] / $perPage));
$page = min($page, $totalPages);
$pageTitle = 'Facilities';
$activeNav = 'facilities';
$breadcrumbItems = [['label' => 'Facilities', 'url' => 'index.php']];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header"><div><h1>Facilities</h1><p>Manage church spaces that can later be selected by centralized appointments.</p></div><a class="btn btn-primary" href="add.php"><i class="fa-solid fa-building me-2"></i>Add facility</a></div>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Facility saved successfully.</div><?php endif; ?>
<?php if (isset($_GET['status_changed'])): ?><div class="alert alert-success">Facility status updated.</div><?php endif; ?>
<div class="cmmp-card mb-4"><form method="get" class="row g-3 align-items-end"><div class="col-lg-5"><label class="form-label" for="search">Search facilities</label><input class="form-control" id="search" name="search" value="<?= e($search) ?>" placeholder="Name, code, or location"></div><div class="col-md-3 col-lg-2"><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status"><option value="">All statuses</option><?php foreach (FACILITY_STATUSES as $item): ?><option value="<?= e($item) ?>" <?= $status === $item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select></div><div class="col-md-3 col-lg-2"><label class="form-label" for="type">Type</label><select class="form-select" id="type" name="type"><option value="">All types</option><?php foreach (FACILITY_TYPES as $item): ?><option value="<?= e($item) ?>" <?= $type === $item ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $item)) ?></option><?php endforeach; ?></select></div><div class="col-md-3 col-lg-2 d-flex gap-2"><button class="btn btn-primary" type="submit">Filter</button><a class="btn btn-outline-secondary" href="index.php">Reset</a></div></form></div>
<div class="d-flex justify-content-between align-items-center mb-2"><h2 class="cmmp-section-title my-0">Facility directory</h2><span class="text-muted small"><?= $result['total'] ?> record(s)</span></div>
<div class="cmmp-table-wrap"><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Name</th><th>Code</th><th>Type</th><th>Capacity</th><th>Status</th><th>Appointments</th><th class="text-end">Actions</th></tr></thead><tbody><?php if ($result['items'] === []): ?><tr><td colspan="7" class="text-center py-5 text-muted">No facilities found.</td></tr><?php endif; ?><?php foreach ($result['items'] as $facility): ?><tr><td><a class="fw-semibold" href="view.php?id=<?= (int) $facility['id'] ?>"><?= e($facility['name']) ?></a><small class="d-block text-muted"><?= e($facility['location'] ?? 'Location not set') ?></small></td><td><?= e($facility['code']) ?></td><td><?= e(str_replace('_', ' ', $facility['facility_type'])) ?></td><td><?= e((string) ($facility['capacity'] ?? 'Not set')) ?></td><td><span class="badge text-bg-<?= $facility['facility_status'] === 'AVAILABLE' ? 'success' : 'secondary' ?>"><?= e($facility['facility_status']) ?></span></td><td><?= (int) $facility['appointment_count'] ?></td><td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int) $facility['id'] ?>">View</a> <a class="btn btn-sm btn-outline-secondary" href="edit.php?id=<?= (int) $facility['id'] ?>">Edit</a></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php if ($totalPages > 1): ?><nav class="mt-4"><ul class="pagination justify-content-end"><?php for ($number = 1; $number <= $totalPages; $number++): ?><li class="page-item <?= $number === $page ? 'active' : '' ?>"><a class="page-link" href="?<?= e(http_build_query(['search' => $search, 'status' => $status, 'type' => $type, 'page' => $number])) ?>"><?= $number ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
