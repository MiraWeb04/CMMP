<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function personnel_schedule_month(?string $value): array
{
    $month = DateTimeImmutable::createFromFormat('!Y-m', (string) $value);
    if (!$month instanceof DateTimeImmutable || $month->format('Y-m') !== $value) {
        $month = new DateTimeImmutable('first day of this month');
    }

    $start = $month->modify('first day of this month');
    $next = $start->modify('first day of next month');

    return [
        'key' => $start->format('Y-m'),
        'label' => $start->format('F Y'),
        'start' => $start->format('Y-m-d H:i:s'),
        'end' => $next->format('Y-m-d H:i:s'),
        'prev' => $start->modify('-1 month')->format('Y-m'),
        'next' => $start->modify('+1 month')->format('Y-m'),
        'leading' => (int) $start->format('w'),
        'days' => (int) $start->format('t'),
    ];
}

function personnel_schedule_url(string $path, array $params, string $month): string
{
    $params['month'] = $month;
    return $path . '?' . http_build_query($params);
}

function personnel_schedule_status_label(string $status): string
{
    $label = str_replace('_', ' ', strtolower(trim($status)));
    return $label === '' ? 'Scheduled' : ucwords($label);
}

function personnel_schedule_items(int $staffId, string $start, string $end): array
{
    $connection = database();
    $items = [];

    $appointments = $connection->prepare(
        'SELECT DISTINCT a.start_datetime, a.end_datetime, a.appointment_type, a.status,
                f.name AS facility_name, sr.request_number
         FROM appointments a
         LEFT JOIN appointment_staff aps ON aps.appointment_id = a.id
         LEFT JOIN facilities f ON f.id = a.facility_id
         LEFT JOIN service_requests sr ON sr.id = a.service_request_id
         WHERE a.status <> "CANCELLED"
           AND a.start_datetime >= :start
           AND a.start_datetime < :end
           AND (a.assigned_staff_id = :staff_id OR aps.staff_id = :joined_staff_id)
         ORDER BY a.start_datetime'
    );
    $appointments->execute([
        'start' => $start,
        'end' => $end,
        'staff_id' => $staffId,
        'joined_staff_id' => $staffId,
    ]);
    foreach ($appointments->fetchAll() as $row) {
        $items[] = personnel_schedule_item(
            (string) $row['start_datetime'],
            (string) ($row['end_datetime'] ?? ''),
            (string) $row['appointment_type'],
            (string) ($row['request_number'] ?? 'Appointment'),
            (string) ($row['facility_name'] ?? ''),
            (string) $row['status'],
            'Appointment'
        );
    }

    $services = $connection->prepare(
        'SELECT sr.preferred_start_datetime, sr.preferred_end_datetime, sr.request_number, sr.status,
                st.name AS service_name, f.name AS facility_name, sa.assignment_role
         FROM service_assignments sa
         INNER JOIN service_requests sr ON sr.id = sa.service_request_id
         INNER JOIN service_types st ON st.id = sr.service_type_id
         LEFT JOIN facilities f ON f.id = sr.preferred_facility_id
         WHERE sa.staff_id = :staff_id
           AND sr.preferred_start_datetime IS NOT NULL
           AND sr.preferred_start_datetime >= :start
           AND sr.preferred_start_datetime < :end
           AND sr.status NOT IN ("REJECTED", "CANCELLED")
           AND NOT EXISTS (
               SELECT 1
               FROM appointments a
               LEFT JOIN appointment_staff aps ON aps.appointment_id = a.id
               WHERE a.service_request_id = sr.id
                 AND a.status <> "CANCELLED"
                 AND (a.assigned_staff_id = :assigned_staff_id OR aps.staff_id = :joined_staff_id)
           )
         ORDER BY sr.preferred_start_datetime'
    );
    $services->execute([
        'staff_id' => $staffId,
        'start' => $start,
        'end' => $end,
        'assigned_staff_id' => $staffId,
        'joined_staff_id' => $staffId,
    ]);
    foreach ($services->fetchAll() as $row) {
        $items[] = personnel_schedule_item(
            (string) $row['preferred_start_datetime'],
            (string) ($row['preferred_end_datetime'] ?? ''),
            (string) $row['service_name'],
            trim((string) $row['request_number'] . ' · ' . (string) $row['assignment_role'], ' ·'),
            (string) ($row['facility_name'] ?? ''),
            (string) $row['status'],
            'Service'
        );
    }

    $events = $connection->prepare(
        'SELECT e.start_datetime, e.end_datetime, e.title, e.event_status, e.location,
                f.name AS facility_name, ea.assignment_role
         FROM event_assignments ea
         INNER JOIN events e ON e.id = ea.event_id
         LEFT JOIN facilities f ON f.id = e.facility_id
         WHERE ea.staff_id = :staff_id
           AND e.event_status <> "CANCELLED"
           AND e.start_datetime >= :start
           AND e.start_datetime < :end
         ORDER BY e.start_datetime'
    );
    $events->execute(['staff_id' => $staffId, 'start' => $start, 'end' => $end]);
    foreach ($events->fetchAll() as $row) {
        $location = trim((string) ($row['facility_name'] ?: $row['location'] ?: ''));
        $items[] = personnel_schedule_item(
            (string) $row['start_datetime'],
            (string) ($row['end_datetime'] ?? ''),
            (string) $row['title'],
            (string) ($row['assignment_role'] ?? 'Event'),
            $location,
            (string) $row['event_status'],
            'Event'
        );
    }

    usort($items, static fn(array $left, array $right): int => strcmp($left['start'], $right['start']));
    return $items;
}

function personnel_schedule_item(string $start, string $end, string $title, string $detail, string $location, string $status, string $kind): array
{
    $timestamp = strtotime($start) ?: time();
    return [
        'date' => date('Y-m-d', $timestamp),
        'start' => $start,
        'end' => $end,
        'time' => date('g:i A', $timestamp),
        'title' => $title,
        'detail' => $detail,
        'location' => $location !== '' ? $location : 'No location',
        'status' => $status,
        'kind' => $kind,
    ];
}

function personnel_schedule_by_day(array $items): array
{
    $days = [];
    foreach ($items as $item) {
        $days[$item['date']][] = $item;
    }
    return $days;
}
