<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$pdo = database();
$accounts = [
    ['role' => 'SUPER_ADMIN',    'username' => 'superadmin',     'email' => 'superadmin@example.test'],
    ['role' => 'CHURCH_ADMIN',  'username' => 'churchadmin',    'email' => 'churchadmin@example.test'],
    ['role' => 'PASTOR',         'username' => 'pastor',         'email' => 'pastor@example.test'],
    ['role' => 'COUNSELOR',     'username' => 'counselor',     'email' => 'counselor@example.test'],
    ['role' => 'MINISTRY_LEADER','username' => 'ministryleader','email' => 'ministryleader@example.test'],
    ['role' => 'STAFF',         'username' => 'staff',          'email' => 'staff@example.test'],
    ['role' => 'MEMBER',        'username' => 'member',         'email' => 'member@example.test'],
];

$password = 'Password123!';
$hash = password_hash($password, PASSWORD_DEFAULT);

foreach ($accounts as $account) {
    $email = $account['email'];
    $username = $account['username'];

    $find = $pdo->prepare('SELECT id FROM users WHERE email = :email OR username = :username LIMIT 1');
    $find->execute(['email' => $email, 'username' => $username]);
    $existing = $find->fetch();

    if ($existing) {
        $userId = (int) $existing['id'];
        $upsert = $pdo->prepare(
            'UPDATE users SET username = :username, email = :email, password_hash = :password_hash, status = :status WHERE id = :id'
        );
        $upsert->execute([
            'username' => $username,
            'email' => $email,
            'password_hash' => $hash,
            'status' => 'ACTIVE',
            'id' => $userId,
        ]);
    } else {
        $insert = $pdo->prepare(
            'INSERT INTO users (username, email, password_hash, status)
             VALUES (:username, :email, :password_hash, :status)'
        );
        $insert->execute([
            'username' => $username,
            'email' => $email,
            'password_hash' => $hash,
            'status' => 'ACTIVE',
        ]);
        $userId = (int) $pdo->lastInsertId();
    }

    $role = $pdo->prepare('SELECT id FROM roles WHERE code = :code LIMIT 1');
    $role->execute(['code' => $account['role']]);
    $roleId = $role->fetchColumn();
    if ($roleId === false) {
        echo 'Missing role: ' . $account['role'] . PHP_EOL;
        continue;
    }

    $clear = $pdo->prepare('DELETE FROM user_roles WHERE user_id = :user_id');
    $clear->execute(['user_id' => $userId]);

    $assign = $pdo->prepare(
        'INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
    );
    $assign->execute([
        'user_id' => $userId,
        'role_id' => (int) $roleId,
    ]);

    echo $account['role'] . ' => username=' . $username . ' email=' . $email . ' password=' . $password . PHP_EOL;
}
