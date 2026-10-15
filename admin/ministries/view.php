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

$assignedIds = array_map('intval', array_column($ministry['members'], 'member_id'));
$members = Ministry::activeMembersWithMinistries($id);
$availableMembers = array_values(array_filter(
    $members,
    static fn(array $member): bool => !in_array((int) $member['id'], $assignedIds, true)
));

$memberRows = [['member_id' => 0, 'role' => '']];
$assignError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'add') {
            $postedMembers = ministry_posted_members($_POST, $availableMembers);
            $memberRows = $postedMembers['rows'];
            if ($postedMembers['selected'] === []) {
                throw new InvalidArgumentException('Select at least one church member.');
            }
            foreach ($postedMembers['selected'] as $memberId => $role) {
                Ministry::addMember($id, (int) $memberId, $role);
            }
        }
        if ($action === 'remove') {
            Ministry::removeMember($id, (int) ($_POST['member_id'] ?? 0));
        }
        header('Location: view.php?id=' . $id);
        exit;
    } catch (InvalidArgumentException $exception) {
        $assignError = $exception->getMessage();
    }
}

$pageTitle = $ministry['name'];
$activeNav = 'ministries-admin';
$breadcrumbItems = [
    ['label' => 'Ministries', 'url' => 'index.php'],
    ['label' => $ministry['name'], 'url' => 'view.php?id=' . $id],
];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header">
    <div>
        <h1><?= e($ministry['name']) ?></h1>
        <p><?= e($ministry['code']) ?> · Leader: <?= e($ministry['leader_name']) ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="edit.php?id=<?= (int) $id ?>">Assign leader</a>
</div>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="cmmp-card">
            <h2 class="h5">Ministry group members</h2>
            <p class="text-muted small">Only church administration can assign members to this ministry. Members cannot request to join.</p>
            <?php if ($ministry['members'] === []): ?>
                <p class="text-muted">No members have been assigned yet.</p>
            <?php endif; ?>
            <?php foreach ($ministry['members'] as $member): ?>
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span><?= e($member['member_name']) ?> · <?= e($member['membership_role'] ?? 'Member') ?></span>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="member_id" value="<?= (int) $member['member_id'] ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit">Remove</button>
                    </form>
                </div>
            <?php endforeach; ?>
            <?php if ($assignError): ?><div class="alert alert-danger mt-3"><?= e($assignError) ?></div><?php endif; ?>
            <form class="mt-3" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <?php $memberChoices = $availableMembers; require __DIR__ . '/../../includes/ministry-member-picker.php'; ?>
                <button class="btn btn-primary" type="submit" <?= $availableMembers === [] ? 'disabled' : '' ?>>Assign members</button>
            </form>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="cmmp-card">
            <h2 class="h5">Activities</h2>
            <?php if ($ministry['activities'] === []): ?><p class="text-muted mb-0">No activities.</p><?php endif; ?>
            <?php foreach ($ministry['activities'] as $activity): ?>
                <p><?= e($activity['name']) ?><br><small><?= e($activity['start_datetime'] ?? '') ?></small></p>
            <?php endforeach; ?>
        </div>
        <div class="cmmp-card mt-3">
            <h2 class="h5">Events</h2>
            <?php if ($ministry['events'] === []): ?><p class="text-muted mb-0">No events.</p><?php endif; ?>
            <?php foreach ($ministry['events'] as $event): ?>
                <p><?= e($event['title']) ?><br><small><?= e($event['start_datetime']) ?></small></p>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
