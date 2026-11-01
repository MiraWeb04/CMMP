<?php
declare(strict_types=1);

$navigationGroups = require __DIR__ . '/navigation.php';
require_once __DIR__ . '/../helpers/nav_counts.php';
$staffOnlyView = has_role('STAFF') && !has_role(['SUPER_ADMIN', 'CHURCH_ADMIN']);
$navCounts = nav_attention_counts();
?>
<aside class="offcanvas-lg offcanvas-start cmmp-sidebar" tabindex="-1" id="cmmpSidebar" aria-labelledby="cmmpSidebarLabel">
	<div class="offcanvas-header d-lg-none">
		<h2 class="h6 mb-0" id="cmmpSidebarLabel">Portal navigation</h2>
		<button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#cmmpSidebar" aria-label="Close"></button>
	</div>
	<div class="offcanvas-body d-flex flex-column p-0">
		<div class="cmmp-sidebar-heading d-none d-lg-block">Workspace</div>
		<nav class="cmmp-nav px-3" aria-label="Primary navigation">
			<?php foreach ($navigationGroups as $group): ?>
				<?php $isStaffDashboardGroup = $group['label'] === 'Overview' && array_filter($group['items'], static fn (array $item): bool => $item['key'] === 'admin-dashboard'); ?>
				<?php if ($staffOnlyView && (in_array($group['label'], ['Member Portal', 'Administration', 'Ministry & Care'], true) || ($group['label'] === 'Overview' && !$isStaffDashboardGroup))): ?>
					<?php continue; ?>
				<?php endif; ?>
				<?php $visibleItems = array_values(array_filter($group['items'], static fn (array $item): bool => has_role($item['roles']))); ?>
				<?php if ($visibleItems === []): ?>
					<?php continue; ?>
				<?php endif; ?>
				<div class="cmmp-nav-label"><?= e($group['label']) ?></div>
				<?php foreach ($visibleItems as $item): ?>
					<a class="cmmp-nav-link <?= ($activeNav ?? '') === $item['key'] ? 'active' : '' ?>" href="<?= e($item['url']) ?>">
						<i class="fa-solid <?= e($item['icon']) ?> fa-fw"></i>
						<span class="cmmp-nav-text"><?= e($item['label']) ?></span>
						<?php $navCount = (int) ($navCounts[$item['key']] ?? 0); ?>
						<?php if ($navCount > 0): ?>
							<span class="cmmp-nav-count"><?= $navCount > 99 ? '99+' : $navCount ?></span>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
			<?php endforeach; ?>
		</nav>
		<div class="mt-auto p-3">
			<div class="cmmp-sidebar-note"><i class="fa-solid fa-shield-halved me-2"></i>Authorized portal access</div>
		</div>
	</div>
</aside>
