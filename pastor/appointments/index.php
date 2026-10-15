<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../models/Personnel.php';
require_once __DIR__ . '/../../services/SchedulingService.php';

require_role('PASTOR');
$person = Personnel::findForUser((int) current_user_id());
if (!$person) {
    http_response_code(404);
    exit('Pastor profile not found.');
}

$appointments = SchedulingService::appointmentsForStaff((int) $person['id']);
$pageTitle = 'Appointments';
$activeNav = 'pastor-appointments';
$breadcrumbItems = [['label' => 'Appointments', 'url' => 'index.php']];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header">
    <div>
        <h1>Appointments</h1>
        <p>Mark an appointment completed after the pastoral visit or service is finished.</p>
    </div>
</div>
<?php if (isset($_GET['completed'])): ?><div class="alert alert-success">The appointment is marked completed.</div><?php endif; ?>
<?php if (!empty($_GET['error'])): ?><div class="alert alert-danger"><?= e((string) $_GET['error']) ?></div><?php endif; ?>
<div class="cmmp-card">
    <?php if ($appointments === []): ?>
        <p class="text-muted mb-0">No appointments are assigned to you.</p>
    <?php endif; ?>
    <?php foreach ($appointments as $item): ?>
        <?php $completed = strtoupper((string) $item['status']) === 'COMPLETED'; ?>
        <div class="d-flex justify-content-between align-items-start gap-3 border-bottom py-3">
            <div>
                <strong><?= e($item['appointment_type']) ?></strong>
                <?php if (!empty($item['request_number'])): ?><span class="text-muted"> · <?= e($item['request_number']) ?></span><?php endif; ?>
                <div class="text-muted small"><?= e($item['start_datetime']) ?> to <?= e($item['end_datetime']) ?> · <?= e($item['facility_name'] ?? 'No facility') ?></div>
                <?php if ($completed): ?>
                    <span class="badge text-bg-success mt-2">Completed</span>
                <?php else: ?>
                    <span class="badge text-bg-light mt-2"><?= e(ucwords(strtolower(str_replace('_', ' ', (string) $item['status'])))) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!$completed): ?>
                <form method="post" action="complete.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="appointment_id" value="<?= (int) $item['id'] ?>">
                    <button class="btn btn-sm btn-primary" type="submit">Mark completed</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
