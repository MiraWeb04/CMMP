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
    'position_title' => 'Pastor / Minister',
    'employment_status' => 'ACTIVE',
    'hired_at' => '',
    'personnel_type' => 'PASTOR',
    'ordination_date' => '',
];
$errors = [];
$accounts = Personnel::availablePastorAccounts();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();
    foreach ($fields as $field => $default) {
        $fields[$field] = trim((string) ($_POST[$field] ?? $default));
    }
    $fields['user_id'] = filter_var($fields['user_id'], FILTER_VALIDATE_INT) ?: '';
    $validAccountIds = array_map('intval', array_column($accounts, 'user_id'));
    if (!$fields['user_id'] || !in_array((int) $fields['user_id'], $validAccountIds, true)) {
        $errors[] = 'Select an available portal account.';
    } else {
        foreach ($accounts as $account) {
            if ((int) $account['user_id'] === (int) $fields['user_id']) {
                $fields['member_id'] = (int) ($account['member_id'] ?? 0);
                break;
            }
        }
    }
    if ($fields['employee_number'] === '') {
        $errors[] = 'Pastor number is required.';
    }
    foreach (['hired_at', 'ordination_date'] as $dateField) {
        if ($fields[$dateField] !== '') {
            $date = DateTimeImmutable::createFromFormat('Y-m-d', $fields[$dateField]);
            if (!$date || $date->format('Y-m-d') !== $fields[$dateField]) {
                $errors[] = 'Enter a valid date for ' . str_replace('_', ' ', $dateField) . '.';
            }
        }
    }

    if ($errors === []) {
        try {
            Personnel::create($fields);
            header('Location: index.php?saved=1');
            exit;
        } catch (Throwable $exception) {
            $errors[] = $exception instanceof PDOException && (string) $exception->getCode() === '23000'
                ? 'That pastor number or account is already assigned.'
                : 'Pastor record could not be created.';
        }
    }
}

$pageTitle = 'Add pastor';
$activeNav = 'pastors';
$breadcrumbItems = [['label' => 'Pastors', 'url' => 'index.php'], ['label' => 'Add pastor', 'url' => 'add.php']];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header"><div><h1>Add pastor</h1><p>Create a pastor profile connected to a portal account.</p></div><a class="btn btn-outline-secondary" href="index.php">Back to pastors</a></div>
<?php if ($errors !== []): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" class="cmmp-card">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label" for="user_id">Pastor portal account</label><select class="form-select" id="user_id" name="user_id" required><option value="">Select a portal account</option><?php foreach ($accounts as $account): ?><?php $name = trim(($account['first_name'] ?? '') . ' ' . ($account['middle_name'] ?? '') . ' ' . ($account['last_name'] ?? '')); ?><option value="<?= (int) $account['user_id'] ?>" <?= (string) $fields['user_id'] === (string) $account['user_id'] ? 'selected' : '' ?>><?= e(($account['username'] ?: $account['email']) . ' - ' . $account['email'] . ($name !== '' ? ' (' . $name . ')' : '')) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label" for="employee_number">Pastor number</label><input class="form-control" id="employee_number" name="employee_number" value="<?= e($fields['employee_number']) ?>" required></div>
        <div class="col-md-6"><label class="form-label" for="position_title">Position</label><input class="form-control" id="position_title" name="position_title" value="<?= e($fields['position_title']) ?>" required></div>
        <div class="col-md-6"><label class="form-label" for="ordination_date">Ordination date</label><input class="form-control" id="ordination_date" name="ordination_date" type="date" value="<?= e($fields['ordination_date']) ?>"></div>
    </div>
    <div class="mt-4"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-user-plus me-2"></i>Create pastor</button> <a class="btn btn-outline-secondary" href="index.php">Cancel</a></div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>