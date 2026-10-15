<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/SchedulingService.php';

final class AvailabilityService
{
    private const PERSONNEL_TYPES = ['PASTOR', 'COUNSELOR'];
    private const BLOCKING_APPOINTMENT_STATUSES = ['PENDING', 'CONFIRMED', 'COMPLETED', 'NO_SHOW'];

    public static function check(array $data): array
    {
        $personnelType = (string) ($data['personnel_type'] ?? $data['type'] ?? '');
        $personnelId = filter_var($data['personnel_id'] ?? $data['staff_id'] ?? $data['assigned_staff_id'] ?? null, FILTER_VALIDATE_INT);
        $start = (string) ($data['start_datetime'] ?? self::combineDateAndTime($data['requested_date'] ?? $data['date'] ?? '', $data['start_time'] ?? ''));
        $end = (string) ($data['end_datetime'] ?? self::combineDateAndTime($data['requested_date'] ?? $data['date'] ?? '', $data['end_time'] ?? ''));

        if (!$personnelId) {
            return SchedulingService::checkAvailability($data);
        }

        return self::isAvailable($personnelType, (int) $personnelId, $start, $end);
    }

    public static function isAvailable(
        string $personnelType,
        int $personnelId,
        string $requestedDate,
        string $startTime,
        ?string $endTime = null,
        ?int $excludeAppointmentId = null
    ): array {
        if ($endTime === null) {
            [$requestedDate, $startTime, $endTime] = self::splitDateTimeArguments($requestedDate, $startTime);
        }

        $type = self::normalizePersonnelType($personnelType);
        $window = self::normalizeWindow($requestedDate, $startTime, $endTime);
        $person = self::personnel($type, $personnelId);
        if ($person === null) {
            return ['available' => false, 'reason' => 'Personnel is inactive, unavailable, or not a ' . ucfirst(strtolower($type)) . '.', 'conflicts' => []];
        }

        $conflicts = self::overlappingAppointments($personnelId, $window['start'], $window['end'], $excludeAppointmentId);
        return [
            'available' => $conflicts === [],
            'reason' => $conflicts === [] ? null : 'Personnel has an overlapping appointment.',
            'conflicts' => $conflicts,
            'personnel' => $person,
            'start_datetime' => $window['start'],
            'end_datetime' => $window['end'],
        ];
    }

    public static function availablePersonnel(
        string $personnelType,
        string $requestedDate,
        string $startTime,
        ?string $endTime = null
    ): array {
        if ($endTime === null) {
            [$requestedDate, $startTime, $endTime] = self::splitDateTimeArguments($requestedDate, $startTime);
        }

        $type = self::normalizePersonnelType($personnelType);
        $window = self::normalizeWindow($requestedDate, $startTime, $endTime);
        $query = database()->prepare(
            'SELECT DISTINCT s.id, s.employee_number, s.position_title,
                    COALESCE(CONCAT(m.first_name, " ", m.last_name), u.username, s.employee_number) AS display_name,
                    u.username, u.email, u.role AS personnel_type
             FROM staff s
             INNER JOIN users u ON u.id = s.user_id OR u.id = (SELECT m2.user_id FROM members m2 WHERE m2.id = s.member_id LIMIT 1)
             LEFT JOIN members m ON m.id = s.member_id
             WHERE s.employment_status = "ACTIVE"
               AND u.status = "ACTIVE"
               AND u.role = :personnel_type
               AND NOT EXISTS (
                   SELECT 1
                   FROM appointments a
                   LEFT JOIN appointment_staff aps ON aps.appointment_id = a.id
                                     LEFT JOIN events ev ON ev.id = a.event_id
                                     LEFT JOIN service_requests sr ON sr.id = a.service_request_id
                   WHERE (a.assigned_staff_id = s.id OR aps.staff_id = s.id)
                     AND a.status <> "CANCELLED"
                                         AND (a.event_id IS NULL OR ev.event_status <> "CANCELLED")
                                         AND (a.service_request_id IS NULL OR sr.status <> "CANCELLED")
                     AND a.start_datetime < :end_datetime
                     AND a.end_datetime > :start_datetime
               )
             ORDER BY display_name'
        );
        $query->execute([
            'personnel_type' => $type,
            'start_datetime' => $window['start'],
            'end_datetime' => $window['end'],
        ]);

        return $query->fetchAll();
    }

    public static function rosterForWindow(array $roles, string $startDateTime, string $endDateTime, ?int $excludeRequestId = null): array
    {
        $normalized = [];
        foreach ($roles as $role) {
            $normalized[] = self::normalizePersonnelType((string) $role);
        }
        $normalized = array_values(array_unique($normalized));
        $available = [];
        $unavailable = [];
        foreach ($normalized as $role) {
            $start = new DateTimeImmutable($startDateTime);
            $end = new DateTimeImmutable($endDateTime);
            if ($end <= $start) {
                throw new InvalidArgumentException('Invalid schedule.');
            }
            $window = ['start' => $start->format('Y-m-d H:i:s'), 'end' => $end->format('Y-m-d H:i:s')];
            $people = self::personnelByRole($role);
            foreach ($people as $person) {
                $appointmentConflicts = self::overlappingAppointments((int) $person['id'], $window['start'], $window['end'], null);
                $assignmentConflicts = self::overlappingAssignments((int) $person['id'], $window['start'], $window['end'], $excludeRequestId);
                if ($appointmentConflicts === [] && $assignmentConflicts === []) {
                    $available[] = $person;
                    continue;
                }
                $reason = 'Already assigned to another service';
                if ($appointmentConflicts !== []) {
                    $conflict = $appointmentConflicts[0];
                    $reason = 'Already assigned to another service';
                    if (!empty($conflict['service_request_title'])) {
                        $reason = 'Already assigned to another service';
                    } elseif (!empty($conflict['event_title'])) {
                        $reason = 'Already assigned to a church event';
                    } elseif (!empty($conflict['appointment_type'])) {
                        $reason = 'Already assigned to another appointment';
                    }
                }
                $person['unavailable_reason'] = $reason;
                $unavailable[] = $person;
            }
        }
        return ['available' => $available, 'unavailable' => $unavailable];
    }

    private static function personnelByRole(string $type): array
    {
        $query = database()->prepare(
            'SELECT DISTINCT s.id, s.employee_number, s.position_title, s.employment_status,
                    COALESCE(CONCAT(m.first_name, " ", m.last_name), u.username, s.employee_number) AS display_name,
                    u.username, u.email, u.role AS personnel_type, u.status AS account_status
             FROM staff s
             INNER JOIN users u ON u.id = s.user_id OR u.id = (SELECT m2.user_id FROM members m2 WHERE m2.id = s.member_id LIMIT 1)
             LEFT JOIN members m ON m.id = s.member_id
             WHERE s.employment_status = "ACTIVE"
               AND u.status = "ACTIVE"
               AND u.role = :personnel_type
             ORDER BY display_name'
        );
        $query->execute(['personnel_type' => $type]);
        return $query->fetchAll();
    }

    private static function overlappingAssignments(int $personnelId, string $start, string $end, ?int $excludeRequestId): array
    {
        $sql = 'SELECT sr.id, sr.request_number, sr.subject, st.name AS service_name
                FROM service_assignments sa
                INNER JOIN service_requests sr ON sr.id = sa.service_request_id
                INNER JOIN service_types st ON st.id = sr.service_type_id
                WHERE sa.staff_id = :staff_id
                  AND sr.status IN ("PENDING", "PENDING_ASSIGNMENT", "UNDER_REVIEW", "DOCUMENTS_REQUIRED", "ASSIGNED", "APPROVED", "SCHEDULED", "CONFIRMED")
                  AND sr.preferred_start_datetime IS NOT NULL
                  AND sr.preferred_start_datetime < :end_datetime
                  AND COALESCE(sr.preferred_end_datetime, sr.preferred_start_datetime) > :start_datetime';
        $parameters = [
            'staff_id' => $personnelId,
            'start_datetime' => $start,
            'end_datetime' => $end,
        ];
        if ($excludeRequestId) {
            $sql .= ' AND sr.id <> :exclude_request_id';
            $parameters['exclude_request_id'] = $excludeRequestId;
        }
        $query = database()->prepare($sql);
        $query->execute($parameters);
        return $query->fetchAll();
    }

    public static function personnelTypeForStaff(int $personnelId): ?string
    {
        $query = database()->prepare(
            'SELECT u.role
             FROM staff s
             INNER JOIN users u ON u.id = s.user_id OR u.id = (SELECT m.user_id FROM members m WHERE m.id = s.member_id LIMIT 1)
             WHERE s.id = :staff_id
               AND u.status = "ACTIVE"
               AND u.role IN ("PASTOR", "COUNSELOR")
             LIMIT 1'
        );
        $query->execute(['staff_id' => $personnelId]);
        $type = $query->fetchColumn();
        return $type === false ? null : (string) $type;
    }

    private static function personnel(string $type, int $personnelId): ?array
    {
        $query = database()->prepare(
            'SELECT s.id, s.employee_number, s.position_title,
                    COALESCE(CONCAT(m.first_name, " ", m.last_name), u.username, s.employee_number) AS display_name,
                    u.username, u.email, u.role AS personnel_type
             FROM staff s
             INNER JOIN users u ON u.id = s.user_id OR u.id = (SELECT m2.user_id FROM members m2 WHERE m2.id = s.member_id LIMIT 1)
             LEFT JOIN members m ON m.id = s.member_id
             WHERE s.id = :staff_id
               AND s.employment_status = "ACTIVE"
               AND u.status = "ACTIVE"
               AND u.role = :personnel_type
             LIMIT 1'
        );
        $query->execute(['staff_id' => $personnelId, 'personnel_type' => $type]);
        $person = $query->fetch();
        return $person ?: null;
    }

    private static function overlappingAppointments(int $personnelId, string $start, string $end, ?int $excludeAppointmentId): array
    {
        $conditions = [
            '(a.assigned_staff_id = :staff_id OR aps.staff_id = :staff_id_joined)',
            'a.status <> "CANCELLED"',
            'a.start_datetime < :end_datetime',
            'a.end_datetime > :start_datetime',
        ];
        $parameters = [
            'staff_id' => $personnelId,
            'staff_id_joined' => $personnelId,
            'end_datetime' => $end,
            'start_datetime' => $start,
        ];
        if ($excludeAppointmentId) {
            $conditions[] = 'a.id <> :exclude_appointment_id';
            $parameters['exclude_appointment_id'] = $excludeAppointmentId;
        }

        $query = database()->prepare(
            'SELECT DISTINCT a.id, a.appointment_type, a.start_datetime, a.end_datetime, a.status,
                      f.name AS facility_name, e.title AS event_title,
                      sr.subject AS service_request_title
             FROM appointments a
             LEFT JOIN appointment_staff aps ON aps.appointment_id = a.id
             LEFT JOIN facilities f ON f.id = a.facility_id
             LEFT JOIN events e ON e.id = a.event_id
                  LEFT JOIN service_requests sr ON sr.id = a.service_request_id
             WHERE ' . implode(' AND ', $conditions) . '
                  AND (a.event_id IS NULL OR e.event_status <> "CANCELLED")
                  AND (a.service_request_id IS NULL OR sr.status <> "CANCELLED")
             ORDER BY a.start_datetime'
        );
        $query->execute($parameters);
        return $query->fetchAll();
    }

    private static function normalizePersonnelType(string $personnelType): string
    {
        $type = strtoupper(trim($personnelType));
        if (!in_array($type, self::PERSONNEL_TYPES, true)) {
            throw new InvalidArgumentException('Personnel type must be PASTOR or COUNSELOR.');
        }
        return $type;
    }

    private static function normalizeWindow(string $requestedDate, string $startTime, string $endTime): array
    {
        $start = self::parseDateTime($requestedDate, $startTime);
        $end = self::parseDateTime($requestedDate, $endTime);
        if ($end <= $start) {
            throw new InvalidArgumentException('Invalid schedule.');
        }
        return ['start' => $start->format('Y-m-d H:i:s'), 'end' => $end->format('Y-m-d H:i:s')];
    }

    private static function parseDateTime(string $date, string $time): DateTimeImmutable
    {
        $value = trim($date) . ' ' . trim($time);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value)
            ?: DateTimeImmutable::createFromFormat('!Y-m-d H:i', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$parsed || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgumentException('Use a valid date and time.');
        }
        return $parsed;
    }

    private static function combineDateAndTime(string $date, string $time): string
    {
        return trim($date) . ' ' . trim($time);
    }

    private static function splitDateTimeArguments(string $dateTime, string $endDateTime): array
    {
        $start = new DateTimeImmutable($dateTime);
        $end = new DateTimeImmutable($endDateTime);
        return [$start->format('Y-m-d'), $start->format('H:i:s'), $end->format('H:i:s')];
    }
}
