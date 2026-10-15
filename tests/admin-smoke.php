<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../config/constants.php';

$connection = database();
$suffix = bin2hex(random_bytes(5));
$username = 'admin_smoke_' . $suffix;
$email = $username . '@example.test';
$password = 'AdminSmokeTestPassword123!';

$userId = null;

try {
    $insert = $connection->prepare(
        'INSERT INTO users (username, email, password_hash, status)
         VALUES (:username, :email, :password_hash, :status)'
    );

    $insert->execute([
        'username' => $username,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'status' => 'ACTIVE',
    ]);

    $userId = (int) $connection->lastInsertId();

    $role = $connection->prepare('SELECT id FROM roles WHERE code = :code LIMIT 1');
    $role->execute(['code' => 'SUPER_ADMIN']);
    $roleId = $role->fetchColumn();
    if ($roleId === false) {
        throw new RuntimeException('Missing SUPER_ADMIN role.');
    }

    $assign = $connection->prepare(
        'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
    );
    $assign->execute([
        'user_id' => $userId,
        'role_id' => (int) $roleId,
    ]);

    $user = authenticate_user($email, $password);
    if ($user === null || $user['status'] !== 'ACTIVE') {
        throw new RuntimeException('Admin authentication failed.');
    }
    if (!in_array('SUPER_ADMIN', $user['roles'], true)) {
        throw new RuntimeException('SUPER_ADMIN role was not returned for the user.');
    }

    login_user((int) $user['id'], $user['roles']);
    if (!has_role('SUPER_ADMIN')) {
        throw new RuntimeException('Role gate failed for SUPER_ADMIN.');
    }

    if (role_redirect_path(['SUPER_ADMIN']) !== '/admin/dashboard/') {
        throw new RuntimeException('SUPER_ADMIN redirect path is wrong.');
    }

    logout_user();
    echo "Admin smoke test passed.\n";
} finally {
    if ($userId !== null) {
        $cleanupRoles = $connection->prepare('DELETE FROM user_roles WHERE user_id = :user_id');
        $cleanupRoles->execute(['user_id' => $userId]);

        $cleanupUser = $connection->prepare('DELETE FROM users WHERE id = :id');
        $cleanupUser->execute(['id' => $userId]);
    }
}
