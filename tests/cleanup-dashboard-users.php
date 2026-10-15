<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$pdo = database();
$emails = [
    'superadmin@example.test',
    'churchadmin@example.test',
    'pastor@example.test',
    'counselor@example.test',
    'ministryleader@example.test',
    'staff@example.test',
    'member@example.test',
];

$placeholders = implode(',', array_fill(0, count($emails), '?'));

$stmt = $pdo->prepare('DELETE FROM user_roles WHERE user_id IN (SELECT id FROM users WHERE email IN (' . $placeholders . '))');
$stmt->execute($emails);

$stmt = $pdo->prepare('DELETE FROM users WHERE email IN (' . $placeholders . ')');
$stmt->execute($emails);

echo 'cleanup done' . PHP_EOL;
