<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Personnel.php';
require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);

$pdo = database();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    header('Location: index.php');
    exit;
}

$errors = [];
$roles = $pdo->query("SELECT id, code, name FROM roles ORDER BY code")->fetchAll();

$user = $pdo->prepare('SELECT id, username, email, role, status FROM users WHERE id = :id LIMIT 1');
$user->execute(['id' => $id]);
$user = $user->fetch();
if (!$user) {
    header('Location: index.php');
    exit;
}

$currentRoles = $pdo->prepare('SELECT role_id FROM user_roles WHERE user_id = :user_id');
$currentRoles->execute(['user_id' => $id]);
$currentRoleIds = array_map('intval', array_column($currentRoles->fetchAll(), 'role_id'));

$fields = [
    'username' => $user['username'],
    'email' => $user['email'],
    'role' => $user['role'] ?? '',
    'status' => $user['status'],
    'roles' => $currentRoleIds,
    'password' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();

    $fields['username'] = trim((string) ($_POST['username'] ?? $fields['username']));
    $fields['email'] = trim((string) ($_POST['email'] ?? $fields['email']));
    $fields['status'] = in_array((string) ($_POST['status'] ?? 'ACTIVE'), ['ACTIVE', 'INACTIVE', 'LOCKED', 'PENDING'], true)
        ? (string) $_POST['status']
        : 'PENDING';
    $fields['roles'] = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['roles'] ?? [])), static fn (int $roleId): bool => $roleId > 0)));
    $fields['password'] = (string) ($_POST['password'] ?? '');

    if ($fields['username'] === '') {
        $errors[] = 'Username is required.';
    }

    if (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    $duplicateUsername = $pdo->prepare('SELECT id FROM users WHERE username = :username AND id != :id LIMIT 1');
    $duplicateUsername->execute(['username' => $fields['username'], 'id' => $id]);
    if ($duplicateUsername->fetchColumn()) {
        $errors[] = 'That username is already in use.';
    }

    $duplicateEmail = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1');
    $duplicateEmail->execute(['email' => $fields['email'], 'id' => $id]);
    if ($duplicateEmail->fetchColumn()) {
        $errors[] = 'That email address is already in use.';
    }

    if ($fields['roles'] === []) {
        $errors[] = 'Assign at least one role.';
    } else {
        $roleCheck = $pdo->prepare(
            'SELECT id FROM roles WHERE id IN (' . implode(',', array_fill(0, count($fields['roles']), '?')) . ')'
        );
        $roleCheck->execute($fields['roles']);
        $validRoleIds = array_map('intval', array_column($roleCheck->fetchAll(), 'id'));
        if (count($validRoleIds) !== count($fields['roles'])) {
            $errors[] = 'One or more selected roles are invalid.';
        } elseif (Personnel::roleIdsMixStaffAndClergy($fields['roles'])) {
            $errors[] = 'Pastors and counselors cannot also be recorded as staff.';
        }
    }

    if ($fields['password'] !== '' && strlen($fields['password']) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($errors === []) {
        try {
            $pdo->beginTransaction();

            $primaryRole = $pdo->prepare('SELECT code FROM roles WHERE id = :id LIMIT 1');
            $primaryRole->execute(['id' => (int) $fields['roles'][0]]);
            $primaryRoleCode = (string) $primaryRole->fetchColumn();

            $update = $pdo->prepare(
                'UPDATE users SET username = :username, email = :email, role = :role, status = :status' . ($fields['password'] !== '' ? ', password_hash = :password_hash' : '') . ' WHERE id = :id'
            );
            $params = [
                'username' => $fields['username'],
                'email' => $fields['email'],
                'role' => $primaryRoleCode,
                'status' => $fields['status'],
                'id' => $id,
            ];
            if ($fields['password'] !== '') {
                $params['password_hash'] = password_hash($fields['password'], PASSWORD_DEFAULT);
            }
            $update->execute($params);

            $clear = $pdo->prepare('DELETE FROM user_roles WHERE user_id = :user_id');
            $clear->execute(['user_id' => $id]);

            $roleInsert = $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)');
            foreach ($fields['roles'] as $roleId) {
                $roleInsert->execute(['user_id' => $id, 'role_id' => $roleId]);
            }

            $pdo->commit();
            header('Location: index.php?updated=1');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'User update failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Edit User';
$activeNav = 'users';
$breadcrumbItems = [['label' => 'Users & Roles', 'url' => 'index.php'], ['label' => 'Edit User', 'url' => 'edit.php?id=' . $id]];

require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header">
    <div>
        <h1>Edit User</h1>
        <p>Update account access and role assignments.</p>
    </div>
    <div>
        <a class="btn btn-outline-secondary" href="index.php">Back to users</a>
    </div>
</div>

<?php if ($errors !== []): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="post" class="cmmp-card">
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="username">Username</label>
            <input class="form-control" id="username" name="username" value="<?= htmlspecialchars($fields['username'], ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" id="email" name="email" type="email" value="<?= htmlspecialchars($fields['email'], ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="password">Password (leave blank to keep current)</label>
            <input class="form-control" id="password" name="password" type="password" minlength="8">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="ACTIVE" <?= $fields['status'] === 'ACTIVE' ? 'selected' : '' ?>>ACTIVE</option>
                <option value="INACTIVE" <?= $fields['status'] === 'INACTIVE' ? 'selected' : '' ?>>INACTIVE</option>
                <option value="LOCKED" <?= $fields['status'] === 'LOCKED' ? 'selected' : '' ?>>LOCKED</option>
                <option value="PENDING" <?= $fields['status'] === 'PENDING' ? 'selected' : '' ?>>PENDING</option>
            </select>
        </div>
    </div>

    <div class="mt-4">
        <label class="form-label">Roles</label>
        <div class="row g-2">
            <?php foreach ($roles as $role): ?>
                <div class="col-md-3">
                    <label class="d-flex align-items-center gap-2 border rounded p-2">
                        <input type="checkbox" name="roles[]" value="<?= (int) $role['id'] ?>" <?= in_array((int) $role['id'], $fields['roles'], true) ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($role['code'], ENT_QUOTES, 'UTF-8') ?></span>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary" type="submit">Save User</button>
        <a class="btn btn-outline-secondary" href="index.php">Cancel</a>
    </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
