<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/event.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$event = $id ? Event::find($id) : null;
if (!$event) { http_response_code(404); exit('Event not found.'); }
$workflowConflict = trim((string) ($_GET['conflict'] ?? ''));
$pageTitle = 'Event profile';
$breadcrumbItems = [['label' => 'Events', 'url' => 'index.php'], ['label' => $event['title'], 'url' => 'view.php?id=' . (int) $event['id']]];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<?php if ($workflowConflict !== ''): ?><div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= e($workflowConflict) ?> The event was not published.</div><?php endif; ?>
<div class="cmmp-page-header"><div><h1><?= e($event['title']) ?></h1><p><?= e($event['category_name'] ?? 'Uncategorized') ?> · Event profile</p></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="index.php">Back</a><a class="btn btn-primary" href="edit.php?id=<?= (int) $event['id'] ?>">Edit</a></div></div>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Event saved successfully.</div><?php endif; ?><?php if (isset($_GET['status_changed'])): ?><div class="alert alert-success">Event status updated.</div><?php endif; ?>
<div class="row g-3"><div class="col-lg-8"><div class="cmmp-card"><?php $eventCoverClass = 'is-hero'; require __DIR__ . '/../../includes/event-cover.php'; ?><div class="d-flex justify-content-between align-items-start"><h2 class="h5">Event details</h2><span class="badge text-bg-<?= $event['event_status'] === 'PUBLISHED' ? 'success' : ($event['event_status'] === 'CANCELLED' ? 'danger' : 'secondary') ?>"><?= e($event['event_status']) ?></span></div><dl class="row mb-0"><dt class="col-sm-4">Schedule</dt><dd class="col-sm-8"><?= e($event['start_datetime']) ?><br><?= e($event['end_datetime']) ?></dd><dt class="col-sm-4">Facility</dt><dd class="col-sm-8"><?= e($event['facility_name'] ?? 'No facility assigned') ?></dd><dt class="col-sm-4">Capacity</dt><dd class="col-sm-8"><?= e((string) ($event['capacity'] ?? 'Unlimited')) ?> (<?= (int) $event['registration_count'] ?> registered)</dd><dt class="col-sm-4">Registration</dt><dd class="col-sm-8"><?= $event['registration_required'] ? 'Required' : 'Not required' ?><br><?= e($event['registration_deadline'] ?? 'No deadline') ?></dd><dt class="col-sm-4">Description</dt><dd class="col-sm-8"><?= nl2br(e($event['description'] ?? 'Not provided')) ?></dd></dl></div></div><div class="col-lg-4"><div class="cmmp-card"><h2 class="h5">Event status</h2><p class="text-muted small">Publishing controls member visibility. Published events must not overlap existing venue bookings or service requests.</p><form method="post" action="status.php"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $event['id'] ?>"><select class="form-select mb-3" name="status" required><?php foreach (EVENT_STATUSES as $status): ?><option value="<?= e($status) ?>" <?= $event['event_status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select><button class="btn btn-primary" type="submit">Update status</button></form></div></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
