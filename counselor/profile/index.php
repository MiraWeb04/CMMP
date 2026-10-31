<?php
declare(strict_types=1);
require_once __DIR__ . '/../../helpers/auth.php';
require_role('COUNSELOR');
header('Location: ' . APP_BASE_URL . '/account/profile.php');
exit;
