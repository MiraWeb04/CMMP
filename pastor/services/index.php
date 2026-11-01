<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/service_request.php';
require_once __DIR__ . '/../../models/Personnel.php';
require_once __DIR__ . '/../../models/ServiceRequest.php';
require_once __DIR__ . '/../../services/SchedulingService.php';

require_role('PASTOR');
$person = Personnel::findForUser((int) current_user_id());
if (!$person) {
    http_response_code(404);
    exit('Pastor profile not found.');
}

$services = Personnel::serviceAssignments((int) $person['id']);
$events = Personnel::eventAssignments((int) $person['id']);
$appointments = SchedulingService::appointmentsForStaff((int) $person['id']);
$pageTitle = 'Pastoral assignments';
$activeNav = 'pastor-services';
$breadcrumbItems = [['label' => 'Pastoral assignments', 'url' => 'index.php']];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header">
    <div>
        <h1>Pastoral assignments</h1>
        <p>Approve, decline, reschedule, or complete the services and events assigned to <?= e($person['display_name']) ?>.</p>
    </div>
    <a class="btn btn-outline-primary" href="<?= e(APP_BASE_URL) ?>/admin/services/calendar.php"><i class="fa-solid fa-calendar-days me-2"></i>Church calendar</a>
</div>
<?php if (isset($_GET['approved'])): ?><div class="alert alert-success">The request is approved. The member has been notified.</div><?php endif; ?>
<?php if (isset($_GET['declined'])): ?><div class="alert alert-success">The request was declined. The member has been notified.</div><?php endif; ?>
<?php if (isset($_GET['completed'])): ?><div class="alert alert-success">The service is marked completed.</div><?php endif; ?>
<?php if (!empty($_GET['error'])): ?><div class="alert alert-danger"><?= e((string) $_GET['error']) ?></div><?php endif; ?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="cmmp-card">
            <h2 class="h5">Assigned services</h2>
            <?php foreach ($services as $item): ?>
                <?php $completed = strtoupper((string) $item['status']) === 'COMPLETED'; ?>
                <div class="d-flex justify-content-between align-items-start gap-3 border-bottom py-3">
                    <div>
                        <a href="<?= e(APP_BASE_URL) ?>/admin/services/view.php?id=<?= (int) $item['id'] ?>"><?= e($item['request_number']) ?></a>
                        <div class="text-muted small"><?= e($item['service_name']) ?> · <?= e($item['assignment_role']) ?></div>
                        <?php if ($completed): ?>
                            <span class="badge text-bg-success mt-2">Completed</span>
                        <?php else: ?>
                            <span class="badge text-bg-light mt-2"><?= e(service_status_label((string) $item['status'])) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php
                    $itemStatus = strtoupper((string) $item['status']);
                    $canApprove = in_array($itemStatus, ['PENDING', 'PENDING_ASSIGNMENT', 'UNDER_REVIEW', 'DOCUMENTS_REQUIRED', 'ASSIGNED', 'RESCHEDULE_REQUIRED'], true);
                    $canDecline = !in_array($itemStatus, ['REJECTED', 'CANCELLED', 'COMPLETED'], true);
                    ?>
                    <?php if ($canApprove || $canDecline): ?>
                        <div class="cmmp-row-actions">
                            <?php if ($canApprove): ?>
                                <form method="post" action="decide.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="service_request_id" value="<?= (int) $item['id'] ?>">
                                    <input type="hidden" name="decision" value="approve">
                                    <button class="btn btn-sm btn-primary" type="submit">Approve</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($canDecline): ?>
                                <form method="post" action="decide.php" onsubmit="return confirm('Decline this request?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="service_request_id" value="<?= (int) $item['id'] ?>">
                                    <input type="hidden" name="decision" value="decline">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Decline</button>
                                </form>
                            <?php endif; ?>
                            <?php if (!$completed && $canDecline): ?>
                                <form method="post" action="complete.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="service_request_id" value="<?= (int) $item['id'] ?>">
                                    <button class="btn btn-sm btn-outline-primary" type="submit">Mark completed</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if ($services === []): ?><p class="text-muted mb-0">No assigned services.</p><?php endif; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="cmmp-card">
            <h2 class="h5">Assigned events</h2>
            <?php foreach ($events as $item): ?>
                <p><?= e($item['title']) ?><br><small class="text-muted"><?= e($item['start_datetime']) ?> · <?= e($item['assignment_role'] ?? '') ?></small></p>
            <?php endforeach; ?>
            <?php if ($events === []): ?><p class="text-muted mb-0">No assigned events.</p><?php endif; ?>
        </div>
    </div>
    <div class="col-12">
        <div class="cmmp-card">
            <h2 class="h5">Appointments</h2>
            <?php foreach ($appointments as $item): ?>
                <?php $completed = strtoupper((string) $item['status']) === 'COMPLETED'; ?>
                <div class="d-flex justify-content-between align-items-start gap-3 border-bottom py-3">
                    <div>
                        <strong><?= e($item['appointment_type']) ?></strong>
                        <div class="text-muted small"><?= e($item['start_datetime']) ?> to <?= e($item['end_datetime']) ?> · <?= e($item['facility_name'] ?? 'No facility') ?></div>
                        <?php if ($completed): ?>
                            <span class="badge text-bg-success mt-2">Completed</span>
                        <?php else: ?>
                            <span class="badge text-bg-light mt-2"><?= e(ucwords(strtolower(str_replace('_', ' ', (string) $item['status'])))) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!$completed): ?>
                        <form method="post" action="<?= e(APP_BASE_URL) ?>/pastor/appointments/complete.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="appointment_id" value="<?= (int) $item['id'] ?>">
                            <input type="hidden" name="return" value="../services/index.php">
                            <button class="btn btn-sm btn-primary" type="submit">Mark completed</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if ($appointments === []): ?><p class="text-muted mb-0">No scheduled appointments.</p><?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
