<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/auth.php';
$navigation = require __DIR__ . '/../includes/navigation.php';

$basePath = parse_url(APP_BASE_URL, PHP_URL_PATH) ?: '/CMMP';
$workspaceRoot = realpath(__DIR__ . '/..');
$broken = [];

foreach ($navigation as $group) {
    foreach ($group['items'] as $item) {
        $url = (string) ($item['url'] ?? '');
        if ($url === '') {
            $broken[] = ['label' => $item['label'] ?? 'Unnamed item', 'reason' => 'Empty URL'];
            continue;
        }

        $parsed = parse_url($url);
        $path = (string) ($parsed['path'] ?? '');
        if ($path === '') {
            $broken[] = ['label' => $item['label'] ?? 'Unnamed item', 'reason' => 'No parseable path'];
            continue;
        }

        $relative = str_replace($basePath, '', $path);
        $relative = trim($relative, '/');
        if ($relative === '') {
            $broken[] = ['label' => $item['label'] ?? 'Unnamed item', 'reason' => 'Base path only'];
            continue;
        }

        $absolute = $workspaceRoot . '/' . $relative;
        $phpFile = $absolute . '/index.php';
        $phpFileLegacy = $workspaceRoot . '/' . $relative;

        $hasFileRoute = str_ends_with($relative, '.php') && file_exists($workspaceRoot . '/' . $relative);
        $hasFolderRoute = !$hasFileRoute && file_exists($phpFile);

        if (!$hasFileRoute && !$hasFolderRoute) {
            $broken[] = ['label' => $item['label'] ?? 'Unnamed item', 'url' => $url, 'reason' => 'No route file at ' . $absolute];
        }
    }
}

if ($broken !== []) {
    echo "Broken navigation links found:\n";
    foreach ($broken as $item) {
        echo '- ' . ($item['label'] ?? 'Unknown') . ' => ' . ($item['url'] ?? 'n/a') . ' :: ' . $item['reason'] . PHP_EOL;
    }
    exit(1);
}

echo 'All navigation links resolve to a real route file or folder index.php route.' . PHP_EOL;
