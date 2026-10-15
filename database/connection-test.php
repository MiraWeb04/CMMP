<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once __DIR__ . '/../config/database.php';

try {
    database()->query('SELECT 1');
    fwrite(STDOUT, "CMMP database connection successful.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, "CMMP database connection failed.\n");
    exit(1);
}
