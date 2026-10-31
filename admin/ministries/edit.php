<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/ministry_form.php';
require_once __DIR__ . '/../../models/Ministry.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN']);

$id = (int) ($_GET['id'] ?? 0);
$ministry = Ministry::find($id);
if (!$ministry) {
    http_response_code(404);
    exit('Ministry not found.');
}
$people = Ministry::activePersonnel();
$members = Ministry::membersEligibleForAssignment($id);
$assignedIds = array_map('intval', array_column($ministry['members'], 'member_id'));
$memberChoices = array_values(array_filter(
    $members,
    static fn(array $member): bool => !in_array((int) $member['id'], $assignedIds, true)
));
$memberRows = [['member_id' => 0, 'role' => '']];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();
    try {
        $leaderStaffId = (int) ($_POST['leader_staff_id'] ?? 0);
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
        $postedMembers = ministry_posted_members($_POST, $memberChoices);
        $memberRows = $postedMembers['rows'];
        Ministry::update($id, [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'leader_staff_id' => $leaderStaffId,
        ]);
        Ministry::setStatus($id, (string) ($_POST['ministry_status'] ?? 'ACTIVE'));
        foreach ($postedMembers['selected'] as $memberId => $role) {
            Ministry::addMember($id, (int) $memberId, $role);
        }
        header('Location: view.php?id=' . $id);
        exit;
    } catch (Throwable $exception) {
        $error = $exception instanceof InvalidArgumentException
            ? $exception->getMessage()
            : 'Ministry could not be updated.';
    }
}

$pageTitle = 'Edit ministry';
$activeNav = 'ministries-admin';
require __DIR__ . '/../../includes/header.php';
?>
<div class="cmmp-page-header"><h1>Edit <?= e($ministry['name']) ?></h1></div>
<div class="cmmp-card">
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <input class="form-control mb-3" name="name" value="<?= e($ministry['name']) ?>" required>
        <textarea class="form-control mb-3" name="description"><?= e($ministry['description']) ?></textarea>
        <label class="form-label" for="leader_staff_id">Leader</label>
        <select class="form-select mb-2" id="leader_staff_id" name="leader_staff_id">
            <option value="0">Not assigned</option>
            <?php foreach ($people as $person): ?>
                <?php $isAvailable = (int) ($person['id'] ?? 0) > 0 && ($person['employment_status'] ?? '') === 'ACTIVE'; ?>
                <option value="<?= (int) ($person['id'] ?? 0) ?>" <?= (int) $ministry['leader_staff_id'] === (int) ($person['id'] ?? 0) ? 'selected' : '' ?> <?= $isAvailable ? '' : 'disabled' ?>><?= e(($person['username'] ?: $person['email']) . ' - ' . $person['email'] . ($isAvailable ? '' : ' (not an active staff member)')) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="form-text mb-3">Only church administration can assign or change the ministry leader.</div>
        <?php require __DIR__ . '/../../includes/ministry-member-picker.php'; ?>
        <select class="form-select mb-3" name="ministry_status"><option value="ACTIVE" <?= $ministry['ministry_status'] === 'ACTIVE' ? 'selected' : '' ?>>ACTIVE</option><option value="INACTIVE" <?= $ministry['ministry_status'] === 'INACTIVE' ? 'selected' : '' ?>>INACTIVE</option></select>
        <button class="btn btn-primary" type="submit">Save changes</button>
    </form>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
