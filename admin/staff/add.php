<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../models/Personnel.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);

$fields = [
    'user_id' => '',
    'member_id' => '',
    'employee_number' => '',
    'position_title' => 'Staff',
    'employment_status' => 'ACTIVE',
    'hired_at' => '',
    'personnel_type' => 'STAFF',
];
$errors = [];
$portalAccounts = Personnel::availablePortalAccounts();
$positionSelection = $fields['position_title'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();
    foreach ($fields as $field => $default) {
        $fields[$field] = trim((string) ($_POST[$field] ?? $default));
    }

    $fields['member_id'] = filter_var($fields['member_id'], FILTER_VALIDATE_INT) ?: '';
    $validStatuses = ['ACTIVE', 'INACTIVE', 'ON_LEAVE'];

    if ($fields['employee_number'] === '') {
        $errors[] = 'Employee number is required.';
    }
    if (!$fields['user_id']) {
        $errors[] = 'Select a portal account.';
    } elseif (!in_array((int) $fields['user_id'], array_map('intval', array_column($portalAccounts, 'user_id')), true)) {
        $errors[] = 'Select an available active portal account.';
    } else {
        foreach ($portalAccounts as $account) {
            if ((int) $account['user_id'] === (int) $fields['user_id']) {
                $fields['member_id'] = (int) ($account['member_id'] ?? 0);
                break;
            }
        }
    }
    if (!in_array($fields['employment_status'], $validStatuses, true)) {
        $errors[] = 'Select a valid employment status.';
    }
    foreach (['hired_at'] as $dateField) {
        if ($fields[$dateField] !== '') {
            $date = DateTimeImmutable::createFromFormat('Y-m-d', $fields[$dateField]);
            if (!$date || $date->format('Y-m-d') !== $fields[$dateField]) {
                $errors[] = 'Enter a valid date for ' . str_replace('_', ' ', $dateField) . '.';
            }
        }
    }

    $fields['personnel_type'] = 'STAFF';

    if ($errors === []) {
        try {
            Personnel::create($fields);
            header('Location: index.php?saved=1');
            exit;
        } catch (Throwable $exception) {
            $errors[] = $exception instanceof PDOException && (string) $exception->getCode() === '23000'
                ? 'That employee number or member is already assigned.'
                : 'Staff record could not be created.';
        }
    }
}

$pageTitle = 'Add staff';
$activeNav = 'staff';
$breadcrumbItems = [['label' => 'Staff', 'url' => 'index.php'], ['label' => 'Add staff', 'url' => 'add.php']];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header">
    <div><h1>Add staff</h1><p>Create a personnel record for the staff directory.</p></div>
    <a class="btn btn-outline-secondary" href="index.php">Back to staff</a>
</div>
<?php if ($errors !== []): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<form method="post" class="cmmp-card">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="user_id">Portal account</label>
            <select class="form-select" id="user_id" name="user_id" required>
                <option value="">Select an active portal account</option>
                <?php foreach ($portalAccounts as $account): ?>
                    <?php $memberName = trim(($account['first_name'] ?? '') . ' ' . ($account['middle_name'] ?? '') . ' ' . ($account['last_name'] ?? '')); ?>
                    <?php $accountLabel = $account['username'] . ' - ' . $account['email'] . ($memberName !== '' ? ' (' . $memberName . ')' : ' (No member record)'); ?>
                    <option value="<?= (int) $account['user_id'] ?>" <?= (string) $fields['user_id'] === (string) $account['user_id'] ? 'selected' : '' ?>><?= e($accountLabel) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Pastor and counselor accounts cannot be recorded as staff.</div>
        </div>
        <div class="col-md-6"><label class="form-label" for="employee_number">Employee number</label><input class="form-control" id="employee_number" name="employee_number" value="<?= e($fields['employee_number']) ?>" required></div>
        <div class="col-md-3"><label class="form-label" for="employment_status">Employment status</label><select class="form-select" id="employment_status" name="employment_status"><option value="ACTIVE" <?= $fields['employment_status'] === 'ACTIVE' ? 'selected' : '' ?>>Active</option><option value="INACTIVE" <?= $fields['employment_status'] === 'INACTIVE' ? 'selected' : '' ?>>Inactive</option><option value="ON_LEAVE" <?= $fields['employment_status'] === 'ON_LEAVE' ? 'selected' : '' ?>>On leave</option></select></div>
        <div class="col-md-6"><label class="form-label" for="hired_at">Hired date</label><input class="form-control" id="hired_at" name="hired_at" type="date" value="<?= e($fields['hired_at']) ?>"></div>
    </div>
    <div class="mt-4"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-user-plus me-2"></i>Create staff record</button> <a class="btn btn-outline-secondary" href="index.php">Cancel</a></div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>