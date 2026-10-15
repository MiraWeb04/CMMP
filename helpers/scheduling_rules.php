<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * Default category → service blocks when DB table is empty or missing.
 *
 * @var array<string, list<string>>
 */
const SCHEDULING_DEFAULT_CATEGORY_SERVICE_BLOCKS = [
    'Worship' => ['FACILITY_RESERVATION', 'MANANITA'],
    'Youth' => ['FACILITY_RESERVATION'],
];

/**
 * @return array<string, list<string>> category name => service type codes
 */
function scheduling_category_service_block_map(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $map = SCHEDULING_DEFAULT_CATEGORY_SERVICE_BLOCKS;
    try {
        $rows = database()->query(
            'SELECT c.name AS category_name, esb.service_type_code
             FROM event_service_blocks esb
             INNER JOIN event_categories c ON c.id = esb.event_category_id
             WHERE c.is_active = 1'
        )->fetchAll();
        if ($rows !== []) {
            $map = [];
            foreach ($rows as $row) {
                $name = (string) $row['category_name'];
                $code = strtoupper((string) $row['service_type_code']);
                $map[$name] ??= [];
                if (!in_array($code, $map[$name], true)) {
                    $map[$name][] = $code;
                }
            }
        }
    } catch (Throwable) {
        // Table may not exist before migration; defaults apply.
    }

    return $map;
}

function scheduling_category_blocks_service(string $categoryName, string $serviceCode): bool
{
    $serviceCode = strtoupper(trim($serviceCode));
    $map = scheduling_category_service_block_map();
    $blocked = $map[$categoryName] ?? [];

    return in_array($serviceCode, $blocked, true);
}

function scheduling_service_blocked_by_events_on_date(string $serviceCode, string $date): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }

    $map = scheduling_category_service_block_map();
    if ($map === []) {
        return false;
    }

    $dayStart = $date . ' 00:00:00';
    $dayEnd = $date . ' 23:59:59';
    $query = database()->prepare(
        'SELECT c.name AS category_name
         FROM events e
         INNER JOIN event_categories c ON c.id = e.category_id
         WHERE e.event_status = "PUBLISHED"
           AND e.start_datetime < :day_end
           AND e.end_datetime > :day_start'
    );
    $query->execute(['day_start' => $dayStart, 'day_end' => $dayEnd]);
    foreach ($query->fetchAll() as $row) {
        if (scheduling_category_blocks_service((string) $row['category_name'], $serviceCode)) {
            return true;
        }
    }

    return false;
}
