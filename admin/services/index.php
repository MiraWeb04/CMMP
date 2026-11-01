<?php
declare(strict_types=1);
require_once __DIR__ . '/../../helpers/auth.php'; require_once __DIR__ . '/../../helpers/csrf.php'; require_once __DIR__ . '/../../helpers/service_request.php'; require_once __DIR__ . '/../../helpers/pagination.php';
require_role(SERVICE_REVIEW_ROLES);
$search = trim((string) ($_GET['search'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
$page = list_page();
$perPage = list_per_page();
$result = ServiceRequest::paginateForAdmin($search, $status, $page, $perPage);
$requests = $result['items'];
$page = min($page, pagination_total_pages($result['total'], $perPage));

$pageTitle = 'Service requests';
$activeNav = has_role('PASTOR') && !has_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']) ? 'pastor-review' : 'service-requests';
$breadcrumbItems = [['label' => 'Service requests', 'url' => 'index.php']];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header"><div><h1>Service requests</h1><p>Admin and authorized staff review requests, check availability, and assign pastors or counselors. Members never select personnel.</p></div><a class="btn btn-outline-primary" href="calendar.php"><i class="fa-solid fa-calendar-days me-2"></i>Church calendar</a></div>
<?php if (isset($_GET['deleted']) && $_GET['deleted'] === '1'): ?><div class="alert alert-success">Service request deleted. It had no historical appointments or counseling records.</div><?php endif; ?>
<?php if (isset($_GET['archived'])): ?><div class="alert alert-success">Service request cancelled. Related appointments, attendance, and history were kept.</div><?php endif; ?>
<?php if (!empty($_GET['error'])): ?><div class="alert alert-danger"><?= e((string) $_GET['error']) ?></div><?php endif; ?>
<div class="cmmp-card mb-4"><form method="get" class="row g-3 align-items-end"><div class="col-lg-6"><label class="form-label" for="search">Search</label><input class="form-control" id="search" name="search" value="<?= e($search) ?>" placeholder="Request number, subject, or member"></div><div class="col-md-3"><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status"><option value="">All statuses</option><?php foreach (SERVICE_REQUEST_STATUSES as $item): ?><option value="<?= e($item) ?>" <?= $status === $item ? 'selected' : '' ?>><?= e(service_status_label($item)) ?></option><?php endforeach; ?></select></div><div class="col-auto"><button class="btn btn-primary" type="submit">Filter</button></div></form></div><div class="cmmp-table-wrap"><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Request</th><th>Member</th><th>Service</th><th>Status</th><th>Submitted</th><th class="text-end">Action</th></tr></thead><tbody><?php if ($requests === []): ?><tr><td colspan="6" class="text-center text-muted py-5">No service requests found.</td></tr><?php endif; ?><?php foreach ($requests as $request): ?><tr><td><a href="view.php?id=<?= (int) $request['id'] ?>"><?= e($request['request_number']) ?></a><small class="d-block text-muted"><?= e($request['subject']) ?></small></td><td><?= e($request['member_name']) ?></td><td><?= e($request['service_name']) ?></td><td><span class="badge text-bg-secondary"><?= e(service_status_label((string) $request['status'])) ?></span></td><td><?= e($request['requested_at']) ?></td><td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int) $request['id'] ?>">View</a><?php if (has_role(['SUPER_ADMIN', 'CHURCH_ADMIN']) && !in_array((string) $request['status'], ['COMPLETED', 'CANCELLED'], true)): ?><?php $canHardDelete = ServiceRequest::canPermanentlyDelete($request); ?><form method="post" action="delete.php" class="d-inline" onsubmit="return confirm(<?= $canHardDelete ? json_encode('Permanently delete this unused request? This cannot be undone.') : json_encode('Cancel this request? Appointments, counseling, and history will be kept.') ?>);"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $request['id'] ?>"><input type="hidden" name="action" value="<?= $canHardDelete ? 'delete' : 'archive' ?>"><button class="btn btn-sm btn-outline-danger" type="submit"><?= $canHardDelete ? 'Delete' : 'Cancel' ?></button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php
$paginationTotal = $result['total'];
$paginationPage = $page;
$paginationPerPage = $perPage;
$paginationFilters = ['search' => $search, 'status' => $status];
$paginationLabel = 'Service request pages';
require __DIR__ . '/../../includes/pagination.php';
require __DIR__ . '/../../includes/footer.php';
?>
