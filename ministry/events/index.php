<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_role(['MINISTRY_LEADER']);

$pageTitle = 'Events';
$activeNav = 'ministry-events';
$breadcrumbItems = [['label' => 'Events', 'url' => 'index.php']];

require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header">
    <div>
        <h1>Events</h1>
        <p>Ministry event workspace.</p>
    </div>
</div>
<div class="cmmp-card">
    <p class="text-muted mb-0">This route is available for ministry leader events.</p>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
