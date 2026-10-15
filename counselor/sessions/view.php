<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/service_request.php';
require_once __DIR__ . '/../../models/Personnel.php';
require_once __DIR__ . '/../../models/Counseling.php';

require_role('COUNSELOR');

$person = Personnel::findForUser((int) current_user_id());
$caseId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$case = $caseId ? Counseling::findCase($caseId) : null;
if (!$person || !$case) {
    http_response_code(404);
    exit('Counseling case not found.');
}

$assigned = false;
foreach (Counseling::casesForCounselor((int) $person['id']) as $item) {
    if ((int) $item['id'] === $caseId) {
        $assigned = true;
        break;
    }
}
if (!$assigned) {
    http_response_code(403);
    exit('Forbidden');
}

$details = is_array($case['request_data'] ?? null) ? $case['request_data'] : [];
$requestStatus = service_status_label((string) ($case['request_status'] ?? ''));
$formatWhen = static function (?string $value): string {
    if ($value === null || $value === '') {
        return 'Not scheduled';
    }
    $timestamp = strtotime($value);
    return $timestamp === false ? $value : date('F j, Y · g:i A', $timestamp);
};

$pageTitle = 'Counseling case';
$activeNav = 'counselor-sessions';
$breadcrumbItems = [
    ['label' => 'Counseling sessions', 'url' => 'index.php'],
    ['label' => $case['member_name'], 'url' => 'view.php?id=' . $caseId],
];

require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header">
    <div>
        <p class="cmmp-kicker">Counseling case</p>
        <h1><?= e($case['member_name']) ?></h1>
        <p><?= e($case['request_number']) ?> · <?= e($case['service_name']) ?> · <?= e($requestStatus) ?></p>
    </div>
</div>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="cmmp-card h-100">
            <h2 class="h5">Confirmed booking</h2>
            <dl class="row mb-0">
                <dt class="col-sm-5">Status</dt>
                <dd class="col-sm-7"><span class="badge text-bg-primary"><?= e($requestStatus) ?></span></dd>
                <dt class="col-sm-5">Date and time</dt>
                <dd class="col-sm-7"><?= e($formatWhen($case['preferred_start_datetime'] ?? null)) ?></dd>
                <dt class="col-sm-5">Ends</dt>
                <dd class="col-sm-7"><?= e($formatWhen($case['preferred_end_datetime'] ?? null)) ?></dd>
                <dt class="col-sm-5">Facility</dt>
                <dd class="col-sm-7"><?= e($case['preferred_facility_name'] ?: 'No preference') ?></dd>
                <?php if (!empty($details['partner_1_name'])): ?>
                    <dt class="col-sm-5">Partner 1</dt>
                    <dd class="col-sm-7"><?= e($details['partner_1_name']) ?></dd>
                <?php endif; ?>
                <?php if (!empty($details['partner_2_name'])): ?>
                    <dt class="col-sm-5">Partner 2</dt>
                    <dd class="col-sm-7"><?= e($details['partner_2_name']) ?></dd>
                <?php endif; ?>
                <?php if (!empty($details['contact_number'])): ?>
                    <dt class="col-sm-5">Contact</dt>
                    <dd class="col-sm-7"><?= e($details['contact_number']) ?></dd>
                <?php endif; ?>
                <?php if (!empty($details['expected_wedding_date'])): ?>
                    <dt class="col-sm-5">Wedding date</dt>
                    <dd class="col-sm-7"><?= e($details['expected_wedding_date']) ?></dd>
                <?php endif; ?>
            </dl>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="cmmp-card h-100">
            <h2 class="h5">Sessions</h2>
            <?php foreach ($case['sessions'] as $session): ?>
                <?php
                $sessionStatus = strtoupper((string) ($session['appointment_status'] ?? $session['status']));
                $sessionLabel = $sessionStatus === 'COMPLETED' || $session['status'] === 'COMPLETED' ? 'Completed' : 'Confirmed';
                ?>
                <div class="border-bottom py-3">
                    <strong>Session <?= (int) $session['session_number'] ?></strong> · <?= e($sessionLabel) ?>
                    <br>
                    <span class="text-muted"><?= e($formatWhen($session['start_datetime'] ?? null)) ?><?= $session['facility_name'] ? ' · ' . e($session['facility_name']) : '' ?></span>
                    <?php if ($session['status'] !== 'COMPLETED'): ?>
                        <form class="mt-2" method="post" action="complete.php">
                            <input type="hidden" name="session_id" value="<?= (int) $session['id'] ?>">
                            <?= csrf_field() ?>
                            <textarea class="form-control mb-2" name="private_notes" rows="2" placeholder="Private counseling notes" required></textarea>
                            <button class="btn btn-sm btn-primary" type="submit">Record completion</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if ($case['sessions'] === []): ?>
                <p class="text-muted mb-0">The confirmed booking is <?= e($formatWhen($case['preferred_start_datetime'] ?? null)) ?>. The first session has not been placed on your case yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
