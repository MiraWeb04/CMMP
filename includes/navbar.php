<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../models/Notification.php';

$currentUser = $currentUser ?? authenticated_user();
$currentRoles = $currentRoles ?? current_user_roles();
$notificationUrl = has_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF', 'PASTOR', 'COUNSELOR', 'MINISTRY_LEADER'])
    ? APP_BASE_URL . '/admin/staff/notifications.php'
    : APP_BASE_URL . '/member/notifications/';
$notificationUnread = Notification::unreadCount((int) ($currentUser['id'] ?? 0));
?>
<nav class="navbar navbar-expand-lg cmmp-navbar sticky-top">
	<div class="container-fluid px-3 px-lg-4">
		<button class="btn cmmp-menu-toggle d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#cmmpSidebar" aria-controls="cmmpSidebar" aria-label="Open navigation">
			<i class="fa-solid fa-bars"></i>
		</button>
		<a class="navbar-brand d-flex align-items-center gap-2" href="<?= e(APP_BASE_URL) ?>/member-portal.php">
			<?php require __DIR__ . '/brand-logo.php'; ?>
			<span><strong>CMMP</strong><small class="d-block">UCCP Church Portal</small></span>
		</a>
		<div class="ms-auto d-flex align-items-center gap-2">
			<div class="cmmp-top-search d-none d-lg-flex">
				<i class="fa-solid fa-magnifying-glass"></i>
				<input class="form-control form-control-sm" type="search" aria-label="Search portal" placeholder="Search">
			</div>
			<button class="btn cmmp-icon-button" type="button" data-cmmp-theme-toggle aria-label="Toggle theme">
				<i class="fa-solid fa-circle-half-stroke"></i>
			</button>
			<a class="btn cmmp-icon-button position-relative" href="<?= e($notificationUrl) ?>" aria-label="Notifications">
				<i class="fa-solid fa-bell"></i>
				<?php if ($notificationUnread > 0): ?><span class="cmmp-badge-dot"></span><?php endif; ?>
			</a>
			<span class="d-none d-md-inline text-muted small"><?= e($currentUser['email'] ?? '') ?></span>
			<div class="dropdown">
				<button class="btn cmmp-user-button dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
					<span class="cmmp-avatar"><i class="fa-solid fa-user"></i></span>
					<span class="d-none d-sm-inline"><?= e($currentUser['username'] ?? 'Account') ?></span>
				</button>
				<ul class="dropdown-menu dropdown-menu-end shadow-sm">
					<li><span class="dropdown-item-text small text-muted"><?= e(implode(' / ', $currentRoles)) ?></span></li>
					<li><hr class="dropdown-divider"></li>
					<?php if (in_array('MEMBER', $currentRoles, true)): ?>
					<li><a class="dropdown-item" href="<?= e(APP_BASE_URL) ?>/member/dashboard/"><i class="fa-solid fa-house me-2"></i>Member dashboard</a></li>
					<?php endif; ?>
					<li><a class="dropdown-item" href="<?= e(in_array('MEMBER', $currentRoles, true) && !array_intersect($currentRoles, ['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF', 'PASTOR', 'COUNSELOR', 'MINISTRY_LEADER']) ? APP_BASE_URL . '/member/profile/' : APP_BASE_URL . '/account/profile.php') ?>"><i class="fa-solid fa-user me-2"></i>My profile</a></li>
					<li><a class="dropdown-item" href="<?= e(APP_BASE_URL) ?>/auth/logout.php"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Log out</a></li>
				</ul>
			</div>
		</div>
	</div>
</nav>
