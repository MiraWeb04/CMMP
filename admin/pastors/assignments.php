<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../models/ServiceRequest.php';
require_once __DIR__ . '/../../models/Personnel.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$person = $id ? Personnel::find($id) : null;
if (!$person) {
    http_response_code(404);
    exit('Pastor record not found.');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();
    try {
        if ((int) ($_POST['staff_id'] ?? 0) !== (int) $id) {
            throw new InvalidArgumentException('Invalid personnel assignment.');
        }
        if ($_POST['action'] === 'assign_service') {
            $serviceId = filter_var($_POST['service_request_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$serviceId) { throw new InvalidArgumentException('Select a service request.'); }
            ServiceRequest::assign((int) $serviceId, (int) $id, 'PASTOR', (int) current_user_id());
        } elseif ($_POST['action'] === 'assign_event') {
            $eventId = filter_var($_POST['event_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$eventId) { throw new InvalidArgumentException('Select an event.'); }
            Personnel::saveEventAssignment((int) $eventId, (int) $id, (string) ($_POST['assignment_role'] ?? ''));
        }
        header('Location: assignments.php?id=' . (int) $id . '&saved=1');
        exit;
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
}

$connection = database();
$availableServices = $connection->query("SELECT sr.id, sr.request_number, st.name AS service_name, sr.subject FROM service_requests sr INNER JOIN service_types st ON st.id = sr.service_type_id WHERE sr.status <> 'CANCELLED' ORDER BY sr.updated_at DESC")->fetchAll();
$availableEvents = $connection->query("SELECT id, title, start_datetime FROM events WHERE event_status <> 'CANCELLED' ORDER BY start_datetime DESC")->fetchAll();

$services = Personnel::serviceAssignments((int) $id);
$events = Personnel::eventAssignments((int) $id);
$pageTitle = 'Pastor assignments';
$activeNav = 'pastors';
$breadcrumbItems = [['label' => 'Pastors', 'url' => 'index.php'], ['label' => 'Assignments', 'url' => 'assignments.php?id=' . $id]];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header"><div><h1><?= e($person['display_name']) ?></h1><p>Services and events assigned to this pastor.</p></div><a class="btn btn-outline-secondary" href="index.php">Back to pastors</a></div>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Assignment saved successfully.</div><?php endif; ?>
<?php if ($errors !== []): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="cmmp-card mb-3"><h2 class="h5">Add assignment</h2><form method="post" class="row g-3 align-items-end"><input type="hidden" name="staff_id" value="<?= (int) $id ?>"><?= csrf_field() ?><div class="col-lg-5"><label class="form-label" for="service_request_id">Service request</label><select class="form-select" id="service_request_id" name="service_request_id"><option value="">Select a service request</option><?php foreach ($availableServices as $service): ?><option value="<?= (int) $service['id'] ?>"><?= e($service['request_number'] . ' - ' . $service['service_name'] . ($service['subject'] !== '' ? ' - ' . $service['subject'] : '')) ?></option><?php endforeach; ?></select></div><div class="col-lg-2"><input type="hidden" name="action" value="assign_service"><button class="btn btn-primary w-100" type="submit">Assign service</button></div></form><hr><form method="post" class="row g-3 align-items-end"><input type="hidden" name="staff_id" value="<?= (int) $id ?>"><?= csrf_field() ?><div class="col-lg-5"><label class="form-label" for="event_id">Event</label><select class="form-select" id="event_id" name="event_id"><option value="">Select an event</option><?php foreach ($availableEvents as $event): ?><option value="<?= (int) $event['id'] ?>"><?= e($event['title'] . ' - ' . $event['start_datetime']) ?></option><?php endforeach; ?></select></div><div class="col-lg-3"><label class="form-label" for="assignment_role">Role</label><input class="form-control" id="assignment_role" name="assignment_role" placeholder="Pastor"></div><div class="col-lg-2"><input type="hidden" name="action" value="assign_event"><button class="btn btn-outline-primary w-100" type="submit">Assign event</button></div></form></div>
<div class="row g-3">
    <div class="col-lg-6"><div class="cmmp-card"><h2 class="h5">Assigned service requests</h2><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead><tr><th>Request</th><th>Service</th><th>Role</th><th>Status</th></tr></thead><tbody><?php foreach ($services as $item): ?><tr><td><a href="../services/view.php?id=<?= (int) $item['id'] ?>"><?= e($item['request_number']) ?></a><?php if ($item['subject'] !== ''): ?><small class="d-block text-muted"><?= e($item['subject']) ?></small><?php endif; ?></td><td><?= e($item['service_name']) ?></td><td><?= e($item['assignment_role']) ?></td><td><?= e($item['status']) ?></td></tr><?php endforeach; ?><?php if ($services === []): ?><tr><td colspan="4" class="text-center text-muted py-3">No assigned service requests.</td></tr><?php endif; ?></tbody></table></div></div></div>
    <div class="col-lg-6"><div class="cmmp-card"><h2 class="h5">Assigned events</h2><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead><tr><th>Event</th><th>Role</th><th>Start</th><th>Status</th></tr></thead><tbody><?php foreach ($events as $item): ?><tr><td><?= e($item['title']) ?></td><td><?= e($item['assignment_role'] ?? 'Event assignment') ?></td><td><?= e($item['start_datetime']) ?></td><td><?= e($item['event_status']) ?></td></tr><?php endforeach; ?><?php if ($events === []): ?><tr><td colspan="4" class="text-center text-muted py-3">No assigned events.</td></tr><?php endif; ?></tbody></table></div></div></div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
