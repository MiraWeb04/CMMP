<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../models/Personnel.php';
require_once __DIR__ . '/../../services/SchedulingService.php';

require_role('COUNSELOR');

$person = Personnel::findForUser((int) current_user_id());
if (!$person) {
    http_response_code(404);
    exit('Counselor profile not found.');
}

$services = Personnel::serviceAssignments((int) $person['id']);
$appointments = SchedulingService::appointmentsForStaff((int) $person['id']);
$pageTitle = 'Counseling sessions';
$activeNav = 'counselor-sessions';
$breadcrumbItems = [['label' => 'Counseling sessions', 'url' => 'index.php']];

require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header">
    <div>
        <p class="cmmp-kicker">Counselor</p>
        <h1>Assigned requests</h1>
        <p>Service requests church staff assigned to <?= e($person['display_name']) ?>.</p>
    </div>
</div>
<div class="cmmp-card mb-4">
    <h2 class="h5">Assigned requests</h2>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Request</th>
                    <th>Member</th>
                    <th>Service</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($services as $item): ?>
                    <?php $caseId = (int) ($item['counseling_case_id'] ?? 0); ?>
                    <tr>
                        <td><?= e($item['request_number']) ?><br><small class="text-muted"><?= e($item['subject']) ?></small></td>
                        <td><?= e($item['member_name'] !== '' ? $item['member_name'] : 'Member') ?></td>
                        <td><?= e($item['service_name']) ?></td>
                        <td><span class="badge text-bg-primary"><?= e(str_replace('_', ' ', (string) $item['status'])) ?></span></td>
                        <td class="text-end">
                            <?php if ($caseId > 0): ?>
                                <a class="btn btn-sm btn-primary" href="view.php?id=<?= $caseId ?>">Open case</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($services === []): ?>
                    <tr><td colspan="5" class="text-center text-muted py-5">No requests are assigned to you yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="cmmp-card">
    <h2 class="h5">Counseling appointments</h2>
    <?php foreach ($appointments as $item): ?>
        <p class="mb-3"><?= e($item['appointment_type']) ?><br><small class="text-muted"><?= e($item['start_datetime']) ?> to <?= e($item['end_datetime']) ?> · <?= e($item['status']) ?></small></p>
    <?php endforeach; ?>
    <?php if ($appointments === []): ?>
        <p class="text-muted mb-0">No counseling appointments.</p>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
