<?php
declare(strict_types=1);
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../models/Personnel.php';
require_role('STAFF');
$account = authenticated_user();
$person = Personnel::findForUser((int) current_user_id());
$pageTitle = 'My Profile'; $activeNav = 'staff-profile'; $breadcrumbItems = [['label' => 'My Profile', 'url' => 'profile.php']];
require __DIR__ . '/../../includes/header.php'; require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header"><div><h1>My Profile</h1><p>Your staff account and personnel information.</p></div></div><div class="cmmp-card"><dl class="row mb-0"><dt class="col-sm-4">Username</dt><dd class="col-sm-8"><?= e($account['username'] ?? '') ?></dd><dt class="col-sm-4">Email</dt><dd class="col-sm-8"><?= e($account['email'] ?? '') ?></dd><?php if ($person): ?><dt class="col-sm-4">Name</dt><dd class="col-sm-8"><?= e($person['display_name']) ?></dd><dt class="col-sm-4">Employee number</dt><dd class="col-sm-8"><?= e($person['employee_number']) ?></dd><dt class="col-sm-4">Position</dt><dd class="col-sm-8"><?= e($person['position_title']) ?></dd><?php endif; ?></dl></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
