<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/auth.php';

$dashboardTitle = $dashboardTitle ?? 'Dashboard';
$dashboardLead = $dashboardLead ?? 'Your portal workspace at a glance.';
$dashboardRoleLabel = $dashboardRoleLabel ?? role_display_label();
$dashboardMetrics = $dashboardMetrics ?? [];
$dashboardActions = $dashboardActions ?? [];
$dashboardRecentTitle = $dashboardRecentTitle ?? '';
$dashboardRecentItems = $dashboardRecentItems ?? [];
$dashboardRecentEmpty = $dashboardRecentEmpty ?? 'Nothing to show yet.';
$dashboardNotice = $dashboardNotice ?? '';
$dashboardNoticeClass = $dashboardNoticeClass ?? 'alert-success';
$activeNav = $activeNav ?? 'dashboard';
$pageTitle = $pageTitle ?? $dashboardTitle;
$breadcrumbItems = [['label' => $dashboardTitle, 'url' => APP_BASE_URL . role_redirect_path(current_user_roles())]];

require __DIR__ . '/header.php';
require __DIR__ . '/breadcrumb.php';
?>
<section class="cmmp-page-header">
    <div>
        <span class="cmmp-kicker"><?= e($dashboardRoleLabel) ?></span>
        <h1><?= e($dashboardTitle) ?></h1>
        <p><?= e($dashboardLead) ?></p>
    </div>
</section>
<section class="cmmp-welcome" aria-labelledby="welcome-title">
    <h2 id="welcome-title">Welcome, <?= e(portal_display_name()) ?>.</h2>
    <p>Here is the church workspace that needs your attention.</p>
    <span class="cmmp-role-badge"><?= e($dashboardRoleLabel) ?></span>
</section>
<?php if ($dashboardNotice !== ''): ?>
    <div class="alert <?= e($dashboardNoticeClass) ?>"><?= e($dashboardNotice) ?></div>
<?php endif; ?>
<?php if ($dashboardMetrics !== []): ?>
<div class="row g-3 mb-1">
    <?php foreach ($dashboardMetrics as $metric): ?>
        <div class="col-sm-6 col-xl-3">
            <?php if (!empty($metric['url'])): ?>
                <a class="cmmp-card cmmp-dash-link d-block" href="<?= e($metric['url']) ?>">
                    <div class="text-muted small"><?= e($metric['label']) ?></div>
                    <div class="cmmp-metric-value"><?= e((string) $metric['value']) ?></div>
                </a>
            <?php else: ?>
                <div class="cmmp-card">
                    <div class="text-muted small"><?= e($metric['label']) ?></div>
                    <div class="cmmp-metric-value"><?= e((string) $metric['value']) ?></div>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php if ($dashboardActions !== []): ?>
<h2 class="cmmp-section-title">Your workspace</h2>
<div class="row g-3">
    <?php foreach ($dashboardActions as $card): ?>
        <div class="col-sm-6 col-xl-3">
            <a class="cmmp-card cmmp-dashboard-card cmmp-dash-link d-block h-100" href="<?= e($card['url']) ?>">
                <span class="cmmp-card-icon"><i class="fa-solid <?= e($card['icon']) ?>"></i></span>
                <h3><?= e($card['title']) ?></h3>
                <p><?= e($card['description']) ?></p>
            </a>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php if ($dashboardRecentTitle !== ''): ?>
<h2 class="cmmp-section-title"><?= e($dashboardRecentTitle) ?></h2>
<div class="cmmp-card">
    <?php if ($dashboardRecentItems === []): ?>
        <p class="text-muted mb-0"><?= e($dashboardRecentEmpty) ?></p>
    <?php else: ?>
        <div class="cmmp-recent-list">
        <?php foreach ($dashboardRecentItems as $item): ?>
            <article class="cmmp-recent-item<?= !empty($item['action_html']) ? ' cmmp-recent-item-with-action' : '' ?>">
                <div class="cmmp-recent-item-body">
                    <?php if (!empty($item['url'])): ?>
                        <a href="<?= e($item['url']) ?>"><?= e($item['title']) ?></a>
                    <?php else: ?>
                        <strong><?= e($item['title']) ?></strong>
                    <?php endif; ?>
                    <?php if (!empty($item['meta'])): ?>
                        <small><?= e($item['meta']) ?></small>
                    <?php endif; ?>
                </div>
                <?php if (!empty($item['action_html'])): ?>
                    <div class="cmmp-recent-item-action"><?= $item['action_html'] ?></div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/footer.php'; ?>
