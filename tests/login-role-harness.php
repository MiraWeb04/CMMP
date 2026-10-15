<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth.php';

$accounts = [
    ['identifier' => 'superadmin@example.test', 'expected' => '/admin/dashboard/'],
    ['identifier' => 'churchadmin@example.test', 'expected' => '/admin/dashboard/'],
    ['identifier' => 'pastor@example.test', 'expected' => '/pastor/dashboard/'],
    ['identifier' => 'counselor@example.test', 'expected' => '/counselor/dashboard/'],
    ['identifier' => 'ministryleader@example.test', 'expected' => '/ministry/dashboard/'],
    ['identifier' => 'staff@example.test', 'expected' => '/admin/dashboard/'],
    ['identifier' => 'member@example.test', 'expected' => '/member/dashboard/'],
];

$password = 'Password123!';

foreach ($accounts as $account) {
    $user = authenticate_user($account['identifier'], $password);
    if ($user === null || ($user['status'] ?? null) !== 'ACTIVE') {
        throw new RuntimeException('Login failed for ' . $account['identifier']);
    }

    $roles = $user['roles'];
    if ($roles === []) {
        throw new RuntimeException('No role configured for ' . $account['identifier']);
    }

    $redirect = role_redirect_path($roles);
    if ($redirect !== $account['expected']) {
        throw new RuntimeException('Redirect mismatch for ' . $account['identifier'] . ': ' . $redirect);
    }

    echo 'PASS ' . $account['identifier'] . ' => ' . $redirect . PHP_EOL;
}

echo 'All role login redirects passed.' . PHP_EOL;
