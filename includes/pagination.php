<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/pagination.php';

$total = (int) ($paginationTotal ?? 0);
$perPage = (int) ($paginationPerPage ?? CMMP_LIST_PER_PAGE);
$page = (int) ($paginationPage ?? 1);
$filters = is_array($paginationFilters ?? null) ? $paginationFilters : [];
$label = (string) ($paginationLabel ?? 'Pages');
$totalPages = pagination_total_pages($total, $perPage);
$page = min(max(1, $page), $totalPages);

if ($totalPages <= 1) {
    return;
}
?>
<nav class="mt-4" aria-label="<?= e($label) ?>">
    <ul class="pagination justify-content-end mb-0">
        <?php if ($page > 1): ?>
            <li class="page-item"><a class="page-link" href="?<?= e(pagination_query($filters, $page - 1)) ?>">Previous</a></li>
        <?php endif; ?>
        <?php
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        for ($number = $start; $number <= $end; $number++):
        ?>
            <li class="page-item <?= $number === $page ? 'active' : '' ?>">
                <a class="page-link" href="?<?= e(pagination_query($filters, $number)) ?>"><?= $number ?></a>
            </li>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
            <li class="page-item"><a class="page-link" href="?<?= e(pagination_query($filters, $page + 1)) ?>">Next</a></li>
        <?php endif; ?>
    </ul>
</nav>
