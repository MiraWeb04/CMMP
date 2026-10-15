<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../config/database.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}
require_valid_csrf_token();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(422);
    exit('Invalid service request.');
}

$delete = database()->prepare('DELETE FROM service_requests WHERE id = :id');
$delete->execute(['id' => $id]);
header('Location: index.php?deleted=' . ($delete->rowCount() === 1 ? '1' : '0'));
exit;
