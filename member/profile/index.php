<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/account_profile.php';

require_role('MEMBER');
header('Location: ' . account_profile_url());
exit;
