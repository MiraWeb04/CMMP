<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../models/Ministry.php';

require_role('MEMBER');
$memberId = Ministry::memberIdForUser((int) current_user_id());
if (!$memberId) {
    http_response_code(404);
    exit('Your member profile has not been created yet.');
}

$ministries = Ministry::listForUser();
$memberMinistryIds = Ministry::membershipIdsForMember($memberId);
$pageTitle = 'Ministries';
$activeNav = 'ministries';
$breadcrumbItems = [['label' => 'Ministries', 'url' => 'index.php']];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header">
    <div>
        <h1>Ministries</h1>
        <p>View church ministries. Church administration assigns leaders and group members.</p>
    </div>
</div>
<div class="row g-3">
    <?php foreach ($ministries as $ministry): ?>
        <?php
        $ministryId = (int) $ministry['id'];
        $isMember = in_array($ministryId, $memberMinistryIds, true);
        ?>
        <div class="col-md-6">
            <div class="cmmp-card">
                <h2 class="h5"><?= e($ministry['name']) ?></h2>
                <p><?= e($ministry['description'] ?? '') ?></p>
                <p class="text-muted small">Leader: <?= e($ministry['leader_name']) ?> · <?= (int) $ministry['member_count'] ?> members</p>
                <?php if ($isMember): ?>
                    <span class="badge text-bg-success mt-2">You are a member of this group</span>
                <?php endif; ?>
                <?php if ($isMember && ($ministry['activities'] ?? []) !== []): ?>
                    <div class="mt-3">
                        <strong class="small d-block mb-2">Activities</strong>
                        <ul class="mb-0">
                            <?php foreach ($ministry['activities'] as $activity): ?>
                                <li>
                                    <span class="fw-semibold"><?= e($activity['name']) ?></span>
                                    <?php if (!empty($activity['start_datetime'])): ?><span class="text-muted small ms-2"><?= e($activity['start_datetime']) ?></span><?php endif; ?>
                                    <?php if (!empty($activity['location'])): ?><span class="text-muted small ms-2"><?= e($activity['location']) ?></span><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if ($ministries === []): ?>
        <div class="col-12"><div class="cmmp-card text-center text-muted py-5">No active ministries are available right now.</div></div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
