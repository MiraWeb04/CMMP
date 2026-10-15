<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/auth.php';

$dashboardTitle = $dashboardTitle ?? 'Dashboard';
$dashboardLead = $dashboardLead ?? 'Your portal workspace at a glance.';
$dashboardCards = $dashboardCards ?? [];
$activeNav = $activeNav ?? 'dashboard';
$currentUser = authenticated_user();
$currentRoles = current_user_roles();
$breadcrumbItems = [['label' => $dashboardTitle, 'url' => APP_BASE_URL . '/auth/protected-test.php']];
require __DIR__ . '/header.php';
require __DIR__ . '/breadcrumb.php';
?>
<section class="cmmp-page-header">
    <div>
        <h1><?= e($dashboardTitle) ?></h1>
        <p><?= e($dashboardLead) ?></p>
    </div>
</section>
<section class="cmmp-welcome" aria-labelledby="welcome-title">
    <h2 id="welcome-title">Welcome, <?= e($currentUser['username'] ?? 'member') ?>.</h2>
    <p>This is your CMMP workspace. Portal features will appear here as they become available.</p>
    <span class="cmmp-role-badge"><?= e(implode(' / ', $currentRoles)) ?></span>
</section>
<div class="alert alert-light border d-flex align-items-center gap-2" role="status">
    <i class="fa-solid fa-circle-info text-primary"></i>
    <span>This dashboard is a layout placeholder. No operational data is being displayed yet.</span>
</div>
<h2 class="cmmp-section-title">Quick access</h2>
<div class="row g-3">
    <?php foreach ($dashboardCards as $card): ?>
        <div class="col-sm-6 col-xl-3">
            <article class="cmmp-card">
                <span class="cmmp-card-icon"><i class="fa-solid <?= e($card['icon']) ?>"></i></span>
                <h3><?php if (!empty($card['url'])): ?><a class="text-decoration-none" href="<?= e($card['url']) ?>"><?= e($card['title']) ?></a><?php else: ?><?= e($card['title']) ?><?php endif; ?></h3>
                <p><?= e($card['description']) ?></p>
            </article>
        </div>
    <?php endforeach; ?>
</div>
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div class="toast cmmp-toast" role="status" aria-live="polite" aria-atomic="true" data-cmmp-toast data-bs-delay="4500">
        <div class="toast-header">
            <i class="fa-solid fa-church text-primary me-2"></i>
            <strong class="me-auto">CMMP</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">Your portal workspace is ready.</div>
    </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
