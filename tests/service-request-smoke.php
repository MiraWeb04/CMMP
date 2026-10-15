<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/ServiceRequest.php';
require_once __DIR__ . '/../helpers/service_request.php';
require_once __DIR__ . '/../services/SchedulingService.php';

$connection = database();
$suffix = strtoupper(bin2hex(random_bytes(4)));
$memberId = null;
$requestIds = [];
$workflowRequestIds = [];
$appointmentIds = [];

try {
    $memberInsert = $connection->prepare(
        'INSERT INTO members (member_number, first_name, last_name, membership_status)
         VALUES (:number, :first_name, :last_name, :status)'
    );
    $memberInsert->execute(['number' => 'SR-' . $suffix, 'first_name' => 'Service', 'last_name' => 'Tester', 'status' => 'ACTIVE']);
    $memberId = (int) $connection->lastInsertId();

    $types = ServiceRequest::types();
    $expected = ['WEDDING', 'BAPTISM', 'FUNERAL_SERVICE', 'FACILITY_RESERVATION', 'PRE_MARRIAGE_COUNSELING', 'HOUSE_BLESSING', 'PASTORAL_COUNSELING'];
    $codes = array_column($types, 'code');
    if (array_diff($expected, $codes) !== []) {
        throw new RuntimeException('Service catalog is incomplete.');
    }

    $typeOffset = 0;
    foreach ($types as $type) {
        $data = match ($type['code']) {
            'WEDDING' => ['groom_name' => 'Groom Tester', 'bride_name' => 'Bride Tester', 'contact_number' => '09170000000'],
            'BAPTISM' => ['candidate_name' => 'Applicant Tester', 'contact_number' => '09170000000'],
            'FUNERAL_SERVICE' => ['deceased_name' => 'Deceased Tester', 'date_of_death' => date('Y-m-d'), 'family_contact_name' => 'Family Tester'],
            'FACILITY_RESERVATION' => ['event_purpose' => 'Fellowship gathering'],
            'HOUSE_BLESSING' => ['applicant_name' => 'House Tester', 'house_address' => '123 Test Street', 'house_landmark' => 'Near chapel', 'contact_number' => '09170000000'],
            'PRE_MARRIAGE_COUNSELING' => ['partner_1_name' => 'Partner One', 'partner_2_name' => 'Partner Two', 'contact_number' => '09170000000'],
            'PASTORAL_COUNSELING' => ['applicant_name' => 'Counseling Tester', 'counseling_type' => 'Spiritual'],
            default => ['additional_information' => 'Service request test'],
        };
        $start = date('Y-m-d H:i:s', time() + 86400 + ($typeOffset * 7200));
        $end = date('Y-m-d H:i:s', time() + 86400 + ($typeOffset * 7200) + 3600);
        $typeOffset++;
        $requestId = ServiceRequest::create([
            'service_type_id' => (int) $type['id'],
            'requested_by_member_id' => $memberId,
            'request_number' => 'TEST-' . $suffix . '-' . $type['code'],
            'subject' => $type['name'],
            'description' => $data['additional_information'] ?? null,
            'request_data' => json_encode($data, JSON_THROW_ON_ERROR),
            'preferred_start_datetime' => $start,
            'preferred_end_datetime' => $end,
            'preferred_facility_id' => null,
            'requested_by_member_id_user' => null,
        ], service_request_participants($type['code'], $data, $memberId));
        $requestIds[] = $requestId;
        if (in_array($type['code'], ['BAPTISM', 'WEDDING'], true)) {
            $workflowRequestIds[$type['code']] = $requestId;
        }
    }

    foreach ($requestIds as $requestId) {
        $request = ServiceRequest::find($requestId);
        if (!$request || $request['status'] !== 'PENDING_ASSIGNMENT' || count($request['status_history']) !== 1 || $request['status_history'][0]['new_status'] !== 'PENDING_ASSIGNMENT') {
            throw new RuntimeException('Request status/history validation failed.');
        }
    }

    $adminPaginated = ServiceRequest::paginateForAdmin('', '');
    $adminPaginatedIds = array_column($adminPaginated, 'id');
    foreach ($requestIds as $requestId) {
        if (!in_array($requestId, $adminPaginatedIds, true)) {
            throw new RuntimeException('Admin service request pagination did not surface a newly created request.');
        }
    }

    $adminUserCount = (int) $connection->query("SELECT COUNT(DISTINCT u.id)
        FROM users u
        INNER JOIN user_roles ur ON ur.user_id = u.id
        INNER JOIN roles r ON r.id = ur.role_id
        WHERE u.status = 'ACTIVE' AND r.code IN ('SUPER_ADMIN', 'CHURCH_ADMIN')")
        ->fetchColumn();

    foreach ($requestIds as $requestId) {
        $adminNotificationCheck = $connection->prepare(
            'SELECT COUNT(*) FROM notifications n
             INNER JOIN users u ON u.id = n.user_id
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE n.reference_type = :ref_type
               AND n.reference_id = :ref_id
               AND n.notification_type = :type
               AND r.code IN (\'SUPER_ADMIN\', \'CHURCH_ADMIN\')'
        );
        $adminNotificationCheck->execute([
            'ref_type' => 'service_request',
            'ref_id' => $requestId,
            'type' => 'SERVICE_REQUEST_NEW',
        ]);
        $notificationsForRequest = (int) $adminNotificationCheck->fetchColumn();
        if ($notificationsForRequest < 1 || $notificationsForRequest > $adminUserCount) {
            throw new RuntimeException('Administrative notifications were not created for the new service request.');
        }
    }

    $staff = ServiceRequest::staff();
    if ($staff === []) {
        throw new RuntimeException('No active staff are available for workflow assignment.');
    }
    $staffId = (int) $staff[0]['id'];

    foreach ($workflowRequestIds as $code => $requestId) {
        ServiceRequest::updateStatus($requestId, 'UNDER_REVIEW', null, 'Review started.');
        $reviewed = ServiceRequest::find($requestId);
        if ($reviewed['status'] !== 'UNDER_REVIEW' || count($reviewed['status_history']) < 2) {
            throw new RuntimeException('Status transition history failed for ' . $code . '.');
        }

        $role = 'PASTOR';
        ServiceRequest::assign($requestId, $staffId, $role, null);
        $assigned = ServiceRequest::find($requestId);
        if (($assigned['status'] ?? '') !== 'ASSIGNED') {
            throw new RuntimeException('Assignment did not move ' . $code . ' to ASSIGNED.');
        }

        $assignmentCheck = $connection->prepare(
            'SELECT COUNT(*) FROM service_assignments WHERE service_request_id = :request_id AND staff_id = :staff_id AND assignment_role = :role'
        );
        $assignmentCheck->execute(['request_id' => $requestId, 'staff_id' => $staffId, 'role' => $role]);
        if ((int) $assignmentCheck->fetchColumn() !== 1) {
            throw new RuntimeException('Assignment persistence failed for ' . $code . '.');
        }

        $appointmentShift = $code === 'WEDDING' ? 0 : 10800;
        $appointmentId = SchedulingService::createAppointment([
            'service_request_id' => $requestId,
            'event_id' => null,
            'facility_id' => null,
            'appointment_type' => 'SERVICE_REQUEST',
            'assigned_staff_id' => $staffId,
            'start_datetime' => date('Y-m-d H:i:s', strtotime('+21 days 09:00:00') + $appointmentShift),
            'end_datetime' => date('Y-m-d H:i:s', strtotime('+21 days 10:00:00') + $appointmentShift),
            'status' => 'CONFIRMED',
            'notes' => 'Smoke-test schedule for ' . $code,
            'created_by_user_id' => null,
        ]);
        $appointment = SchedulingService::appointment((int) $appointmentId);
        if (!$appointment || (int) $appointment['service_request_id'] !== $requestId) {
            throw new RuntimeException('Appointment scheduling failed for ' . $code . '.');
        }
        $appointmentIds[] = (int) $appointmentId;

        ServiceRequest::updateStatus($requestId, 'SCHEDULED', null, 'Scheduled for service delivery.');
        $scheduled = ServiceRequest::find($requestId);
        if ($scheduled['status'] !== 'SCHEDULED') {
            throw new RuntimeException('Status did not advance to SCHEDULED for ' . $code . '.');
        }
    }

    echo "All service type request smoke tests passed.\n";
} finally {
    if ($appointmentIds !== []) {
        $placeholders = implode(',', array_fill(0, count($appointmentIds), '?'));
        $deleteAppointments = $connection->prepare('DELETE FROM appointments WHERE id IN (' . $placeholders . ')');
        $deleteAppointments->execute($appointmentIds);
    }
    if ($requestIds !== []) {
        $placeholders = implode(',', array_fill(0, count($requestIds), '?'));
        $delete = $connection->prepare('DELETE FROM service_requests WHERE id IN (' . $placeholders . ')');
        $delete->execute($requestIds);
    }
    if ($memberId !== null) {
        $deleteMember = $connection->prepare('DELETE FROM members WHERE id = :id');
        $deleteMember->execute(['id' => $memberId]);
    }
}
