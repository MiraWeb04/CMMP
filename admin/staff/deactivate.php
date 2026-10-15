<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../models/Personnel.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

require_valid_csrf_token();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$person = $id ? Personnel::find((int) $id) : null;
if ($person && !Personnel::isClergy($person)) {
    Personnel::deactivate((int) $id);
}
header('Location: index.php?deactivated=1');
exit;
