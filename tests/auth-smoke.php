<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../config/constants.php';

$connection = database();
$suffix = bin2hex(random_bytes(5));
$username = 'step5_' . $suffix;
$email = $username . '@example.test';
$password = 'Step5-test-password';
$pendingUsername = 'pending_' . $suffix;
$pendingEmail = $pendingUsername . '@example.test';

$insert = $connection->prepare(
    'INSERT INTO users (username, email, password_hash, status)
     VALUES (:username, :email, :password_hash, :status)'
);

$activeId = null;
$pendingId = null;

try {
    $insert->execute([
        'username' => $username,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'status' => 'ACTIVE',
    ]);
    $activeId = (int) $connection->lastInsertId();

    $roleCodes = USER_ROLES;
    $role = $connection->prepare('SELECT id FROM roles WHERE code = :code LIMIT 1');
    $assign = $connection->prepare(
        'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
    );
    foreach ($roleCodes as $roleCode) {
        $role->execute(['code' => $roleCode]);
        $roleId = $role->fetchColumn();
        if ($roleId === false) {
            throw new RuntimeException('Missing role: ' . $roleCode);
        }
        $assign->execute(['user_id' => $activeId, 'role_id' => $roleId]);
    }

    $active = authenticate_user($email, $password);
    if (!$active || array_diff($roleCodes, $active['roles']) !== []) {
        throw new RuntimeException('Active authentication failed.');
    }

    login_user((int) $active['id'], $active['roles']);
    foreach ($roleCodes as $roleCode) {
        if (!has_role($roleCode)) {
            throw new RuntimeException('Role check failed: ' . $roleCode);
        }
    }

    foreach ($roleCodes as $roleCode) {
        if (role_redirect_path([$roleCode]) === '/auth/protected-test.php') {
            throw new RuntimeException('Role redirect missing: ' . $roleCode);
        }
    }
    logout_user();

    if (authenticate_user($email, 'wrong-password') !== null) {
        throw new RuntimeException('Wrong password was accepted.');
    }

    $insert->execute([
        'username' => $pendingUsername,
        'email' => $pendingEmail,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'status' => 'PENDING',
    ]);
    $pendingId = (int) $connection->lastInsertId();

    $pending = authenticate_user($pendingEmail, $password);
    if (($pending['status'] ?? null) !== 'PENDING') {
        throw new RuntimeException('Pending account status was not returned.');
    }

    echo "Authentication database smoke test passed.\n";
} finally {
    if ($activeId !== null || $pendingId !== null) {
        $delete = $connection->prepare(
            'DELETE FROM users WHERE id IN (:active_id, :pending_id)'
        );
        $delete->execute([
            'active_id' => $activeId ?: 0,
            'pending_id' => $pendingId ?: 0,
        ]);
    }
}
