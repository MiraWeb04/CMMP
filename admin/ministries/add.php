<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/ministry_form.php';
require_once __DIR__ . '/../../models/Ministry.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN']);

$people = Ministry::activePersonnel();
$members = Ministry::membersEligibleForAssignment();
$error = null;
$form = [
    'name' => '',
    'code' => '',
    'description' => '',
    'leader_staff_id' => 0,
];
$memberRows = [['member_id' => 0, 'role' => '']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();
    $form = [
        'name' => trim((string) ($_POST['name'] ?? '')),
        'code' => trim((string) ($_POST['code'] ?? '')),
        'description' => trim((string) ($_POST['description'] ?? '')),
        'leader_staff_id' => (int) ($_POST['leader_staff_id'] ?? 0),
    ];
    try {
        $postedMembers = ministry_posted_members($_POST, $members);
        $memberRows = $postedMembers['rows'];
        $leaderStaffId = $form['leader_staff_id'];
        if ($leaderStaffId > 0) {
            $validLeader = false;
            foreach ($people as $person) {
                if ((int) ($person['id'] ?? 0) === $leaderStaffId && ($person['employment_status'] ?? '') === 'ACTIVE') {
                    $validLeader = true;
                    break;
                }
            }
            if (!$validLeader) {
                throw new InvalidArgumentException('Select an active staff account as the ministry leader.');
            }
        }
        $id = Ministry::create([
            'name' => $form['name'],
            'code' => $form['code'],
            'description' => $form['description'],
            'leader_staff_id' => $leaderStaffId,
        ]);
        foreach ($postedMembers['selected'] as $memberId => $role) {
            Ministry::addMember($id, (int) $memberId, $role);
        }
        header('Location: view.php?id=' . $id);
        exit;
    } catch (Throwable $exception) {
        $error = $exception instanceof InvalidArgumentException
            ? $exception->getMessage()
            : 'Ministry could not be created.';
    }
}

$pageTitle = 'Create ministry';
$activeNav = 'ministries-admin';
$memberChoices = $members;
require __DIR__ . '/../../includes/header.php';
?>
<div class="cmmp-page-header"><h1>Create ministry</h1></div>
<div class="cmmp-card">
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <input class="form-control mb-3" name="name" placeholder="Ministry name" value="<?= e($form['name']) ?>" required>
        <input class="form-control mb-3" name="code" placeholder="MINISTRY-CODE" value="<?= e($form['code']) ?>" required>
        <textarea class="form-control mb-3" name="description" placeholder="Description"><?= e($form['description']) ?></textarea>
        <label class="form-label" for="leader_staff_id">Leader</label>
        <select class="form-select mb-2" id="leader_staff_id" name="leader_staff_id">
            <option value="0">Not assigned</option>
            <?php foreach ($people as $person): ?>
                <?php $isAvailable = (int) ($person['id'] ?? 0) > 0 && ($person['employment_status'] ?? '') === 'ACTIVE'; ?>
                <option value="<?= (int) ($person['id'] ?? 0) ?>" <?= $form['leader_staff_id'] === (int) ($person['id'] ?? 0) ? 'selected' : '' ?> <?= $isAvailable ? '' : 'disabled' ?>><?= e(($person['username'] ?: $person['email']) . ' - ' . $person['email'] . ($isAvailable ? '' : ' (not an active staff member)')) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="form-text mb-3">Only church administration can assign a ministry leader.</div>
        <?php require __DIR__ . '/../../includes/ministry-member-picker.php'; ?>
        <button class="btn btn-primary" type="submit">Create ministry</button>
    </form>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
