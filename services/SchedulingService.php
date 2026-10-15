<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../models/ServiceRequest.php';
require_once __DIR__ . '/NotificationService.php';

final class SchedulingConflict extends RuntimeException
{
    public function __construct(string $message = 'The selected schedule is no longer available. Please select another schedule.')
    {
        parent::__construct($message);
    }
}

final class SchedulingService
{
    private const APPOINTMENT_STATUSES = ['PENDING', 'CONFIRMED', 'COMPLETED', 'CANCELLED', 'NO_SHOW'];

    public static function validateWindow(string $start, string $end): array
    {
        $startTimestamp = strtotime($start);
        $endTimestamp = strtotime($end);
        if ($startTimestamp === false || $endTimestamp === false || $endTimestamp <= $startTimestamp) {
            throw new InvalidArgumentException('Invalid schedule.');
        }

        return [
            'start' => date('Y-m-d H:i:s', $startTimestamp),
            'end' => date('Y-m-d H:i:s', $endTimestamp),
        ];
    }

    public static function resources(array $data): array
    {
        $resources = [];
        foreach (['facility_id' => 'facility', 'event_id' => 'event', 'service_request_id' => 'service'] as $field => $prefix) {
            $id = filter_var($data[$field] ?? null, FILTER_VALIDATE_INT);
            if ($id) {
                $resources[] = $prefix . ':' . $id;
            }
        }
        $staffIds = array_merge(
            [(int) ($data['assigned_staff_id'] ?? 0)],
            array_map('intval', (array) ($data['assigned_staff_ids'] ?? []))
        );
        foreach (array_unique(array_filter($staffIds)) as $staffId) {
            $resources[] = 'staff:' . $staffId;
        }
        $resources = array_values(array_unique($resources));
        sort($resources, SORT_STRING);
        return $resources;
    }

    public static function findConflicts(array $data): array
    {
        $window = self::validateWindow((string) ($data['start_datetime'] ?? ''), (string) ($data['end_datetime'] ?? ''));
        $conditions = ['a.status <> :cancelled', 'a.start_datetime < :end_datetime', 'a.end_datetime > :start_datetime'];
        $parameters = ['cancelled' => 'CANCELLED', 'start_datetime' => $window['start'], 'end_datetime' => $window['end']];
        $excludeId = filter_var($data['exclude_appointment_id'] ?? null, FILTER_VALIDATE_INT);
        if ($excludeId) {
            $conditions[] = 'a.id <> :exclude_appointment_id';
            $parameters['exclude_appointment_id'] = $excludeId;
        }
        $resourceConditions = [];

        foreach (['facility_id' => 'facility', 'event_id' => 'event', 'service_request_id' => 'service'] as $field => $prefix) {
            $id = filter_var($data[$field] ?? null, FILTER_VALIDATE_INT);
            if ($id) {
                $parameter = 'resource_' . $prefix;
                $resourceConditions[] = 'a.' . $field . ' = :' . $parameter;
                $parameters[$parameter] = $id;
            }
        }
        $staffIds = array_merge(
            [(int) ($data['assigned_staff_id'] ?? 0)],
            array_map('intval', (array) ($data['assigned_staff_ids'] ?? []))
        );
        $staffIds = array_values(array_unique(array_filter($staffIds)));
        foreach ($staffIds as $index => $staffId) {
            $assignedParameter = 'assigned_staff_' . $index;
            $joinedParameter = 'joined_staff_' . $index;
            $resourceConditions[] = '(a.assigned_staff_id = :' . $assignedParameter . ' OR aps.staff_id = :' . $joinedParameter . ')';
            $parameters[$assignedParameter] = $staffId;
            $parameters[$joinedParameter] = $staffId;
        }
        if ($resourceConditions === []) {
            return [];
        }

        $query = database()->prepare(
            'SELECT DISTINCT a.id, a.appointment_type, a.start_datetime, a.end_datetime,
                    a.status, f.name AS facility_name, e.title AS event_title
             FROM appointments a
             LEFT JOIN appointment_staff aps ON aps.appointment_id = a.id
             LEFT JOIN facilities f ON f.id = a.facility_id
             LEFT JOIN events e ON e.id = a.event_id
             WHERE ' . implode(' AND ', $conditions) .
             ' AND (' . implode(' OR ', $resourceConditions) . ')
             ORDER BY a.start_datetime'
        );
        $query->execute($parameters);
        return $query->fetchAll();
    }

    public static function checkAvailability(array $data): array
    {
        $conflicts = self::findConflicts($data);
        return ['available' => $conflicts === [], 'conflicts' => $conflicts];
    }

    public static function createAppointment(array $data): int
    {
        $window = self::validateWindow((string) ($data['start_datetime'] ?? ''), (string) ($data['end_datetime'] ?? ''));
        $status = (string) ($data['status'] ?? 'CONFIRMED');
        if (!in_array($status, self::APPOINTMENT_STATUSES, true)) {
            throw new InvalidArgumentException('Invalid appointment status.');
        }
        $connection = database();
        $connection->beginTransaction();
        try {
            $resources = self::resources($data);
            if ($resources === []) {
                throw new InvalidArgumentException('At least one facility, event, service, or staff resource is required.');
            }
            $insertLock = $connection->prepare('INSERT IGNORE INTO scheduling_resource_locks (resource_key) VALUES (:resource_key)');
            $lockResource = $connection->prepare('SELECT resource_key FROM scheduling_resource_locks WHERE resource_key = :resource_key FOR UPDATE');
                foreach (array_unique($resources) as $resource) {
                $insertLock->execute(['resource_key' => $resource]);
                $lockResource->execute(['resource_key' => $resource]);
            }

            $staffIds = array_merge([(int) ($data['assigned_staff_id'] ?? 0)], array_map('intval', (array) ($data['assigned_staff_ids'] ?? [])));
            $conflicts = self::findConflicts($data);
            if ($conflicts !== []) {
                throw new SchedulingConflict();
            }

            require_once __DIR__ . '/AvailabilityService.php';
            foreach (array_unique(array_filter(array_map('intval', $staffIds))) as $staffId) {
                $personnelType = AvailabilityService::personnelTypeForStaff($staffId);
                if ($personnelType === null) {
                    continue;
                }
                $availability = AvailabilityService::isAvailable(
                    $personnelType,
                    $staffId,
                    $window['start'],
                    $window['end'],
                    null
                );
                if (!$availability['available']) {
                    throw new SchedulingConflict(
                        'The selected ' . ucfirst(strtolower($personnelType)) . ' is no longer available for this time. Please choose another time or personnel.'
                    );
                }
            }

            $insert = $connection->prepare(
                'INSERT INTO appointments
                    (service_request_id, event_id, facility_id, appointment_type, assigned_staff_id,
                     start_datetime, end_datetime, status, notes, created_by_user_id)
                 VALUES (:service_request_id, :event_id, :facility_id, :appointment_type, :assigned_staff_id,
                         :start_datetime, :end_datetime, :status, :notes, :created_by_user_id)'
            );
            $insert->execute([
                'service_request_id' => filter_var($data['service_request_id'] ?? null, FILTER_VALIDATE_INT) ?: null,
                'event_id' => filter_var($data['event_id'] ?? null, FILTER_VALIDATE_INT) ?: null,
                'facility_id' => filter_var($data['facility_id'] ?? null, FILTER_VALIDATE_INT) ?: null,
                'appointment_type' => trim((string) ($data['appointment_type'] ?? 'Appointment')),
                'assigned_staff_id' => filter_var($data['assigned_staff_id'] ?? null, FILTER_VALIDATE_INT) ?: null,
                'start_datetime' => $window['start'],
                'end_datetime' => $window['end'],
                'status' => $status,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => filter_var($data['created_by_user_id'] ?? null, FILTER_VALIDATE_INT) ?: null,
            ]);
            $appointmentId = (int) $connection->lastInsertId();

            $staffInsert = $connection->prepare('INSERT INTO appointment_staff (appointment_id, staff_id) VALUES (:appointment_id, :staff_id)');
            foreach (array_unique(array_filter($staffIds)) as $staffId) {
                $staffInsert->execute(['appointment_id' => $appointmentId, 'staff_id' => $staffId]);
            }
            $connection->commit();

            $assignedStaffIds = array_values(array_unique(array_filter(array_map('intval', $staffIds))));
            $staffNameById = [];
            foreach ($assignedStaffIds as $staffId) {
                $staffName = database()->prepare(
                    'SELECT COALESCE(CONCAT(m.first_name, " ", m.last_name), s.employee_number)
                     FROM staff s
                     LEFT JOIN members m ON m.id = s.member_id
                     WHERE s.id = :id AND s.employment_status = "ACTIVE"
                     LIMIT 1'
                );
                $staffName->execute(['id' => $staffId]);
                $staffNameById[$staffId] = trim((string) $staffName->fetchColumn());
            }

            foreach ($assignedStaffIds as $staffId) {
                $type = trim((string) ($data['appointment_type'] ?? 'Appointment'));
                $message = 'You were assigned to appointment #' . $appointmentId . ' (' . $type . ') for the selected church service.';
                NotificationService::notifyStaffByStaffId((int) $staffId, 'Appointment assignment', $message, 'APPOINTMENT_ASSIGNED', 'appointment', $appointmentId);
            }

            $requestId = filter_var($data['service_request_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
            if ($requestId) {
                $memberQuery = database()->prepare('SELECT requested_by_member_id FROM service_requests WHERE id = :id LIMIT 1');
                $memberQuery->execute(['id' => $requestId]);
                $memberId = filter_var($memberQuery->fetchColumn(), FILTER_VALIDATE_INT);
                if ($memberId) {
                    $staffNames = array_values(array_filter($staffNameById));
                    $personText = $staffNames === [] ? 'the assigned church personnel' : implode(', ', $staffNames);
                    $memberMessage = 'Appointment #' . $appointmentId . ' has been scheduled and assigned to ' . $personText . '.';
                    NotificationService::notifyMemberByMemberId((int) $memberId, 'Appointment update', $memberMessage, 'APPOINTMENT_SCHEDULED', 'service_request', $requestId);
                }
            }

            AuditLog::record(filter_var($data['created_by_user_id'] ?? null, FILTER_VALIDATE_INT) ?: null, 'APPOINTMENT_CREATED', 'APPOINTMENT', $appointmentId);
            return $appointmentId;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) { $connection->rollBack(); }
            throw $exception;
        }
    }

    public static function appointment(int $appointmentId): ?array
    {
        $query = database()->prepare(
            'SELECT a.*, f.name AS facility_name, e.title AS event_title, sr.request_number
             FROM appointments a
             LEFT JOIN facilities f ON f.id = a.facility_id
             LEFT JOIN events e ON e.id = a.event_id
             LEFT JOIN service_requests sr ON sr.id = a.service_request_id
             WHERE a.id = :id LIMIT 1'
        );
        $query->execute(['id' => $appointmentId]);
        $appointment = $query->fetch();
        if (!$appointment) { return null; }
        $staff = database()->prepare(
            'SELECT s.id, COALESCE(CONCAT(m.first_name, " ", m.last_name), s.employee_number) AS staff_name
             FROM appointment_staff aps INNER JOIN staff s ON s.id = aps.staff_id
             LEFT JOIN members m ON m.id = s.member_id
             WHERE aps.appointment_id = :id ORDER BY staff_name'
        );
        $staff->execute(['id' => $appointmentId]);
        $appointment['staff'] = $staff->fetchAll();
        return $appointment;
    }

    public static function appointmentsForStaff(int $staffId): array
    {
        $query = database()->prepare(
            "SELECT DISTINCT a.id, a.appointment_type, a.start_datetime, a.end_datetime,
                    a.status, f.name AS facility_name, e.title AS event_title, sr.request_number
             FROM appointments a
             LEFT JOIN appointment_staff aps ON aps.appointment_id = a.id
             LEFT JOIN facilities f ON f.id = a.facility_id
             LEFT JOIN events e ON e.id = a.event_id
             LEFT JOIN service_requests sr ON sr.id = a.service_request_id
             WHERE a.status <> 'CANCELLED' AND (a.assigned_staff_id = :staff_id OR aps.staff_id = :joined_staff_id)
             ORDER BY a.start_datetime"
        );
        $query->execute(['staff_id' => $staffId, 'joined_staff_id' => $staffId]);
        return $query->fetchAll();
    }

    public static function assignStaffToAppointment(int $appointmentId, int $staffId): void
    {
        $connection = database();
        $connection->beginTransaction();
        try {
            $appointment = self::appointment($appointmentId);
            if (!$appointment || $appointment['status'] === 'CANCELLED') {
                throw new InvalidArgumentException('Appointment is not available for assignment.');
            }
            $data = [
                'facility_id' => $appointment['facility_id'],
                'event_id' => $appointment['event_id'],
                'service_request_id' => $appointment['service_request_id'],
                'assigned_staff_id' => $staffId,
                'start_datetime' => $appointment['start_datetime'],
                'end_datetime' => $appointment['end_datetime'],
                'exclude_appointment_id' => $appointmentId,
            ];
            foreach (self::resources($data) as $resource) {
                $connection->prepare('INSERT IGNORE INTO scheduling_resource_locks (resource_key) VALUES (:resource_key)')->execute(['resource_key' => $resource]);
                $connection->prepare('SELECT resource_key FROM scheduling_resource_locks WHERE resource_key = :resource_key FOR UPDATE')->execute(['resource_key' => $resource]);
            }
            if (self::findConflicts($data) !== []) {
                throw new SchedulingConflict();
            }
            $update = $connection->prepare('UPDATE appointments SET assigned_staff_id = :staff_id WHERE id = :id');
            $update->execute(['staff_id' => $staffId, 'id' => $appointmentId]);
            $link = $connection->prepare('INSERT IGNORE INTO appointment_staff (appointment_id, staff_id) VALUES (:appointment_id, :staff_id)');
            $link->execute(['appointment_id' => $appointmentId, 'staff_id' => $staffId]);
            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) { $connection->rollBack(); }
            throw $exception;
        }
    }

    public static function completeForStaff(int $appointmentId, int $staffId, int $userId): void
    {
        $query = database()->prepare(
            'SELECT a.id, a.status, a.service_request_id
             FROM appointments a
             LEFT JOIN appointment_staff aps ON aps.appointment_id = a.id
             WHERE a.id = :id
               AND (a.assigned_staff_id = :staff_id OR aps.staff_id = :joined_staff_id)
             LIMIT 1'
        );
        $query->execute([
            'id' => $appointmentId,
            'staff_id' => $staffId,
            'joined_staff_id' => $staffId,
        ]);
        $appointment = $query->fetch();
        if (!$appointment) {
            throw new InvalidArgumentException('This appointment is not assigned to you.');
        }
        if ((string) $appointment['status'] === 'CANCELLED') {
            throw new InvalidArgumentException('A cancelled appointment cannot be completed.');
        }
        if ((string) $appointment['status'] !== 'COMPLETED') {
            $update = database()->prepare(
                'UPDATE appointments SET status = "COMPLETED" WHERE id = :id AND status NOT IN ("CANCELLED", "COMPLETED")'
            );
            $update->execute(['id' => $appointmentId]);
            AuditLog::record($userId, 'APPOINTMENT_COMPLETED', 'APPOINTMENT', $appointmentId);
        }

        $requestId = (int) ($appointment['service_request_id'] ?? 0);
        if ($requestId > 0) {
            $assigned = database()->prepare(
                'SELECT sr.status
                 FROM service_assignments sa
                 INNER JOIN service_requests sr ON sr.id = sa.service_request_id
                 WHERE sa.service_request_id = :request_id
                   AND sa.staff_id = :staff_id
                   AND sa.assignment_role = "PASTOR"
                 LIMIT 1'
            );
            $assigned->execute(['request_id' => $requestId, 'staff_id' => $staffId]);
            $status = $assigned->fetchColumn();
            if ($status !== false && !in_array((string) $status, ['COMPLETED', 'REJECTED', 'CANCELLED'], true)) {
                ServiceRequest::updateStatus($requestId, 'COMPLETED', $userId, 'Marked completed by the assigned pastor.');
            }
        }
    }

    public static function cancelAppointment(int $appointmentId, ?int $userId): void
    {
        $query = database()->prepare("UPDATE appointments SET status = 'CANCELLED' WHERE id = :id AND status <> 'CANCELLED'");
        $query->execute(['id' => $appointmentId]);
        if ($query->rowCount() !== 1) { throw new RuntimeException('Appointment not found or already cancelled.'); }
        AuditLog::record($userId, 'APPOINTMENT_CANCELLED', 'APPOINTMENT', $appointmentId);
    }
}
