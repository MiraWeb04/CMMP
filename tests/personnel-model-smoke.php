<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/Personnel.php';

try {
    $counselors = Personnel::list('COUNSELOR');
    $pastors = Personnel::list('PASTOR');
    echo 'PERSONNEL_SMOKE_OK ' . count($counselors) . ' counselors/' . count($pastors) . ' pastors' . PHP_EOL;
} catch (Throwable $e) {
    echo 'PERSONNEL_SMOKE_FAIL ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
