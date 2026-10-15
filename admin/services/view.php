<?php
declare(strict_types=1);
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/service_request.php';
require_once __DIR__ . '/../../services/AvailabilityService.php';

require_role(SERVICE_REVIEW_ROLES);
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$request = $id ? ServiceRequest::find($id) : null;
if (!$request) {
    http_response_code(404);
    exit('Service request not found.');
}

$canAssign = has_role(SERVICE_ASSIGN_ROLES);
$assignmentRoles = service_assignment_roles((string) $request['service_code']);
$personnelLabel = service_personnel_label((string) $request['service_code']);
$assigned = service_assigned_personnel($request);
$roster = ['available' => [], 'unavailable' => []];
$availabilityError = null;
$preferredStart = strtotime((string) ($request['preferred_start_datetime'] ?? ''));
$preferredEnd = strtotime((string) ($request['preferred_end_datetime'] ?? ''));
if ($canAssign && $assignmentRoles !== [] && $preferredStart !== false && $preferredEnd !== false && $preferredEnd > $preferredStart) {
    try {
        $roster = AvailabilityService::rosterForWindow(
            $assignmentRoles,
            date('Y-m-d H:i:s', $preferredStart),
            date('Y-m-d H:i:s', $preferredEnd),
            (int) $request['id']
        );
    } catch (Throwable $exception) {
        $availabilityError = $exception->getMessage();
    }
}

$assignedBy = '';
if ($assigned && !empty($assigned['assigned_by_user_id'])) {
    $assignedByQuery = database()->prepare('SELECT username FROM users WHERE id = :id LIMIT 1');
    $assignedByQuery->execute(['id' => $assigned['assigned_by_user_id']]);
    $assignedBy = (string) $assignedByQuery->fetchColumn();
}

$pageTitle = 'Booking request';
$activeNav = has_role('PASTOR') && !has_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']) ? 'pastor-review' : 'service-requests';
$breadcrumbItems = [
    ['label' => 'Service requests', 'url' => 'index.php'],
    ['label' => $request['request_number'], 'url' => 'view.php?id=' . $id],
];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
$workflowConflict = trim((string) ($_GET['conflict'] ?? ''));
$assignedNotice = isset($_GET['assigned']);
?>
<?php if ($workflowConflict !== ''): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= e($workflowConflict) ?> The request was not confirmed.</div>
<?php endif; ?>
<?php if ($assignedNotice): ?>
    <div class="alert alert-success">Personnel assignment saved. The member has been notified.</div>
<?php endif; ?>

<div class="cmmp-page-header">
    <div>
        <h1>Booking request</h1>
        <p><?= e($request['request_number']) ?> · <?= e($request['service_name']) ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="index.php">Back</a>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="cmmp-card">
            <h2 class="h5">Booking details</h2>
            <dl class="row mb-0">
                <dt class="col-sm-5">Service</dt>
                <dd class="col-sm-7"><?= e($request['service_name']) ?></dd>
                <dt class="col-sm-5">Requested by</dt>
                <dd class="col-sm-7"><?= e($request['member_name']) ?></dd>
                <dt class="col-sm-5">Preferred date</dt>
                <dd class="col-sm-7"><?= e($preferredStart ? date('F j, Y', $preferredStart) : 'Not provided') ?></dd>
                <dt class="col-sm-5">Preferred time</dt>
                <dd class="col-sm-7"><?= e($preferredStart ? date('g:i A', $preferredStart) : 'Not provided') ?></dd>
                <dt class="col-sm-5">Facility</dt>
                <dd class="col-sm-7"><?= e($request['preferred_facility_name'] ?? 'No preference') ?></dd>
                <dt class="col-sm-5">Status</dt>
                <dd class="col-sm-7"><span class="badge text-bg-<?= $request['status'] === 'COMPLETED' ? 'success' : 'secondary' ?>"><?= e(service_status_label((string) $request['status'])) ?></span></dd>
                <?php foreach ($request['request_data'] as $key => $value): ?>
                    <dt class="col-sm-5"><?= e(ucwords(str_replace('_', ' ', (string) $key))) ?></dt>
                    <dd class="col-sm-7"><?= e((string) $value) ?></dd>
                <?php endforeach; ?>
            </dl>
        </div>

        <div class="cmmp-card mt-3">
            <h2 class="h5">Documents</h2>
            <?php if ($request['documents'] === []): ?>
                <p class="text-muted mb-0">No documents uploaded.</p>
            <?php else: ?>
                <ul class="mb-0">
                    <?php foreach ($request['documents'] as $document): ?>
                        <li>
                            <a href="<?= e(APP_BASE_URL) ?>/services/document-download.php?id=<?= (int) $document['id'] ?>"><?= e($document['original_filename']) ?></a>
                            <span class="badge text-bg-secondary"><?= e($document['document_status']) ?></span>
                            <?php if ($document['document_status'] === 'REJECTED'): ?>
                                <small class="d-block text-danger"><?= e($document['rejection_reason']) ?></small>
                            <?php endif; ?>
                            <form class="mt-2" method="post" action="document-review.php">
                                <input type="hidden" name="document_id" value="<?= (int) $document['id'] ?>">
                                <?= csrf_field() ?>
                                <select class="form-select form-select-sm mb-1" name="document_status">
                                    <option value="VERIFIED">Verify</option>
                                    <option value="REJECTED">Reject</option>
                                </select>
                                <input class="form-control form-control-sm mb-1" name="rejection_reason" placeholder="Required for rejection">
                                <button class="btn btn-sm btn-outline-primary" type="submit">Save document review</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="cmmp-card mt-3">
            <h2 class="h5">Submitted member information</h2>
            <?php if (trim((string) ($request['description'] ?? '')) !== ''): ?>
                <p class="mb-3"><strong>Notes:</strong><br><?= nl2br(e((string) $request['description'])) ?></p>
            <?php endif; ?>
            <?php if ($request['participants'] === []): ?>
                <p class="text-muted mb-0">No additional participants were submitted.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Role</th><th>Name</th><th>Contact</th></tr></thead>
                        <tbody>
                        <?php foreach ($request['participants'] as $participant): ?>
                            <tr>
                                <td><?= e($participant['participant_role']) ?></td>
                                <td><?= e($participant['participant_name']) ?></td>
                                <td><?= e($participant['contact_phone'] ?? $participant['contact_email'] ?? 'Not provided') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-5">
        <?php if ($canAssign): ?>
            <div class="cmmp-card">
                <h2 class="h5">Personnel assignment</h2>
                <?php if ($assignmentRoles === []): ?>
                    <p class="mb-0">This service does not require a pastor or counselor unless church rules later require one. Confirm the facility schedule after review.</p>
                <?php else: ?>
                    <?php if ($assigned): ?>
                        <p class="mb-1"><strong>Assigned <?= e($personnelLabel) ?>:</strong> <?= e($assigned['staff_name']) ?></p>
                        <p class="mb-1"><strong>Assigned by:</strong> <?= e($assignedBy !== '' ? $assignedBy : 'Admin') ?></p>
                        <p class="mb-3"><strong>Assigned date:</strong> <?= e(date('F j, Y', strtotime((string) $assigned['created_at']))) ?></p>
                    <?php endif; ?>

                    <?php if ($availabilityError !== null): ?>
                        <div class="alert alert-danger"><?= e($availabilityError) ?></div>
                    <?php endif; ?>

                    <p class="small text-muted">Available personnel are checked against existing bookings, assignments, and the requested date and time. The system does not auto-assign.</p>
                    <p class="mb-1"><strong>Available</strong></p>
                    <?php if ($roster['available'] === []): ?>
                        <p class="text-muted">No <?= e(strtolower($personnelLabel)) ?> is available for this schedule.</p>
                    <?php else: ?>
                        <ul class="mb-3">
                            <?php foreach ($roster['available'] as $person): ?>
                                <li>✓ <?= e($person['display_name']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if ($roster['unavailable'] !== []): ?>
                        <p class="mb-1"><strong>Unavailable</strong></p>
                        <ul class="mb-3">
                            <?php foreach ($roster['unavailable'] as $person): ?>
                                <li>✕ <?= e($person['display_name']) ?><br><small class="text-muted">Reason: <?= e((string) ($person['unavailable_reason'] ?? 'Already assigned to another service')) ?></small></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <form method="post" action="assign.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $request['id'] ?>">
                        <label class="form-label" for="staff_id"><?= e($personnelLabel) ?></label>
                        <select class="form-select mb-2" id="staff_id" name="staff_id" required <?= $roster['available'] === [] ? 'disabled' : '' ?>>
                            <option value="">Select available <?= e(strtolower($personnelLabel)) ?></option>
                            <?php foreach ($roster['available'] as $person): ?>
                                <option value="<?= (int) $person['id'] ?>"><?= e($person['display_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-primary" type="submit" <?= $roster['available'] === [] ? 'disabled' : '' ?>>Assign <?= e($personnelLabel) ?></button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="cmmp-card <?= $canAssign ? 'mt-3' : '' ?>">
            <h2 class="h5">Review and workflow</h2>
            <form method="post" action="workflow.php">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $request['id'] ?>">
                <label class="form-label" for="status">Status</label>
                <select class="form-select mb-3" id="status" name="status">
                    <?php foreach (SERVICE_REQUEST_STATUSES as $status): ?>
                        <option value="<?= e($status) ?>" <?= $request['status'] === $status ? 'selected' : '' ?>><?= e(service_status_label($status)) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="form-label" for="note">Review note</label>
                <textarea class="form-control mb-3" id="note" name="note" rows="3"></textarea>
                <button class="btn btn-primary" type="submit">Update status</button>
            </form>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <form method="post" action="workflow.php"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $request['id'] ?>"><input type="hidden" name="status" value="CONFIRMED"><input type="hidden" name="note" value="Confirmed."><button class="btn btn-success" type="submit">Confirm</button></form>
                <form method="post" action="workflow.php"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $request['id'] ?>"><input type="hidden" name="status" value="REJECTED"><input type="hidden" name="note" value="Declined."><button class="btn btn-outline-danger" type="submit">Decline</button></form>
                <form method="post" action="workflow.php"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $request['id'] ?>"><input type="hidden" name="status" value="RESCHEDULE_REQUIRED"><input type="hidden" name="note" value="Please choose another schedule."><button class="btn btn-outline-warning" type="submit">Reschedule required</button></form>
            </div>
            <p class="small text-muted mt-3 mb-0">Admin and authorized staff assign pastors or counselors. Members never see this availability list.</p>
        </div>

        <div class="cmmp-card mt-3">
            <h2 class="h5">Assigned personnel</h2>
            <?php if ($request['assignments'] === []): ?>
                <p class="text-muted mb-0">Not yet assigned.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Name</th><th>Role</th><th>Assigned</th></tr></thead>
                        <tbody>
                        <?php foreach ($request['assignments'] as $assignment): ?>
                            <tr>
                                <td><?= e($assignment['staff_name']) ?></td>
                                <td><?= e($assignment['assignment_role']) ?></td>
                                <td><?= e($assignment['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="cmmp-card mt-3">
    <h2 class="h5">Status history</h2>
    <?php foreach ($request['status_history'] as $history): ?>
        <p class="small mb-2"><strong><?= e(service_status_label((string) $history['new_status'])) ?></strong> <span class="text-muted"><?= e($history['created_at']) ?> · <?= e($history['change_note'] ?? '') ?></span></p>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
