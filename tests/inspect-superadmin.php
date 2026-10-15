<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$pdo = database();
$stmt = $pdo->prepare('SELECT id, username, email, password_hash, status FROM users WHERE email = :email LIMIT 1');
$stmt->execute(['email' => 'superadmin@example.test']);
$row = $stmt->fetch();

var_export($row);
echo PHP_EOL;
if ($row) {
    echo 'verify=' . (password_verify('Password123!', $row['password_hash']) ? 'yes' : 'no') . PHP_EOL;
}
