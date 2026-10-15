<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/member.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed.'); }
require_valid_csrf_token();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$member = $id ? Member::find($id) : null;
if (!$member) { http_response_code(404); exit('Member not found.'); }
$status = $member['membership_status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
Member::setStatus((int) $member['id'], $status);
header('Location: view.php?id=' . (int) $member['id'] . '&status_changed=1');
exit;
