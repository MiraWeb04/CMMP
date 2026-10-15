<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/SchedulingService.php';

$connection = database();
$suffix = strtoupper(bin2hex(random_bytes(4)));
$facilityId = null;
$staffId = null;
$appointmentIds = [];

try {
    $facility = $connection->prepare('INSERT INTO facilities (name, code, facility_type, facility_status) VALUES (:name, :code, :type, :status)');
    $facility->execute(['name' => 'Scheduling Test Facility ' . $suffix, 'code' => 'SCH-' . $suffix, 'type' => 'CHAPEL', 'status' => 'AVAILABLE']);
    $facilityId = (int) $connection->lastInsertId();

    $staff = $connection->prepare('INSERT INTO staff (employee_number, position_title, employment_status) VALUES (:number, :title, :status)');
    $staff->execute(['number' => 'SCH-' . $suffix, 'title' => 'Scheduling Test Staff', 'status' => 'ACTIVE']);
    $staffId = (int) $connection->lastInsertId();

    $base = strtotime('2030-06-01 09:00:00');
    $first = [
        'facility_id' => $facilityId, 'assigned_staff_id' => $staffId, 'appointment_type' => 'Test appointment',
        'start_datetime' => date('Y-m-d H:i:s', $base), 'end_datetime' => date('Y-m-d H:i:s', $base + 7200),
        'status' => 'CONFIRMED', 'notes' => null, 'created_by_user_id' => null,
    ];
    $appointmentIds[] = SchedulingService::createAppointment($first);

    $boundary = $first;
    $boundary['start_datetime'] = date('Y-m-d H:i:s', $base + 7200);
    $boundary['end_datetime'] = date('Y-m-d H:i:s', $base + 14400);
    if (!SchedulingService::checkAvailability($boundary)['available']) {
        throw new RuntimeException('Boundary appointment was incorrectly marked as a conflict.');
    }
    $appointmentIds[] = SchedulingService::createAppointment($boundary);

    $overlap = $first;
    $overlap['start_datetime'] = date('Y-m-d H:i:s', $base + 3600);
    $overlap['end_datetime'] = date('Y-m-d H:i:s', $base + 10800);
    if (SchedulingService::checkAvailability($overlap)['available']) {
        throw new RuntimeException('Overlapping appointment was incorrectly marked available.');
    }
    try {
        SchedulingService::createAppointment($overlap);
        throw new RuntimeException('Overlapping appointment was created.');
    } catch (SchedulingConflict $expected) {
        if ($expected->getMessage() !== 'The selected schedule is no longer available. Please select another schedule.') {
            throw $expected;
        }
    }

    $staffConflict = $first;
    $staffConflict['facility_id'] = null;
    $staffConflict['start_datetime'] = date('Y-m-d H:i:s', $base + 1800);
    $staffConflict['end_datetime'] = date('Y-m-d H:i:s', $base + 5400);
    if (SchedulingService::checkAvailability($staffConflict)['available']) {
        throw new RuntimeException('Staff overlap was incorrectly marked available.');
    }

    echo "Scheduling overlap smoke test passed.\n";
} finally {
    if ($appointmentIds !== []) {
        $placeholders = implode(',', array_fill(0, count($appointmentIds), '?'));
        $connection->prepare('DELETE FROM appointments WHERE id IN (' . $placeholders . ')')->execute($appointmentIds);
    }
    if ($staffId !== null) { $connection->prepare('DELETE FROM staff WHERE id = :id')->execute(['id' => $staffId]); }
    if ($facilityId !== null) { $connection->prepare('DELETE FROM facilities WHERE id = :id')->execute(['id' => $facilityId]); }
}
