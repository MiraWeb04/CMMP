<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

final class ChurchCalendar
{
    private const BLOCKING_REQUEST_STATUSES = [
        'PENDING',
        'PENDING_ASSIGNMENT',
        'UNDER_REVIEW',
        'DOCUMENTS_REQUIRED',
        'ASSIGNED',
        'APPROVED',
        'SCHEDULED',
        'CONFIRMED',
    ];
    private const BOOKED_REQUEST_STATUSES = ['ASSIGNED', 'APPROVED', 'SCHEDULED', 'CONFIRMED', 'COMPLETED'];
    private const BOOKED_REQUEST_STATUS_SQL = '"ASSIGNED", "APPROVED", "SCHEDULED", "CONFIRMED", "COMPLETED"';

    public static function events(string $rangeStart, string $rangeEnd, ?int $facilityId = null): array
    {
        [$start, $end] = self::normalizeRange($rangeStart, $rangeEnd);
        $events = [];
        $facilityFilter = $facilityId ? ' AND (facility_id IS NULL OR facility_id = :facility_filter_id)' : '';
        $parameters = ['start_datetime' => $start, 'end_datetime' => $end];
        if ($facilityId) {
            $parameters['facility_filter_id'] = $facilityId;
        }

        $queries = [
            [
                'sql' => 'SELECT title, start_datetime, end_datetime, facility_id
                          FROM events
                          WHERE event_status = "PUBLISHED"
                            AND start_datetime < :end_datetime
                            AND end_datetime > :start_datetime' . $facilityFilter,
                'type' => 'Booked',
                'title' => 'title',
                'titlePrefix' => 'Church event',
            ],
            [
                'sql' => 'SELECT name AS title, start_datetime, end_datetime, NULL AS facility_id
                          FROM ministry_activities
                          WHERE activity_status IN ("PLANNED", "ONGOING")
                            AND start_datetime IS NOT NULL
                            AND start_datetime < :end_datetime
                            AND COALESCE(end_datetime, start_datetime) > :start_datetime',
                'type' => 'Booked',
                'title' => 'title',
                'titlePrefix' => 'Church activity',
            ],
            [
                'sql' => 'SELECT appointment_type AS title, start_datetime, end_datetime, facility_id
                          FROM appointments
                          WHERE status = "CONFIRMED"
                            AND start_datetime < :end_datetime
                            AND end_datetime > :start_datetime' . $facilityFilter,
                'type' => 'Booked',
                'title' => 'title',
                'titlePrefix' => 'Booked',
            ],
            [
                'sql' => 'SELECT sr.subject AS title, sr.preferred_start_datetime AS start_datetime,
                                 sr.preferred_end_datetime AS end_datetime, sr.preferred_facility_id AS facility_id,
                                 sr.status AS request_status
                          FROM service_requests sr
                          WHERE sr.status IN (' . self::BOOKED_REQUEST_STATUS_SQL . ')
                            AND sr.preferred_start_datetime IS NOT NULL
                            AND sr.preferred_start_datetime < :end_datetime
                            AND COALESCE(sr.preferred_end_datetime, sr.preferred_start_datetime) > :start_datetime
                            AND EXISTS (
                              SELECT 1 FROM service_request_participants 
                              WHERE service_request_id = sr.id
                              LIMIT 1
                            )' . str_replace('facility_id', 'preferred_facility_id', $facilityFilter),
                'type' => 'Booked',
                'title' => 'title',
                'titlePrefix' => 'Booked',
            ],
        ];

        foreach ($queries as $definition) {
            $query = database()->prepare($definition['sql']);
            $queryParameters = ['start_datetime' => $start, 'end_datetime' => $end];
            if (str_contains($definition['sql'], ':facility_filter_id')) {
                $queryParameters['facility_filter_id'] = $facilityId;
            }
            $query->execute($queryParameters);
            foreach ($query->fetchAll() as $item) {
                $prefix = $definition['titlePrefix'] ?? $definition['type'];
                $events[] = [
                    'title' => $prefix . ': ' . $item[$definition['title']],
                    'start' => $item['start_datetime'],
                    'end' => $item['end_datetime'] ?: $item['start_datetime'],
                    'className' => 'cmmp-fc-event-booked',
                    'extendedProps' => [
                        'type' => $definition['type'],
                        'facility_id' => $item['facility_id'] ? (int) $item['facility_id'] : null,
                        'church_wide' => !$item['facility_id'],
                        'status' => $item['request_status'] ?? null,
                    ],
                ];
            }
        }

        return $events;
    }

    public static function bookedDates(string $rangeStart, string $rangeEnd, ?int $facilityId = null, ?string $serviceCode = null): array
    {
        if ($serviceCode !== null && $serviceCode !== '') {
            $dates = [];
            foreach (self::bookingOccupiedDates($rangeStart, $rangeEnd, $facilityId, $serviceCode) as $date) {
                $dates[$date] = true;
            }
            ksort($dates);

            return array_keys($dates);
        }

        $dates = [];
        foreach (self::events($rangeStart, $rangeEnd, $facilityId) as $event) {
            self::collectDates($dates, (string) $event['start'], (string) ($event['end'] ?? $event['start']));
        }

        ksort($dates);

        return array_keys($dates);
    }

    /**
     * @return list<string>
     */
    private static function bookingOccupiedDates(
        string $rangeStart,
        string $rangeEnd,
        ?int $facilityId = null,
        ?string $serviceCode = null
    ): array {
        [$start, $end] = self::normalizeRange($rangeStart, $rangeEnd);
        $blocked = [];

        $appointmentSql = 'SELECT start_datetime, end_datetime
             FROM appointments
             WHERE status IN ("PENDING", "CONFIRMED")
               AND start_datetime < :end_datetime
               AND end_datetime > :start_datetime';
        $appointmentParams = ['start_datetime' => $start, 'end_datetime' => $end];
        if ($facilityId !== null && $facilityId > 0) {
            $appointmentSql .= ' AND (facility_id IS NULL OR facility_id = :facility_id)';
            $appointmentParams['facility_id'] = $facilityId;
        }
        $appointmentQuery = database()->prepare($appointmentSql);
        $appointmentQuery->execute($appointmentParams);
        foreach ($appointmentQuery->fetchAll() as $item) {
            self::collectDates($blocked, (string) $item['start_datetime'], (string) $item['end_datetime']);
        }

        $requestSql = 'SELECT preferred_start_datetime, preferred_end_datetime
             FROM service_requests sr
             INNER JOIN service_types st ON st.id = sr.service_type_id
             WHERE sr.status IN ("ASSIGNED", "APPROVED", "SCHEDULED", "CONFIRMED", "COMPLETED")
               AND sr.preferred_start_datetime IS NOT NULL
               AND sr.preferred_start_datetime < :end_datetime
               AND COALESCE(sr.preferred_end_datetime, sr.preferred_start_datetime) > :start_datetime
               AND st.code <> "HOUSE_BLESSING"';
        $requestParams = ['start_datetime' => $start, 'end_datetime' => $end];
        if ($facilityId !== null && $facilityId > 0) {
            $requestSql .= ' AND (sr.preferred_facility_id IS NULL OR sr.preferred_facility_id = :facility_id)';
            $requestParams['facility_id'] = $facilityId;
        }
        if ($serviceCode === 'HOUSE_BLESSING') {
            $requestSql .= ' AND st.code = "HOUSE_BLESSING"';
        }
        $requestQuery = database()->prepare($requestSql);
        $requestQuery->execute($requestParams);
        foreach ($requestQuery->fetchAll() as $item) {
            self::collectDates($blocked, (string) $item['preferred_start_datetime'], (string) $item['preferred_end_datetime']);
        }

        return array_keys($blocked);
    }

    public static function assertAvailable(
        string $startDateTime,
        string $endDateTime,
        ?int $facilityId = null,
        ?int $excludeRequestId = null,
        ?int $excludeEventId = null,
        ?string $serviceCode = null
    ): void {
        $conflicts = self::conflicts($startDateTime, $endDateTime, $facilityId, $excludeRequestId, $excludeEventId, $serviceCode);
        if ($conflicts !== []) {
            if ($serviceCode === 'FUNERAL_SERVICE') {
                $message = 'The selected date is no longer available. Please choose a different date range.';
            } else {
                $message = 'The selected venue and time are no longer available.';
            }
            throw new CalendarConflictException($message, $conflicts);
        }
    }

    public static function lockDateRange(PDO $connection, string $startDateTime, string $endDateTime): void
    {
        [$start, $end] = self::normalizeRange($startDateTime, $endDateTime);
        $insert = $connection->prepare('INSERT IGNORE INTO scheduling_resource_locks (resource_key) VALUES (:resource_key)');
        $lock = $connection->prepare('SELECT resource_key FROM scheduling_resource_locks WHERE resource_key = :resource_key FOR UPDATE');
        $first = new DateTimeImmutable($start);
        $last = new DateTimeImmutable($end);
        for ($date = $first->setTime(0, 0); $date < $last; $date = $date->modify('+1 day')) {
            $key = 'calendar:' . $date->format('Y-m-d');
            $insert->execute(['resource_key' => $key]);
            $lock->execute(['resource_key' => $key]);
        }
    }

    public static function conflicts(string $startDateTime, string $endDateTime, ?int $facilityId = null, ?int $excludeRequestId = null, ?int $excludeEventId = null, ?string $serviceCode = null): array
    {
        [$start, $end] = self::normalizeRange($startDateTime, $endDateTime);
        $conflicts = [];
        $conflictKeys = [];
        $ranges = $serviceCode === 'FUNERAL_SERVICE' ? self::funeralNightRanges($start, $end) : [[$start, $end]];
        $facilityClause = $facilityId === null ? ' AND facility_id IS NULL' : ' AND facility_id = :facility_filter_id';
        $parameters = ['start_datetime' => $start, 'end_datetime' => $end];
        if ($facilityId !== null) {
            $parameters['facility_filter_id'] = $facilityId;
        }

        $sources = [
            ['sql' => 'SELECT title AS label, start_datetime, end_datetime, facility_id FROM events WHERE event_status = "PUBLISHED"' . ($excludeEventId ? ' AND id <> :exclude_event_id' : ''), 'type' => 'Church event'],
            ['sql' => 'SELECT name AS label, start_datetime, end_datetime, NULL AS facility_id FROM ministry_activities WHERE activity_status IN ("PLANNED", "ONGOING") AND start_datetime IS NOT NULL', 'type' => 'Church activity'],
            ['sql' => 'SELECT appointment_type AS label, start_datetime, end_datetime, facility_id FROM appointments WHERE status IN ("PENDING", "CONFIRMED")', 'type' => 'Existing booking'],
            ['sql' => 'SELECT sr.subject AS label, sr.preferred_start_datetime AS start_datetime, sr.preferred_end_datetime AS end_datetime, sr.preferred_facility_id AS facility_id, st.code AS service_code FROM service_requests sr INNER JOIN service_types st ON st.id = sr.service_type_id WHERE sr.status IN ("PENDING", "PENDING_ASSIGNMENT", "UNDER_REVIEW", "DOCUMENTS_REQUIRED", "ASSIGNED", "APPROVED", "SCHEDULED", "CONFIRMED") AND sr.preferred_start_datetime IS NOT NULL AND st.code <> "HOUSE_BLESSING" AND EXISTS (SELECT 1 FROM service_request_participants WHERE service_request_id = sr.id LIMIT 1)' . ($excludeRequestId ? ' AND sr.id <> :exclude_request_id' : ''), 'type' => 'Service request', 'facilityField' => 'preferred_facility_id', 'rangeClause' => ' AND sr.preferred_start_datetime < :end_datetime AND COALESCE(sr.preferred_end_datetime, sr.preferred_start_datetime) > :start_datetime'],
            ['sql' => 'SELECT sr.subject AS label, sr.preferred_start_datetime AS start_datetime, sr.preferred_end_datetime AS end_datetime, NULL AS facility_id FROM service_requests sr INNER JOIN service_types st ON st.id = sr.service_type_id WHERE sr.status IN ("PENDING", "PENDING_ASSIGNMENT", "UNDER_REVIEW", "DOCUMENTS_REQUIRED", "ASSIGNED", "APPROVED", "SCHEDULED", "CONFIRMED") AND sr.preferred_start_datetime IS NOT NULL AND st.code = "HOUSE_BLESSING" AND EXISTS (SELECT 1 FROM service_request_participants WHERE service_request_id = sr.id LIMIT 1)' . ($excludeRequestId ? ' AND sr.id <> :exclude_request_id' : ''), 'type' => 'House blessing', 'rangeClause' => ' AND sr.preferred_start_datetime < :end_datetime AND COALESCE(sr.preferred_end_datetime, sr.preferred_start_datetime) > :start_datetime'],
        ];

        if ($excludeRequestId) {
            $parameters['exclude_request_id'] = $excludeRequestId;
        }
        foreach ($sources as $source) {
            // Build facility clause for this source
            if ($source['type'] === 'House blessing') {
                // House blessings happen at people's homes, not at church facilities (no facility filtering)
                $sourceFacilityClause = '';
            } else if (isset($source['facilityField'])) {
                // Service requests with their own facility field
                $sourceFacilityClause = $facilityId === null ? ' AND ' . $source['facilityField'] . ' IS NULL' : ' AND ' . $source['facilityField'] . ' = :facility_filter_id';
            } else if ($source['type'] === 'Church activity') {
                // Ministry activities are church-wide (no facility filtering)
                $sourceFacilityClause = '';
            } else {
                // Events and appointments use standard facility clause
                $sourceFacilityClause = $facilityClause;
            }
            $rangeClause = $source['rangeClause'] ?? ' AND start_datetime < :end_datetime AND COALESCE(end_datetime, start_datetime) > :start_datetime';
            $query = database()->prepare($source['sql'] . $rangeClause . $sourceFacilityClause);
            foreach ($ranges as [$rangeStart, $rangeEnd]) {
                $queryParameters = ['start_datetime' => $rangeStart, 'end_datetime' => $rangeEnd];
                if ($excludeRequestId && str_contains($source['sql'], ':exclude_request_id')) {
                    $queryParameters['exclude_request_id'] = $excludeRequestId;
                }
                if ($excludeEventId && str_contains($source['sql'], ':exclude_event_id')) {
                    $queryParameters['exclude_event_id'] = $excludeEventId;
                }
                if (str_contains($sourceFacilityClause, ':facility_filter_id')) {
                    $queryParameters['facility_filter_id'] = $facilityId;
                }
                $query->execute($queryParameters);
                foreach ($query->fetchAll() as $item) {
                    if (($item['service_code'] ?? null) === 'FUNERAL_SERVICE' && !self::overlapsFuneralNight($rangeStart, $rangeEnd, (string) $item['start_datetime'], (string) ($item['end_datetime'] ?: $item['start_datetime']))) {
                        continue;
                    }
                    $conflictKey = $source['type'] . '|' . $item['label'] . '|' . $item['start_datetime'] . '|' . $item['end_datetime'];
                    if (isset($conflictKeys[$conflictKey])) {
                        continue;
                    }
                    $conflictKeys[$conflictKey] = true;
                    $conflicts[] = ['type' => $source['type'], 'title' => $item['label'], 'start' => $item['start_datetime'], 'end' => $item['end_datetime'], 'facility_id' => $item['facility_id'] ? (int) $item['facility_id'] : null];
                }
            }
        }

        return $conflicts;
    }

    private static function funeralNightRanges(string $start, string $end): array
    {
        $ranges = [];
        $firstDate = new DateTimeImmutable($start);
        $lastDate = new DateTimeImmutable($end);
        for ($date = $firstDate->setTime(18, 0); $date->format('Y-m-d') <= $lastDate->format('Y-m-d'); $date = $date->modify('+1 day')) {
            $ranges[] = [$date->format('Y-m-d 18:00:00'), $date->format('Y-m-d 20:00:00')];
        }
        return $ranges;
    }

    private static function overlapsFuneralNight(string $rangeStart, string $rangeEnd, string $funeralStart, string $funeralEnd): bool
    {
        foreach (self::funeralNightRanges($funeralStart, $funeralEnd) as [$nightStart, $nightEnd]) {
            if ($nightStart < $rangeEnd && $nightEnd > $rangeStart) {
                return true;
            }
        }
        return false;
    }

    public static function blockedDates(int $year): array
    {
        if ($year < 2000 || $year > 2100) {
            throw new InvalidArgumentException('Select a valid calendar year.');
        }

        $start = sprintf('%04d-01-01 00:00:00', $year);
        $end = sprintf('%04d-01-01 00:00:00', $year + 1);
        $blocked = [];

        $eventQuery = database()->prepare(
            'SELECT title, start_datetime, end_datetime
             FROM events
             WHERE event_status = "PUBLISHED"
               AND start_datetime < :end_datetime
               AND end_datetime > :start_datetime'
        );
        $eventQuery->execute(['start_datetime' => $start, 'end_datetime' => $end]);
        foreach ($eventQuery->fetchAll() as $item) {
            self::addRange($blocked, $item['start_datetime'], $item['end_datetime'], 'Church event', (string) $item['title']);
        }

        $activityQuery = database()->prepare(
            'SELECT name, start_datetime, end_datetime
             FROM ministry_activities
             WHERE activity_status IN ("PLANNED", "ONGOING")
               AND start_datetime IS NOT NULL
               AND start_datetime < :end_datetime
               AND COALESCE(end_datetime, start_datetime) >= :start_datetime'
        );
        $activityQuery->execute(['start_datetime' => $start, 'end_datetime' => $end]);
        foreach ($activityQuery->fetchAll() as $item) {
            self::addRange($blocked, $item['start_datetime'], $item['end_datetime'], 'Church activity', (string) $item['name']);
        }

        $appointmentQuery = database()->prepare(
            'SELECT appointment_type, start_datetime, end_datetime
             FROM appointments
             WHERE status IN ("PENDING", "CONFIRMED")
               AND start_datetime < :end_datetime
               AND end_datetime > :start_datetime'
        );
        $appointmentQuery->execute(['start_datetime' => $start, 'end_datetime' => $end]);
        foreach ($appointmentQuery->fetchAll() as $item) {
            self::addRange($blocked, $item['start_datetime'], $item['end_datetime'], 'Existing booking', (string) $item['appointment_type']);
        }

        $requestQuery = database()->prepare(
            'SELECT subject, preferred_start_datetime, preferred_end_datetime
             FROM service_requests
             WHERE status IN ("PENDING", "PENDING_ASSIGNMENT", "UNDER_REVIEW", "ASSIGNED", "APPROVED", "SCHEDULED", "CONFIRMED")
               AND preferred_start_datetime IS NOT NULL
               AND preferred_start_datetime < :end_datetime
               AND COALESCE(preferred_end_datetime, preferred_start_datetime) > :start_datetime'
        );
        $requestQuery->execute(['start_datetime' => $start, 'end_datetime' => $end]);
        foreach ($requestQuery->fetchAll() as $item) {
            self::addRange($blocked, $item['preferred_start_datetime'], $item['preferred_end_datetime'], 'Service request', (string) $item['subject']);
        }

        ksort($blocked);
        return $blocked;
    }

    private static function normalizeRange(string $rangeStart, string $rangeEnd): array
    {
        $startTimestamp = strtotime($rangeStart);
        $endTimestamp = strtotime($rangeEnd);
        if ($startTimestamp === false || $endTimestamp === false || $endTimestamp <= $startTimestamp) {
            throw new InvalidArgumentException('Invalid calendar date range.');
        }
        return [date('Y-m-d H:i:s', $startTimestamp), date('Y-m-d H:i:s', $endTimestamp)];
    }

    private static function collectDates(array &$dates, string $start, string $end): void
    {
        $first = new DateTimeImmutable($start);
        $last = new DateTimeImmutable($end ?: $start);
        if ($last < $first) {
            $last = $first;
        }

        for ($date = $first->setTime(0, 0); $date <= $last->setTime(0, 0); $date = $date->modify('+1 day')) {
            $dates[$date->format('Y-m-d')] = true;
        }
    }

    private static function addRange(array &$blocked, ?string $start, ?string $end, string $type, string $title): void
    {
        if (!$start) {
            return;
        }

        $first = new DateTimeImmutable($start);
        $last = new DateTimeImmutable($end ?: $start);
        if ($last < $first) {
            return;
        }

        for ($date = $first->setTime(0, 0); $date <= $last; $date = $date->modify('+1 day')) {
            $key = $date->format('Y-m-d');
            $blocked[$key] ??= [];
            $blocked[$key][] = ['type' => $type, 'title' => $title];
        }
    }
}

final class CalendarConflictException extends RuntimeException
{
    public function __construct(string $message, public readonly array $conflicts = [])
    {
        parent::__construct($message);
    }
}