<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/audit.php';

start_secure_session();

if (!is_logged_in()) {
    logout_user();
    header('Location: ' . APP_BASE_URL . '/member-portal.php');
    exit;
}

$roles = [];
if (isset($_SESSION['roles']) && is_array($_SESSION['roles'])) {
    $roles = array_values($_SESSION['roles']);
}

if ($roles === []) {
    $roles = current_user_roles();
}

$hasMemberRole = in_array('MEMBER', $roles, true);

audit_log('LOGOUT', 'AUTHENTICATION', current_user_id());
logout_user();

if ($hasMemberRole) {
    header('Location: ' . APP_BASE_URL . '/member-portal.php');
} else {
    header('Location: ' . APP_BASE_URL . '/auth/login.php?logged_out=1');
}

exit;
