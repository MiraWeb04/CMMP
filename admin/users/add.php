<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Personnel.php';
require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);

$pdo = database();
$errors = [];
$success = '';

$fields = [
    'username' => '',
    'email' => '',
    'password' => '',
    'status' => 'ACTIVE',
    'roles' => [],
];

$roles = $pdo->query("SELECT id, code, name FROM roles ORDER BY code")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();

    $fields['username'] = trim((string) ($_POST['username'] ?? ''));
    $fields['email'] = trim((string) ($_POST['email'] ?? ''));
    $fields['password'] = (string) ($_POST['password'] ?? '');
    $fields['status'] = in_array((string) ($_POST['status'] ?? 'ACTIVE'), ['ACTIVE', 'INACTIVE', 'LOCKED', 'PENDING'], true)
        ? (string) $_POST['status']
        : 'PENDING';
    $fields['roles'] = array_values(array_filter(array_map('intval', (array) ($_POST['roles'] ?? []))));

    if ($fields['username'] === '') {
        $errors[] = 'Username is required.';
    }

    if (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if (strlen($fields['password']) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    $existingUsername = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
    $existingUsername->execute(['username' => $fields['username']]);
    if ($existingUsername->fetchColumn()) {
        $errors[] = 'That username is already in use.';
    }

    $existingEmail = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $existingEmail->execute(['email' => $fields['email']]);
    if ($existingEmail->fetchColumn()) {
        $errors[] = 'That email address is already in use.';
    }

    if ($fields['roles'] === []) {
        $errors[] = 'Assign at least one role.';
    } elseif (Personnel::roleIdsMixStaffAndClergy($fields['roles'])) {
        $errors[] = 'Pastors and counselors cannot also be recorded as staff.';
    }

    if ($errors === []) {
        try {
            $pdo->beginTransaction();

            $primaryRole = $pdo->prepare('SELECT code FROM roles WHERE id = :id LIMIT 1');
            $primaryRole->execute(['id' => (int) $fields['roles'][0]]);
            $primaryRoleCode = (string) $primaryRole->fetchColumn();

            $insertUser = $pdo->prepare(
                'INSERT INTO users (username, email, role, password_hash, status)
                 VALUES (:username, :email, :role, :password_hash, :status)'
            );
            $insertUser->execute([
                'username' => $fields['username'],
                'email' => $fields['email'],
                'role' => $primaryRoleCode,
                'password_hash' => password_hash($fields['password'], PASSWORD_DEFAULT),
                'status' => $fields['status'],
            ]);

            $userId = (int) $pdo->lastInsertId();
            $roleInsert = $pdo->prepare(
                'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
            );
            foreach ($fields['roles'] as $roleId) {
                $roleInsert->execute(['user_id' => $userId, 'role_id' => $roleId]);
            }

            $pdo->commit();
            header('Location: index.php?created=1');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'User creation failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Create User';
$activeNav = 'users';
$breadcrumbItems = [['label' => 'Users & Roles', 'url' => 'index.php'], ['label' => 'Create User', 'url' => 'add.php']];

require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header">
    <div>
        <h1>Create User</h1>
        <p>Create a login account and assign CMMP roles.</p>
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
            <label class="form-label" for="password">Password</label>
            <input class="form-control" id="password" name="password" type="password" minlength="8" required>
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
        <button class="btn btn-primary" type="submit">Create User</button>
        <a class="btn btn-outline-secondary" href="index.php">Cancel</a>
    </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
