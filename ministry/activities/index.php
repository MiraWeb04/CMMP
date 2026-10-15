<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_role(['MINISTRY_LEADER']);

$pageTitle = 'Activities';
$activeNav = 'ministry-activities';
$breadcrumbItems = [['label' => 'Activities', 'url' => 'index.php']];

require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header">
    <div>
        <h1>Activities</h1>
        <p>Ministry activity workspace.</p>
    </div>
</div>
<div class="cmmp-card">
    <p class="text-muted mb-0">This route is available for ministry leader activities.</p>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
