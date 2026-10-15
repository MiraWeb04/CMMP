<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/Counseling.php';
require_once __DIR__ . '/../models/ServiceRequest.php';

$connection = database();
$suffix = strtoupper(bin2hex(random_bytes(4)));
$memberId = null;
$staffId = null;
$requestId = null;
$caseId = null;
$appointmentIds = [];

try {
    $memberInsert = $connection->prepare('INSERT INTO members (member_number, first_name, last_name, membership_status) VALUES (:number, :first_name, :last_name, :status)');
    $memberInsert->execute(['number' => 'COUN-' . $suffix, 'first_name' => 'Counseling', 'last_name' => 'Member', 'status' => 'ACTIVE']);
    $memberId = (int) $connection->lastInsertId();

    $staffInsert = $connection->prepare('INSERT INTO staff (employee_number, position_title, employment_status) VALUES (:number, :title, :status)');
    $staffInsert->execute(['number' => 'COUN-' . $suffix, 'title' => 'Test Counselor', 'status' => 'ACTIVE']);
    $staffId = (int) $connection->lastInsertId();
    $connection->prepare('INSERT INTO counselors (staff_id, specialization) VALUES (:staff_id, :specialization)')->execute(['staff_id' => $staffId, 'specialization' => 'Pre-marriage counseling']);

    $weddingType = (int) $connection->query("SELECT id FROM service_types WHERE code = 'WEDDING' LIMIT 1")->fetchColumn();
    $requestId = ServiceRequest::create([
        'service_type_id' => $weddingType,
        'requested_by_member_id' => $memberId,
        'request_number' => 'COUN-REQ-' . $suffix,
        'subject' => 'Wedding counseling test',
        'description' => null,
        'request_data' => json_encode(['groom_name' => 'Groom', 'bride_name' => 'Bride'], JSON_THROW_ON_ERROR),
        'preferred_start_datetime' => '2031-01-01 09:00:00',
        'preferred_end_datetime' => '2031-01-01 11:00:00',
        'preferred_facility_id' => null,
        'requested_by_member_id_user' => null,
    ]);
    ServiceRequest::assign($requestId, $staffId, 'COUNSELOR', null);
    $caseId = Counseling::createCase($requestId, 4);
    $case = Counseling::findCase($caseId);
    if (!$case || (int) $case['required_sessions'] !== 4 || $case['status'] !== 'NOT_STARTED') {
        throw new RuntimeException('Configurable counseling case failed.');
    }

    $sessionId = Counseling::scheduleSession($caseId, 1, $staffId, '2031-01-02 09:00:00', '2031-01-02 10:00:00', null, 0);
    $case = Counseling::findCase($caseId);
    $appointmentIds[] = (int) $case['sessions'][0]['appointment_id'];
    if ($case['status'] !== 'IN_PROGRESS' || $case['sessions'][0]['status'] !== 'PLANNED') {
        throw new RuntimeException('Counseling session scheduling failed.');
    }

    Counseling::completeSession($sessionId, $staffId, 'Private counselor note.');
    $case = Counseling::findCase($caseId);
    if ($case['sessions'][0]['status'] !== 'COMPLETED' || $case['status'] !== 'IN_PROGRESS') {
        throw new RuntimeException('Partial counseling completion failed.');
    }
    if (array_key_exists('private_notes', $case['sessions'][0])) {
        throw new RuntimeException('Private notes leaked from the member-safe case view.');
    }

    if (count(Counseling::casesForCounselor($staffId)) !== 1) {
        throw new RuntimeException('Counselor assignment lookup failed.');
    }
    echo "Counseling workflow smoke test passed.\n";
} finally {
    if ($appointmentIds !== []) {
        $placeholders = implode(',', array_fill(0, count($appointmentIds), '?'));
        $connection->prepare('DELETE FROM appointments WHERE id IN (' . $placeholders . ')')->execute($appointmentIds);
    }
    if ($caseId !== null) { $connection->prepare('DELETE FROM counseling_cases WHERE id = :id')->execute(['id' => $caseId]); }
    if ($requestId !== null) { $connection->prepare('DELETE FROM service_requests WHERE id = :id')->execute(['id' => $requestId]); }
    if ($staffId !== null) { $connection->prepare('DELETE FROM counselors WHERE staff_id = :id')->execute(['id' => $staffId]); $connection->prepare('DELETE FROM staff WHERE id = :id')->execute(['id' => $staffId]); }
    if ($memberId !== null) { $connection->prepare('DELETE FROM members WHERE id = :id')->execute(['id' => $memberId]); }
}
