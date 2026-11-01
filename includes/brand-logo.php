<?php
declare(strict_types=1);

$brandLogoClass = $brandLogoClass ?? 'cmmp-brand-logo';
$brandLogoAlt = $brandLogoAlt ?? 'United Church of Christ in the Philippines logo';
?>
<img class="<?= e($brandLogoClass) ?>" src="<?= e(APP_BASE_URL) ?>/assets/images/uccp-logo.png" alt="<?= e($brandLogoAlt) ?>" width="48" height="48" decoding="async">
