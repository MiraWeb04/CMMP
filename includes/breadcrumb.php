<?php
declare(strict_types=1);

$breadcrumbItems = $breadcrumbItems ?? [];
?>
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb cmmp-breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="<?= e(APP_BASE_URL) ?>/auth/protected-test.php"><i class="fa-solid fa-house"></i><span class="visually-hidden">Home</span></a></li>
        <?php foreach ($breadcrumbItems as $index => $item): ?>
            <?php if ($index === count($breadcrumbItems) - 1): ?>
                <li class="breadcrumb-item active" aria-current="page"><?= e($item['label']) ?></li>
            <?php else: ?>
                <li class="breadcrumb-item"><a href="<?= e($item['url']) ?>"><?= e($item['label']) ?></a></li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ol>
</nav>