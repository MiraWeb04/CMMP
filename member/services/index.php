<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/service_request.php';
require_once __DIR__ . '/../../models/ServiceRequest.php';

require_role('MEMBER');
$memberId = ServiceRequest::memberIdForUser((int) current_user_id())
    ?? ensure_member_profile((int) current_user_id());
if (!$memberId) {
    http_response_code(404);
    exit('Your member profile has not been created yet.');
}

$requests = ServiceRequest::paginateForMember($memberId);
$pageTitle = 'Church services';
$activeNav = 'services';
$breadcrumbItems = [['label' => 'Church services', 'url' => 'index.php']];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header">
    <div>
        <h1>Church services</h1>
        <p>Check the church calendar, request a wedding, baptism, counseling, house blessing, or funeral service, or reserve a facility.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-primary" href="calendar.php"><i class="fa-solid fa-calendar-days me-2"></i>View calendar</a>
        <a class="btn btn-outline-primary" href="request.php?service_code=FACILITY_RESERVATION"><i class="fa-solid fa-building me-2"></i>Reserve a facility</a>
        <a class="btn btn-primary" href="request.php"><i class="fa-solid fa-plus me-2"></i>New request</a>
    </div>
</div>
<?php if (isset($_GET['submitted'])): ?>
    <div class="alert alert-success">Your request has been received. Church staff will review your requested schedule and assign the appropriate pastor/counselor.</div>
<?php endif; ?>
<?php if (isset($_GET['hidden'])): ?>
    <div class="alert alert-success">The completed request was deleted from your list. Church staff still have the original record.</div>
<?php endif; ?>
<?php if (!empty($_GET['hide_error'])): ?>
    <div class="alert alert-danger"><?= e((string) $_GET['hide_error']) ?></div>
<?php endif; ?>
<div class="cmmp-table-wrap">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Request</th>
                    <th>Service</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($requests === []): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">You have no service requests yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($requests as $request): ?>
                    <tr>
                        <td><a href="view.php?id=<?= (int) $request['id'] ?>"><?= e($request['request_number']) ?></a></td>
                        <td><?= e($request['service_name']) ?></td>
                        <td><?= e($request['subject']) ?></td>
                        <td><span class="badge text-bg-<?= $request['status'] === 'COMPLETED' ? 'success' : 'secondary' ?>"><?= e(service_status_label((string) $request['status'])) ?></span></td>
                        <td><?= e($request['requested_at']) ?></td>
                        <td class="text-end">
                            <?php if (ServiceRequest::memberCanHide($request, $memberId)): ?>
                                <?php
                                $hideRequestId = (int) $request['id'];
                                $hideReturnTo = 'services';
                                require __DIR__ . '/../../includes/member-hide-request-form.php';
                                ?>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
