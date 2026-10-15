<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/event.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);
$search = trim((string) ($_GET['search'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$result = Event::paginateAdmin($search, $status, $page, $perPage);
$totalPages = max(1, (int) ceil($result['total'] / $perPage));
$page = min($page, $totalPages);
$pageTitle = 'Events';
$activeNav = 'events-admin';
$breadcrumbItems = [['label' => 'Events', 'url' => 'index.php']];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header"><div><h1>Events</h1><p>Create and publish church events connected to centralized facilities.</p></div><a class="btn btn-primary" href="add.php"><i class="fa-solid fa-calendar-plus me-2"></i>Create event</a></div>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Event saved successfully.</div><?php endif; ?><?php if (isset($_GET['status_changed'])): ?><div class="alert alert-success">Event status updated.</div><?php endif; ?>
<div class="cmmp-card mb-4"><form method="get" class="row g-3 align-items-end"><div class="col-lg-6"><label class="form-label" for="search">Search events</label><input class="form-control" id="search" name="search" value="<?= e($search) ?>" placeholder="Title or description"></div><div class="col-md-3 col-lg-2"><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status"><option value="">All statuses</option><?php foreach (EVENT_STATUSES as $item): ?><option value="<?= e($item) ?>" <?= $status === $item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select></div><div class="col-auto"><button class="btn btn-primary" type="submit">Filter</button><a class="btn btn-outline-secondary ms-2" href="index.php">Reset</a></div></form></div>
<div class="cmmp-table-wrap"><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Event</th><th>Schedule</th><th>Category</th><th>Facility</th><th>Registrations</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody><?php if ($result['items'] === []): ?><tr><td colspan="7" class="text-center py-5 text-muted">No events found.</td></tr><?php endif; ?><?php foreach ($result['items'] as $event): ?><tr><td><a class="fw-semibold" href="view.php?id=<?= (int) $event['id'] ?>"><?= e($event['title']) ?></a><?php if ($event['registration_required']): ?><small class="d-block text-muted">Registration required</small><?php endif; ?></td><td><?= e($event['start_datetime']) ?><small class="d-block text-muted">to <?= e($event['end_datetime']) ?></small></td><td><?= e($event['category_name'] ?? 'Uncategorized') ?></td><td><?= e($event['facility_name'] ?? 'None') ?></td><td><?= (int) $event['registration_count'] ?><?= $event['capacity'] !== null ? ' / ' . (int) $event['capacity'] : '' ?></td><td><span class="badge text-bg-<?= $event['event_status'] === 'PUBLISHED' ? 'success' : ($event['event_status'] === 'CANCELLED' ? 'danger' : 'secondary') ?>"><?= e($event['event_status']) ?></span></td><td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int) $event['id'] ?>">View</a> <a class="btn btn-sm btn-outline-secondary" href="edit.php?id=<?= (int) $event['id'] ?>">Edit</a></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php if ($totalPages > 1): ?><nav class="mt-4"><ul class="pagination justify-content-end"><?php for ($number = 1; $number <= $totalPages; $number++): ?><li class="page-item <?= $number === $page ? 'active' : '' ?>"><a class="page-link" href="?<?= e(http_build_query(['search' => $search, 'status' => $status, 'page' => $number])) ?>"><?= $number ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
