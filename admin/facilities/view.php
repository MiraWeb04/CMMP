<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/facility.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$facility = $id ? Facility::find($id) : null;
if (!$facility) { http_response_code(404); exit('Facility not found.'); }
$pageTitle = 'Facility profile';
$breadcrumbItems = [['label' => 'Facilities', 'url' => 'index.php'], ['label' => $facility['name'], 'url' => 'view.php?id=' . (int) $facility['id']]];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header"><div><h1><?= e($facility['name']) ?></h1><p><?= e($facility['code']) ?> · Facility profile</p></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="index.php">Back</a><a class="btn btn-primary" href="edit.php?id=<?= (int) $facility['id'] ?>">Edit</a></div></div>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Facility saved successfully.</div><?php endif; ?><?php if (isset($_GET['status_changed'])): ?><div class="alert alert-success">Facility status updated.</div><?php endif; ?>
<div class="row g-3"><div class="col-lg-8"><div class="cmmp-card"><h2 class="h5">Facility details</h2><dl class="row mb-0"><dt class="col-sm-4">Name</dt><dd class="col-sm-8"><?= e($facility['name']) ?></dd><dt class="col-sm-4">Code</dt><dd class="col-sm-8"><?= e($facility['code']) ?></dd><dt class="col-sm-4">Type</dt><dd class="col-sm-8"><?= e(str_replace('_', ' ', $facility['facility_type'])) ?></dd><dt class="col-sm-4">Description</dt><dd class="col-sm-8"><?= nl2br(e($facility['description'] ?? 'Not provided')) ?></dd><dt class="col-sm-4">Location</dt><dd class="col-sm-8"><?= e($facility['location'] ?? 'Not provided') ?></dd><dt class="col-sm-4">Capacity</dt><dd class="col-sm-8"><?= e((string) ($facility['capacity'] ?? 'Not set')) ?></dd></dl></div></div><div class="col-lg-4"><div class="cmmp-card"><h2 class="h5">Availability status</h2><span class="badge text-bg-<?= $facility['facility_status'] === 'AVAILABLE' ? 'success' : 'secondary' ?> mb-3"><?= e($facility['facility_status']) ?></span><p class="text-muted small">This facility is included in calendar availability and conflict checking for events, appointments, and service or reservation requests.</p><form method="post" action="toggle.php" onsubmit="return confirm('Change this facility status?');"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $facility['id'] ?>"><button class="btn btn-outline-<?= $facility['facility_status'] === 'AVAILABLE' ? 'danger' : 'success' ?>" type="submit"><?= $facility['facility_status'] === 'AVAILABLE' ? 'Deactivate facility' : 'Activate facility' ?></button></form></div></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
