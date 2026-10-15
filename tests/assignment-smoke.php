<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/SchedulingService.php';

$connection = database();
$suffix = strtoupper(bin2hex(random_bytes(4)));
$facilityIds = [];
$staffIds = [];
$appointmentIds = [];

try {
    $facilityInsert = $connection->prepare('INSERT INTO facilities (name, code, facility_type, facility_status) VALUES (:name, :code, :type, :status)');
    foreach (['A', 'B'] as $label) {
        $facilityInsert->execute(['name' => 'Assignment Facility ' . $suffix . '-' . $label, 'code' => 'AS-' . $suffix . '-' . $label, 'type' => 'CHAPEL', 'status' => 'AVAILABLE']);
        $facilityIds[] = (int) $connection->lastInsertId();
    }
    $staffInsert = $connection->prepare('INSERT INTO staff (employee_number, position_title, employment_status) VALUES (:number, :title, :status)');
    foreach (['A', 'B'] as $label) {
        $staffInsert->execute(['number' => 'AS-' . $suffix . '-' . $label, 'title' => 'Assignment Tester', 'status' => 'ACTIVE']);
        $staffIds[] = (int) $connection->lastInsertId();
    }

    $base = strtotime('2030-07-01 09:00:00');
    $appointmentIds[] = SchedulingService::createAppointment([
        'facility_id' => $facilityIds[0], 'assigned_staff_id' => $staffIds[0], 'appointment_type' => 'Existing assignment',
        'start_datetime' => date('Y-m-d H:i:s', $base), 'end_datetime' => date('Y-m-d H:i:s', $base + 7200), 'status' => 'CONFIRMED',
    ]);
    $appointmentIds[] = SchedulingService::createAppointment([
        'facility_id' => $facilityIds[1], 'assigned_staff_id' => $staffIds[1], 'appointment_type' => 'Reassignment target',
        'start_datetime' => date('Y-m-d H:i:s', $base + 3600), 'end_datetime' => date('Y-m-d H:i:s', $base + 10800), 'status' => 'CONFIRMED',
    ]);

    try {
        SchedulingService::assignStaffToAppointment($appointmentIds[1], $staffIds[0]);
        throw new RuntimeException('Conflicting personnel reassignment was accepted.');
    } catch (SchedulingConflict $expected) {
        if ($expected->getMessage() !== 'The selected schedule is no longer available. Please select another schedule.') {
            throw $expected;
        }
    }

    SchedulingService::assignStaffToAppointment($appointmentIds[1], $staffIds[1]);
    echo "Assignment conflict smoke test passed.\n";
} finally {
    if ($appointmentIds !== []) {
        $placeholders = implode(',', array_fill(0, count($appointmentIds), '?'));
        $connection->prepare('DELETE FROM appointments WHERE id IN (' . $placeholders . ')')->execute($appointmentIds);
    }
    foreach ($staffIds as $staffId) { $connection->prepare('DELETE FROM staff WHERE id = :id')->execute(['id' => $staffId]); }
    foreach ($facilityIds as $facilityId) { $connection->prepare('DELETE FROM facilities WHERE id = :id')->execute(['id' => $facilityId]); }
}
