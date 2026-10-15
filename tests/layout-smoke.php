<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
$navigationGroups = require __DIR__ . '/../includes/navigation.php';

$expectedRoles = [
    'SUPER_ADMIN',
    'CHURCH_ADMIN',
    'PASTOR',
    'COUNSELOR',
    'MINISTRY_LEADER',
    'STAFF',
    'MEMBER',
];

foreach ($expectedRoles as $role) {
    $visibleItems = 0;
    foreach ($navigationGroups as $group) {
        foreach ($group['items'] as $item) {
            if (in_array($role, $item['roles'], true)) {
                $visibleItems++;
            }
        }
    }

    if ($visibleItems === 0) {
        throw new RuntimeException('No navigation items configured for role: ' . $role);
    }
}

echo "Layout role navigation smoke test passed.\n";
