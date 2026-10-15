<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/facility.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed.'); }
require_valid_csrf_token();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$facility = $id ? Facility::find($id) : null;
if (!$facility) { http_response_code(404); exit('Facility not found.'); }
Facility::setStatus((int) $facility['id'], $facility['facility_status'] === 'AVAILABLE' ? 'UNAVAILABLE' : 'AVAILABLE');
header('Location: view.php?id=' . (int) $facility['id'] . '&status_changed=1');
exit;
