<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/SchedulingService.php';

final class Counseling
{
    public static function counselors(): array
    {
        $query = database()->query(
            "SELECT s.id, COALESCE(CONCAT(m.first_name, ' ', m.last_name), s.employee_number) AS display_name,
                    s.position_title
             FROM counselors c INNER JOIN staff s ON s.id = c.staff_id
             LEFT JOIN members m ON m.id = s.member_id
             WHERE s.employment_status = 'ACTIVE' ORDER BY display_name"
        );
        return $query->fetchAll();
    }

    public static function createCase(int $serviceRequestId, int $requiredSessions): int
    {
        if ($requiredSessions < 1 || $requiredSessions > 100) {
            throw new InvalidArgumentException('Required sessions must be between 1 and 100.');
        }
        $type = database()->prepare(
            "SELECT st.code FROM service_requests sr INNER JOIN service_types st ON st.id = sr.service_type_id WHERE sr.id = :id"
        );
        $type->execute(['id' => $serviceRequestId]);
        if (!in_array($type->fetchColumn(), ['WEDDING', 'PRE_MARRIAGE_COUNSELING'], true)) {
            throw new InvalidArgumentException('Counseling can only be started for a wedding or pre-marriage request.');
        }
        $query = database()->prepare('INSERT INTO counseling_cases (service_request_id, required_sessions) VALUES (:request_id, :required_sessions)');
        $query->execute(['request_id' => $serviceRequestId, 'required_sessions' => $requiredSessions]);
        return (int) database()->lastInsertId();
    }

    public static function findCase(int $caseId): ?array
    {
        $query = database()->prepare(
            'SELECT cc.*, sr.request_number, sr.requested_by_member_id, sr.subject, sr.status AS request_status,
                    sr.preferred_start_datetime, sr.preferred_end_datetime, sr.request_data,
                    CONCAT(m.first_name, " ", m.last_name) AS member_name,
                    st.name AS service_name, st.code AS service_code, f.name AS preferred_facility_name
             FROM counseling_cases cc INNER JOIN service_requests sr ON sr.id = cc.service_request_id
             INNER JOIN members m ON m.id = sr.requested_by_member_id
             INNER JOIN service_types st ON st.id = sr.service_type_id
             LEFT JOIN facilities f ON f.id = sr.preferred_facility_id
             WHERE cc.id = :id LIMIT 1'
        );
        $query->execute(['id' => $caseId]);
        $case = $query->fetch();
        if (!$case) { return null; }
        $case['request_data'] = $case['request_data'] ? json_decode((string) $case['request_data'], true) : [];
        if (!is_array($case['request_data'])) {
            $case['request_data'] = [];
        }
        $sessions = database()->prepare(
            'SELECT cs.id, cs.session_number, cs.appointment_id, cs.status, cs.completed_at,
                    a.start_datetime, a.end_datetime, a.status AS appointment_status, f.name AS facility_name
             FROM counseling_sessions cs LEFT JOIN appointments a ON a.id = cs.appointment_id
             LEFT JOIN facilities f ON f.id = a.facility_id
             WHERE cs.counseling_case_id = :case_id ORDER BY cs.session_number'
        );
        $sessions->execute(['case_id' => $caseId]);
        $case['sessions'] = $sessions->fetchAll();
        $counselor = database()->prepare(
            'SELECT s.id, COALESCE(CONCAT(m.first_name, " ", m.last_name), s.employee_number) AS display_name
             FROM service_assignments sa INNER JOIN staff s ON s.id = sa.staff_id
             LEFT JOIN members m ON m.id = s.member_id INNER JOIN counselors c ON c.staff_id = s.id
             WHERE sa.service_request_id = :request_id AND sa.assignment_role = "COUNSELOR" LIMIT 1'
        );
        $counselor->execute(['request_id' => $case['service_request_id']]);
        $case['counselor'] = $counselor->fetch() ?: null;
        return $case;
    }

    public static function casesForCounselor(int $staffId): array
    {
        $query = database()->prepare(
            'SELECT cc.id, cc.status, cc.required_sessions, sr.request_number, sr.subject, sr.status AS request_status,
                    CONCAT(m.first_name, " ", m.last_name) AS couple_name
             FROM counseling_cases cc INNER JOIN service_requests sr ON sr.id = cc.service_request_id
             INNER JOIN members m ON m.id = sr.requested_by_member_id
             INNER JOIN service_assignments sa ON sa.service_request_id = sr.id AND sa.staff_id = :staff_id AND sa.assignment_role = "COUNSELOR"
             ORDER BY cc.updated_at DESC'
        );
        $query->execute(['staff_id' => $staffId]);
        return $query->fetchAll();
    }

    public static function caseForMember(int $caseId, int $memberId): ?array
    {
        $case = self::findCase($caseId);
        return $case && (int) $case['requested_by_member_id'] === $memberId ? $case : null;
    }

    public static function syncConfirmedRequest(int $serviceRequestId, int $userId): void
    {
        $query = database()->prepare(
            'SELECT sr.id, sr.status, sr.preferred_start_datetime, sr.preferred_end_datetime,
                    sr.preferred_facility_id, sr.request_data, st.code AS service_code
             FROM service_requests sr
             INNER JOIN service_types st ON st.id = sr.service_type_id
             WHERE sr.id = :id
             LIMIT 1'
        );
        $query->execute(['id' => $serviceRequestId]);
        $request = $query->fetch();
        if (!$request || !in_array((string) $request['service_code'], ['WEDDING', 'PRE_MARRIAGE_COUNSELING'], true)) {
            return;
        }
        if (!in_array((string) $request['status'], ['APPROVED', 'SCHEDULED', 'CONFIRMED'], true)) {
            return;
        }
        $start = (string) ($request['preferred_start_datetime'] ?? '');
        $end = (string) ($request['preferred_end_datetime'] ?? '');
        if ($start === '' || $end === '') {
            return;
        }
        $details = $request['request_data'] ? json_decode((string) $request['request_data'], true) : [];
        if (is_array($details) && strcasecmp((string) ($details['first_counseling_session'] ?? 'Yes'), 'No') === 0) {
            return;
        }

        $case = database()->prepare('SELECT id FROM counseling_cases WHERE service_request_id = :id LIMIT 1');
        $case->execute(['id' => $serviceRequestId]);
        $caseId = (int) $case->fetchColumn();
        if ($caseId === 0) {
            $caseId = self::createCase($serviceRequestId, 3);
        }

        $counselor = database()->prepare(
            'SELECT staff_id FROM service_assignments
             WHERE service_request_id = :id AND assignment_role = "COUNSELOR"
             LIMIT 1'
        );
        $counselor->execute(['id' => $serviceRequestId]);
        $staffId = (int) $counselor->fetchColumn();
        if ($staffId === 0) {
            return;
        }

        $existing = database()->prepare(
            'SELECT id FROM counseling_sessions
             WHERE counseling_case_id = :case_id AND session_number = 1 AND status <> "CANCELLED"
             LIMIT 1'
        );
        $existing->execute(['case_id' => $caseId]);
        if ($existing->fetchColumn()) {
            return;
        }

        $facilityId = filter_var($request['preferred_facility_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        self::scheduleSession($caseId, 1, $staffId, $start, $end, $facilityId, $userId);
    }

    public static function scheduleSession(int $caseId, int $sessionNumber, int $counselorStaffId, string $start, string $end, ?int $facilityId, int $userId): int
    {
        $case = self::findCase($caseId);
        if (!$case || $sessionNumber < 1 || $sessionNumber > (int) $case['required_sessions']) { throw new InvalidArgumentException('Invalid counseling session number.'); }
        foreach ($case['sessions'] as $session) {
            if ((int) $session['session_number'] === $sessionNumber && $session['status'] !== 'CANCELLED') {
                throw new InvalidArgumentException('That counseling session number is already scheduled.');
            }
        }
        $isCounselor = database()->prepare('SELECT COUNT(*) FROM counselors WHERE staff_id = :staff_id');
        $isCounselor->execute(['staff_id' => $counselorStaffId]);
        if ((int) $isCounselor->fetchColumn() !== 1) { throw new InvalidArgumentException('Selected staff member is not a counselor.'); }
        $appointmentId = SchedulingService::createAppointment([
            'service_request_id' => $case['service_request_id'], 'facility_id' => $facilityId,
            'appointment_type' => 'Pre-marriage counseling session ' . $sessionNumber,
            'assigned_staff_id' => $counselorStaffId, 'start_datetime' => $start, 'end_datetime' => $end,
            'status' => 'CONFIRMED', 'created_by_user_id' => $userId,
        ]);
        $query = database()->prepare('INSERT INTO counseling_sessions (counseling_case_id, session_number, appointment_id) VALUES (:case_id, :number, :appointment_id)');
        $query->execute(['case_id' => $caseId, 'number' => $sessionNumber, 'appointment_id' => $appointmentId]);
        $sessionId = (int) database()->lastInsertId();
        database()->prepare("UPDATE counseling_cases SET status = 'IN_PROGRESS' WHERE id = :id AND status = 'NOT_STARTED'")->execute(['id' => $caseId]);
        return $sessionId;
    }

    public static function completeSession(int $sessionId, int $staffId, string $notes, ?int $userId = null): void
    {
        $query = database()->prepare(
            'SELECT cs.id, cs.counseling_case_id, cs.appointment_id, cc.required_sessions, cc.service_request_id
             FROM counseling_sessions cs INNER JOIN counseling_cases cc ON cc.id = cs.counseling_case_id
             INNER JOIN service_assignments sa ON sa.service_request_id = cc.service_request_id AND sa.staff_id = :staff_id AND sa.assignment_role = "COUNSELOR"
             WHERE cs.id = :session_id LIMIT 1'
        );
        $query->execute(['staff_id' => $staffId, 'session_id' => $sessionId]);
        $session = $query->fetch();
        if (!$session) { throw new RuntimeException('Counseling session is not assigned to you.'); }
        database()->prepare("UPDATE counseling_sessions SET status = 'COMPLETED', private_notes = :notes, completed_at = COALESCE(completed_at, NOW()) WHERE id = :id")->execute(['notes' => $notes, 'id' => $sessionId]);
        self::closeFinishedRequest(
            (int) $session['counseling_case_id'],
            (int) $session['service_request_id'],
            (int) ($session['appointment_id'] ?? 0),
            (int) $session['required_sessions'],
            $userId
        );
    }

    private static function closeFinishedRequest(int $caseId, int $serviceRequestId, int $appointmentId, int $requiredSessions, ?int $userId): void
    {
        if ($appointmentId > 0) {
            database()->prepare('UPDATE appointments SET status = "COMPLETED" WHERE id = :id AND status NOT IN ("CANCELLED", "COMPLETED")')->execute(['id' => $appointmentId]);
        }

        $counts = database()->prepare(
            'SELECT
                SUM(status = "COMPLETED") AS completed_count,
                SUM(status = "PLANNED") AS planned_count
             FROM counseling_sessions
             WHERE counseling_case_id = :case_id AND status <> "CANCELLED"'
        );
        $counts->execute(['case_id' => $caseId]);
        $row = $counts->fetch() ?: ['completed_count' => 0, 'planned_count' => 0];
        $finished = (int) $row['planned_count'] === 0 && (int) $row['completed_count'] > 0;
        $caseStatus = $finished || (int) $row['completed_count'] >= $requiredSessions ? 'COMPLETED' : 'IN_PROGRESS';
        database()->prepare('UPDATE counseling_cases SET status = :status WHERE id = :id')->execute(['status' => $caseStatus, 'id' => $caseId]);

        if ($caseStatus !== 'COMPLETED') {
            return;
        }

        $current = database()->prepare('SELECT status FROM service_requests WHERE id = :id LIMIT 1');
        $current->execute(['id' => $serviceRequestId]);
        $requestStatus = (string) $current->fetchColumn();
        if (in_array($requestStatus, ['COMPLETED', 'REJECTED', 'CANCELLED'], true)) {
            return;
        }

        ServiceRequest::updateStatus($serviceRequestId, 'COMPLETED', $userId, 'Marked completed by the assigned counselor.');
    }
}
