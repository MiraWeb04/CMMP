<?php
declare(strict_types=1);

const CMMP_LIST_PER_PAGE = 15;

function list_page(): int
{
    return max(1, (int) ($_GET['page'] ?? 1));
}

function list_per_page(int $default = CMMP_LIST_PER_PAGE): int
{
    return max(1, min(50, $default));
}

function pagination_total_pages(int $total, int $perPage): int
{
    return max(1, (int) ceil($total / max(1, $perPage)));
}

function pagination_query(array $filters, int $page): string
{
    return http_build_query(array_merge($filters, ['page' => $page]));
}
