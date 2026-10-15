<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../models/Announcement.php';

require_role('MEMBER');
$announcements = Announcement::listForUser(current_user_roles());
$pageTitle = 'Announcements';
$activeNav = 'member-announcements';
$breadcrumbItems = [['label' => 'Announcements', 'url' => 'index.php']];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header">
    <div>
        <h1>Church announcements</h1>
        <p>Important updates about church schedules, services, and events.</p>
    </div>
</div>
<div class="row g-3">
    <?php if ($announcements === []): ?>
        <div class="col-12"><div class="cmmp-card text-center text-muted py-5">No announcements are currently posted.</div></div>
    <?php endif; ?>
    <?php foreach ($announcements as $announcement): ?>
        <div class="col-12">
            <article class="cmmp-card">
                <div class="d-flex justify-content-between gap-2 mb-2">
                    <h2 class="h5 mb-0"><?= e($announcement['title']) ?></h2>
                    <span class="badge text-bg-light"><?= e($announcement['audience_role'] ?? 'ALL') ?></span>
                </div>
                <p class="text-muted small mb-3"><i class="fa-solid fa-clock me-1"></i><?= e($announcement['published_at'] ?? $announcement['created_at']) ?></p>
                <p class="mb-0"><?= nl2br(e((string) $announcement['body'])) ?></p>
            </article>
        </div>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
