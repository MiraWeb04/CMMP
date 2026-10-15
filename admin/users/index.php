<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../config/database.php';
require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);
$canApproveMembers = has_role(['SUPER_ADMIN', 'CHURCH_ADMIN']);

$pdo = database();
$roles = $pdo->query('SELECT id, code FROM roles ORDER BY code')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    require_valid_csrf_token();
    $deleteId = filter_input(INPUT_POST, 'delete_user_id', FILTER_VALIDATE_INT);
    if ($deleteId) {
        $delete = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $delete->execute(['id' => $deleteId]);
        header('Location: index.php?deleted=1');
        exit;
    }
}

$users = $pdo->query(
        'SELECT u.id, u.username, u.email, u.role, u.status, u.last_login_at,
            COALESCE(u.role, GROUP_CONCAT(r.code ORDER BY r.code SEPARATOR ", ")) AS roles,
            GROUP_CONCAT(ur.role_id ORDER BY ur.role_id SEPARATOR ",") AS role_ids
     FROM users u
     LEFT JOIN user_roles ur ON ur.user_id = u.id
     LEFT JOIN roles r ON r.id = ur.role_id
     GROUP BY u.id
     ORDER BY u.username ASC'
)->fetchAll();

$pageTitle = 'Users & Roles';
$activeNav = 'users';
$breadcrumbItems = [['label' => 'Users & Roles', 'url' => 'index.php']];

require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header">
    <div>
        <h1>Users &amp; Roles</h1>
        <p>Manage access and user assignments.</p>
    </div>
    <div>
        <a class="btn btn-primary" href="add.php"><i class="fa-solid fa-user-plus me-2"></i>Create User</a>
    </div>
</div>

<?php if (isset($_GET['created'])): ?>
<div class="alert alert-success">User created.</div>
<?php endif; ?>
<?php if (isset($_GET['updated'])): ?>
<div class="alert alert-success">User updated.</div>
<?php endif; ?>
<?php if (isset($_GET['deleted'])): ?>
<div class="alert alert-success">User deleted.</div>
<?php endif; ?>
<?php if (isset($_GET['approved'])): ?>
<div class="alert alert-success">The registration is approved. That member can sign in.</div>
<?php endif; ?>
<?php if (isset($_GET['declined'])): ?>
<div class="alert alert-success">The registration was declined.</div>
<?php endif; ?>
<?php if (!empty($_GET['error'])): ?>
<div class="alert alert-danger"><?= e((string) $_GET['error']) ?></div>
<?php endif; ?>
<?php
$pendingUsers = array_values(array_filter($users, static fn (array $user): bool => $user['status'] === 'PENDING'));
?>
<?php if ($canApproveMembers && $pendingUsers !== []): ?>
<div class="alert alert-warning d-flex justify-content-between align-items-center gap-3">
    <span><?= count($pendingUsers) ?> new member <?= count($pendingUsers) === 1 ? 'registration is' : 'registrations are' ?> waiting for approval.</span>
    <a class="btn btn-sm btn-primary" href="../members/registrations.php">Review registrations</a>
</div>
<?php endif; ?>

<div class="cmmp-card">
    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Roles</th>
                    <th>Last Login</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="badge text-bg-<?= $user['status'] === 'PENDING' ? 'warning' : ($user['status'] === 'ACTIVE' ? 'success' : 'secondary') ?>"><?= htmlspecialchars($user['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td><?= htmlspecialchars((string) ($user['roles'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($user['last_login_at'] ?? 'Never'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="text-end">
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#editUserModal<?= (int) $user['id'] ?>">Edit</button>
                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this user?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="delete_user_id" value="<?= (int) $user['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php foreach ($users as $user): ?>
<?php $userRoleIds = $user['role_ids'] === null || $user['role_ids'] === '' ? [] : array_map('intval', explode(',', $user['role_ids'])); ?>
<div class="modal fade" id="editUserModal<?= (int) $user['id'] ?>" tabindex="-1" aria-labelledby="editUserModalLabel<?= (int) $user['id'] ?>" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="editUserModalLabel<?= (int) $user['id'] ?>">Edit user</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="edit.php?id=<?= (int) $user['id'] ?>">
                <div class="modal-body">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="modal_username_<?= (int) $user['id'] ?>">Username</label>
                            <input class="form-control" id="modal_username_<?= (int) $user['id'] ?>" name="username" value="<?= e($user['username']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="modal_email_<?= (int) $user['id'] ?>">Email</label>
                            <input class="form-control" id="modal_email_<?= (int) $user['id'] ?>" name="email" type="email" value="<?= e($user['email']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="modal_password_<?= (int) $user['id'] ?>">New password</label>
                            <input class="form-control" id="modal_password_<?= (int) $user['id'] ?>" name="password" type="password" minlength="8">
                            <div class="form-text">Leave blank to keep the current password.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="modal_status_<?= (int) $user['id'] ?>">Status</label>
                            <select class="form-select" id="modal_status_<?= (int) $user['id'] ?>" name="status">
                                <?php foreach (['ACTIVE', 'INACTIVE', 'LOCKED', 'PENDING'] as $status): ?>
                                    <option value="<?= $status ?>" <?= $user['status'] === $status ? 'selected' : '' ?>><?= $status ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="form-label">Roles</label>
                        <div class="row g-2">
                            <?php foreach ($roles as $role): ?>
                                <div class="col-sm-6 col-lg-4">
                                    <label class="d-flex align-items-center gap-2 border rounded p-2">
                                        <input type="checkbox" name="roles[]" value="<?= (int) $role['id'] ?>" <?= in_array((int) $role['id'], $userRoleIds, true) ? 'checked' : '' ?>>
                                        <span><?= e($role['code']) ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-2"></i>Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
