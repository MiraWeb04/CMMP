<?php
declare(strict_types=1);
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/service_request.php';
require_once __DIR__ . '/../../models/Counseling.php';
require_role('MEMBER');
$memberId = ServiceRequest::memberIdForUser((int) current_user_id());
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$request = $id ? ServiceRequest::find($id) : null;
if (!$request || (int) $request['requested_by_member_id'] !== $memberId || !empty($request['hidden_from_member_at'])) {
    http_response_code(404);
    exit('Service request not found.');
}
$caseLookup = database()->prepare('SELECT id FROM counseling_cases WHERE service_request_id = :request_id');
$caseLookup->execute(['request_id' => $request['id']]);
$counselingCase = ($caseId = $caseLookup->fetchColumn()) ? Counseling::caseForMember((int) $caseId, $memberId) : null;
$assigned = service_assigned_personnel($request);
$personnelLabel = service_personnel_label((string) $request['service_code']);
$preferredStart = strtotime((string) ($request['preferred_start_datetime'] ?? ''));
$statusLabel = service_status_label((string) $request['status']);
$isPendingAssignment = in_array((string) $request['status'], ['PENDING', 'PENDING_ASSIGNMENT', 'UNDER_REVIEW'], true);

$pageTitle = 'Service request';
$activeNav = 'services';
$breadcrumbItems = [
    ['label' => 'Church services', 'url' => 'index.php'],
    ['label' => $request['request_number'], 'url' => 'view.php?id=' . $id],
];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header">
    <div>
        <h1><?= e($request['service_name']) ?></h1>
        <p>Booking Reference: <?= e($request['request_number']) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (ServiceRequest::memberCanHide($request, $memberId)): ?>
            <?php
            $hideRequestId = (int) $request['id'];
            $hideReturnTo = 'services';
            $hideButtonClass = 'btn btn-outline-danger';
            $hideButtonLabel = 'Remove from dashboard';
            require __DIR__ . '/../../includes/member-hide-request-form.php';
            ?>
        <?php endif; ?>
        <a class="btn btn-outline-secondary" href="index.php">Back</a>
    </div>
</div>

<div class="alert <?= $isPendingAssignment ? 'alert-info' : 'alert-light' ?> border">
    <?php if ($isPendingAssignment): ?>
        Your request has been received. Church staff will review your requested schedule and assign the appropriate pastor/counselor.
    <?php else: ?>
        Church staff has updated this booking. Check the assigned personnel and current status below.
    <?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="cmmp-card">
            <h2 class="h5">Booking details</h2>
            <dl class="row mb-0">
                <dt class="col-sm-5">Booking Reference</dt>
                <dd class="col-sm-7"><?= e($request['request_number']) ?></dd>
                <dt class="col-sm-5">Service</dt>
                <dd class="col-sm-7"><?= e($request['service_name']) ?></dd>
                <dt class="col-sm-5">Preferred Date</dt>
                <dd class="col-sm-7"><?= e($preferredStart ? date('F j, Y', $preferredStart) : 'Not provided') ?></dd>
                <dt class="col-sm-5">Preferred Time</dt>
                <dd class="col-sm-7"><?= e($preferredStart ? date('g:i A', $preferredStart) : 'Not provided') ?></dd>
                <dt class="col-sm-5">Facility</dt>
                <dd class="col-sm-7"><?= e($request['preferred_facility_name'] ?? 'No preference') ?></dd>
                <dt class="col-sm-5">Status</dt>
                <dd class="col-sm-7"><span class="badge text-bg-<?= $request['status'] === 'COMPLETED' ? 'success' : 'secondary' ?>"><?= e($statusLabel) ?></span></dd>
                <?php if (service_assignment_roles((string) $request['service_code']) !== []): ?>
                    <dt class="col-sm-5">Assigned <?= e($personnelLabel) ?></dt>
                    <dd class="col-sm-7"><?= e($assigned['staff_name'] ?? 'Not yet assigned') ?></dd>
                <?php endif; ?>
                <?php foreach ($request['request_data'] as $key => $value): ?>
                    <dt class="col-sm-5"><?= e(ucwords(str_replace('_', ' ', (string) $key))) ?></dt>
                    <dd class="col-sm-7"><?= e((string) $value) ?></dd>
                <?php endforeach; ?>
            </dl>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="cmmp-card">
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
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <form class="mt-3" method="post" action="document-upload.php" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                <label class="form-label" for="document">Upload or replace document</label>
                <input class="form-control" id="document" name="document[]" type="file" accept="application/pdf,image/jpeg,image/png" required>
                <input class="form-control mt-2" name="document_type" value="SUPPORTING_DOCUMENT" maxlength="100">
                <button class="btn btn-outline-primary btn-sm mt-2" type="submit">Upload document</button>
            </form>
        </div>
        <div class="cmmp-card mt-3">
            <h2 class="h5">Status history</h2>
            <?php foreach ($request['status_history'] as $history): ?>
                <p class="small mb-2"><strong><?= e(service_status_label((string) $history['new_status'])) ?></strong><br><span class="text-muted"><?= e($history['created_at']) ?><?= $history['change_note'] ? ' · ' . e($history['change_note']) : '' ?></span></p>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php if ($counselingCase): ?>
<div class="cmmp-card mt-3">
    <h2 class="h5">Pre-marriage counseling</h2>
    <p><strong>Status:</strong> <?= e($counselingCase['status']) ?></p>
    <?php if ($counselingCase['counselor']): ?><p><strong>Counselor:</strong> <?= e($counselingCase['counselor']['display_name']) ?></p><?php endif; ?>
    <p class="text-muted small">Configured sessions: <?= (int) $counselingCase['required_sessions'] ?></p>
    <div class="row g-2">
        <?php foreach ($counselingCase['sessions'] as $session): ?>
            <div class="col-md-6"><div class="border rounded p-2"><strong>Session <?= (int) $session['session_number'] ?></strong><br><span class="text-muted"><?= e($session['status']) ?> · <?= e($session['start_datetime'] ?? 'Not scheduled') ?></span></div></div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
