<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/fellowship.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this script from the command line.');
}

$updated = fellowship_backfill_all_active_members();
echo "Fellowship backfill complete. Generated profile details for {$updated} active member(s).\n";
