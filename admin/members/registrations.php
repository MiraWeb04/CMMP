<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN']);

$pending = database()->query(
    'SELECT u.id, u.username, u.email, u.created_at,
            COALESCE(CONCAT(m.first_name, " ", m.last_name), u.username) AS member_name,
            m.member_number
     FROM users u
     LEFT JOIN members m ON m.user_id = u.id
     WHERE u.status = "PENDING"
       AND EXISTS (
           SELECT 1
           FROM user_roles ur
           INNER JOIN roles r ON r.id = ur.role_id
           WHERE ur.user_id = u.id AND r.code = "MEMBER"
       )
     ORDER BY u.created_at DESC'
)->fetchAll();

$pageTitle = 'New members';
$activeNav = 'member-registrations';
$breadcrumbItems = [
    ['label' => 'Members', 'url' => 'index.php'],
    ['label' => 'New members', 'url' => 'registrations.php'],
];

require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header">
    <div>
        <p class="cmmp-kicker">Church administration</p>
        <h1>New members</h1>
        <p>Approve or reject people who registered and are waiting to sign in.</p>
    </div>
</div>

<?php if (isset($_GET['approved'])): ?>
    <div class="alert alert-success">The registration is approved. That member can sign in.</div>
<?php endif; ?>
<?php if (isset($_GET['declined'])): ?>
    <div class="alert alert-success">The registration was rejected.</div>
<?php endif; ?>
<?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-danger"><?= e((string) $_GET['error']) ?></div>
<?php endif; ?>

<div class="cmmp-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Email</th>
                    <th>Registered</th>
                    <th class="text-end">Decision</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending as $registration): ?>
                    <tr>
                        <td>
                            <strong><?= e($registration['member_name']) ?></strong>
                            <small class="d-block text-muted"><?= e($registration['username']) ?><?= $registration['member_number'] ? ' · ' . e($registration['member_number']) : '' ?></small>
                        </td>
                        <td><?= e($registration['email']) ?></td>
                        <td><?= e(date('F j, Y g:i A', strtotime((string) $registration['created_at']))) ?></td>
                        <td class="text-end">
                            <div class="cmmp-row-actions">
                                <form method="post" action="../users/approve.php">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="user_id" value="<?= (int) $registration['id'] ?>">
                                    <input type="hidden" name="decision" value="approve">
                                    <input type="hidden" name="return" value="registrations">
                                    <button class="btn btn-sm btn-primary" type="submit">Approve</button>
                                </form>
                                <form method="post" action="../users/approve.php" onsubmit="return confirm('Reject this registration?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="user_id" value="<?= (int) $registration['id'] ?>">
                                    <input type="hidden" name="decision" value="decline">
                                    <input type="hidden" name="return" value="registrations">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Reject</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($pending === []): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted py-5">No new members are waiting for approval.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
