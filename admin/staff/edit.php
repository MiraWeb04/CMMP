<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../models/Personnel.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: index.php');
    exit;
}

$person = Personnel::find($id);
if (!$person) {
    http_response_code(404);
    exit('Staff record not found.');
}
if (Personnel::isClergy($person)) {
    header('Location: ' . Personnel::clergyDirectoryPath($person));
    exit;
}

$fields = [
    'user_id' => (string) ($person['user_id'] ?? ''),
    'member_id' => (string) ($person['member_id'] ?? ''),
    'employee_number' => (string) $person['employee_number'],
    'position_title' => (string) $person['position_title'],
    'other_position' => '',
    'employment_status' => (string) $person['employment_status'],
    'hired_at' => (string) ($person['hired_at'] ?? ''),
    'personnel_type' => (string) $person['personnel_type'],
    'ordination_date' => (string) ($person['ordination_date'] ?? ''),
    'specialization' => (string) ($person['specialization'] ?? ''),
    'credentials' => (string) ($person['credentials'] ?? ''),
];
$positionSelection = in_array($fields['position_title'], STAFF_POSITIONS, true) ? $fields['position_title'] : 'Other';
if ($positionSelection === 'Other') {
    $fields['other_position'] = $fields['position_title'];
}
$errors = [];
$portalAccounts = Personnel::availablePortalAccounts((int) $id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();
    foreach ($fields as $field => $default) {
        $fields[$field] = trim((string) ($_POST[$field] ?? $default));
    }
    $positionSelection = $fields['position_title'];
    if ($positionSelection === 'Other') {
        $fields['position_title'] = $fields['other_position'];
    }
    $fields['user_id'] = filter_var($fields['user_id'], FILTER_VALIDATE_INT) ?: '';
    $fields['member_id'] = '';
    $validStatuses = ['ACTIVE', 'INACTIVE', 'ON_LEAVE'];
    $fields['personnel_type'] = 'STAFF';

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
    if ($fields['employee_number'] === '') {
        $errors[] = 'Employee number is required.';
    }
    if ($fields['position_title'] === '') {
        $errors[] = 'Position is required.';
    }
    if ($positionSelection === 'Other' && $fields['other_position'] === '') {
        $errors[] = 'Specify the position.';
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

    if ($errors === []) {
        try {
            Personnel::update((int) $id, $fields);
            header('Location: index.php?updated=1');
            exit;
        } catch (Throwable $exception) {
            $errors[] = $exception instanceof PDOException && (string) $exception->getCode() === '23000'
                ? 'That employee number or portal account is already assigned.'
                : 'Staff record could not be updated.';
        }
    }
}

$pageTitle = 'Edit staff';
$activeNav = 'staff';
$breadcrumbItems = [['label' => 'Staff', 'url' => 'index.php'], ['label' => 'Edit staff', 'url' => 'edit.php?id=' . $id]];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header"><div><h1>Edit staff</h1><p>Update this personnel record.</p></div><a class="btn btn-outline-secondary" href="index.php">Back to staff</a></div>
<?php if ($errors !== []): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" class="cmmp-card">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label" for="user_id">Portal account</label><select class="form-select" id="user_id" name="user_id" required><option value="">Select an active portal account</option><?php foreach ($portalAccounts as $account): ?><?php $memberName = trim(($account['first_name'] ?? '') . ' ' . ($account['middle_name'] ?? '') . ' ' . ($account['last_name'] ?? '')); ?><option value="<?= (int) $account['user_id'] ?>" <?= (string) $fields['user_id'] === (string) $account['user_id'] ? 'selected' : '' ?>><?= e($account['username'] . ' - ' . $account['email'] . ($memberName !== '' ? ' (' . $memberName . ')' : ' (No member record)')) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label" for="employee_number">Employee number</label><input class="form-control" id="employee_number" name="employee_number" value="<?= e($fields['employee_number']) ?>" required></div>
        <div class="col-md-6"><label class="form-label" for="position_title">Position</label><select class="form-select" id="position_title" name="position_title" required><option value="">Select position</option><?php foreach (STAFF_POSITIONS as $position): ?><option value="<?= e($position) ?>" <?= $positionSelection === $position ? 'selected' : '' ?>><?= e($position) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6" id="other-position-group" <?= $positionSelection === 'Other' ? '' : 'hidden' ?>><label class="form-label" for="other_position">Specify position</label><input class="form-control" id="other_position" name="other_position" value="<?= e($fields['other_position']) ?>"></div>
        <div class="col-md-3"><label class="form-label" for="employment_status">Employment status</label><select class="form-select" id="employment_status" name="employment_status"><option value="ACTIVE" <?= $fields['employment_status'] === 'ACTIVE' ? 'selected' : '' ?>>Active</option><option value="INACTIVE" <?= $fields['employment_status'] === 'INACTIVE' ? 'selected' : '' ?>>Inactive</option><option value="ON_LEAVE" <?= $fields['employment_status'] === 'ON_LEAVE' ? 'selected' : '' ?>>On leave</option></select></div>
        <div class="col-md-6"><label class="form-label" for="hired_at">Hired date</label><input class="form-control" id="hired_at" name="hired_at" type="date" value="<?= e($fields['hired_at']) ?>"></div>
    </div>
    <div class="mt-4"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-2"></i>Save changes</button> <a class="btn btn-outline-secondary" href="index.php">Cancel</a></div>
</form>
<script>
(() => {
    const position = document.getElementById('position_title');
    const otherGroup = document.getElementById('other-position-group');
    const otherInput = document.getElementById('other_position');
    const syncPosition = () => { const isOther = position.value === 'Other'; otherGroup.hidden = !isOther; otherInput.required = isOther; };
    position.addEventListener('change', syncPosition);
    syncPosition();
})();
</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
