<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/NotificationService.php';
require_once __DIR__ . '/Notification.php';
require_once __DIR__ . '/AuditLog.php';
require_once __DIR__ . '/ChurchCalendar.php';

final class ServiceRequest
{
    public static function types(): array
    {
        return database()->query('SELECT id, code, name, description FROM service_types WHERE is_active = 1 AND code NOT IN ("SUNDAY_WORSHIP", "OTHER") ORDER BY name')->fetchAll();
    }

    public static function facilities(): array
    {
        return database()->query("SELECT id, name, code FROM facilities WHERE facility_status = 'AVAILABLE' ORDER BY name")->fetchAll();
    }

    public static function staff(): array
    {
        $query = database()->query(
                        "SELECT DISTINCT s.id, s.employee_number, s.position_title,
                                        COALESCE(CONCAT(m.first_name, ' ', m.last_name), u.username, s.employee_number) AS display_name,
                                        u.username, u.email, u.role AS personnel_type
                         FROM users u
                         LEFT JOIN members m ON m.user_id = u.id
                         INNER JOIN staff s ON s.user_id = u.id OR s.member_id = m.id
                         WHERE s.employment_status = 'ACTIVE'
                             AND u.status = 'ACTIVE'
                             AND u.role IN ('PASTOR', 'COUNSELOR')
                         ORDER BY display_name"
        );
        return $query->fetchAll();
    }

    public static function memberIdForUser(int $userId): ?int
    {
        $query = database()->prepare('SELECT id FROM members WHERE user_id = :user_id LIMIT 1');
        $query->execute(['user_id' => $userId]);
        $id = $query->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public static function create(array $data, array $participants = [], array $documents = []): int
    {
        $connection = database();
        $connection->beginTransaction();
        try {
            $historyUserId = $data['requested_by_member_id_user'] ?? null;
            unset($data['requested_by_member_id_user']);
            ChurchCalendar::lockDateRange($connection, (string) $data['preferred_start_datetime'], (string) $data['preferred_end_datetime']);
            
            // Get service code for appropriate error messaging
            $serviceCode = null;
            if (isset($data['service_type_id'])) {
                $typeQuery = $connection->prepare('SELECT code FROM service_types WHERE id = :id LIMIT 1');
                $typeQuery->execute(['id' => $data['service_type_id']]);
                $serviceCode = $typeQuery->fetchColumn() ?: null;
                if (in_array((string) $serviceCode, ['SUNDAY_WORSHIP', 'OTHER'], true)) {
                    throw new InvalidArgumentException('Sunday Worship and Other are no longer available service requests.');
                }
                if (in_array((string) $serviceCode, ['PRE_MARRIAGE_COUNSELING', 'PASTORAL_COUNSELING'], true)) {
                    require_once __DIR__ . '/Facility.php';
                    $data['preferred_facility_id'] = (int) Facility::counselingOffice()['id'];
                }
            }
            
            ChurchCalendar::assertAvailable(
                (string) $data['preferred_start_datetime'],
                (string) $data['preferred_end_datetime'],
                filter_var($data['preferred_facility_id'] ?? null, FILTER_VALIDATE_INT) ?: null,
                null,
                null,
                $serviceCode,
                false
            );
            if (trim((string) ($data['request_number'] ?? '')) === '') {
                $data['request_number'] = self::nextRequestNumber($connection);
            }
            $insert = $connection->prepare(
                'INSERT INTO service_requests
                    (service_type_id, requested_by_member_id, request_number, status, subject, description,
                     request_data, preferred_start_datetime, preferred_end_datetime, preferred_facility_id)
                 VALUES
                    (:service_type_id, :requested_by_member_id, :request_number, "PENDING_ASSIGNMENT", :subject, :description,
                     :request_data, :preferred_start_datetime, :preferred_end_datetime, :preferred_facility_id)'
            );
            $insert->execute($data);
            $requestId = (int) $connection->lastInsertId();

            $history = $connection->prepare(
                'INSERT INTO service_status_history
                    (service_request_id, old_status, new_status, changed_by_user_id, change_note)
                 VALUES (:request_id, NULL, "PENDING_ASSIGNMENT", :user_id, :note)'
            );
            $history->execute(['request_id' => $requestId, 'user_id' => $historyUserId, 'note' => 'Request submitted by member. Pending assignment.']);

            $participantInsert = $connection->prepare(
                'INSERT INTO service_request_participants
                    (service_request_id, member_id, participant_role, participant_name, contact_phone, contact_email)
                 VALUES (:request_id, :member_id, :role, :name, :phone, :email)'
            );
            foreach ($participants as $participant) {
                $participantInsert->execute([
                    'request_id' => $requestId,
                    'member_id' => $participant['member_id'] ?? null,
                    'role' => $participant['role'],
                    'name' => $participant['name'],
                    'phone' => $participant['phone'] ?? null,
                    'email' => $participant['email'] ?? null,
                ]);
            }

            $documentInsert = $connection->prepare(
                'INSERT INTO service_documents
                    (service_request_id, uploaded_by_user_id, document_type, original_filename,
                     stored_filename, storage_path, mime_type, file_size_bytes, is_private)
                 VALUES (:request_id, :user_id, :document_type, :original_filename,
                         :stored_filename, :storage_path, :mime_type, :file_size_bytes, 1)'
            );
            foreach ($documents as $document) {
                $documentInsert->execute([
                    'request_id' => $requestId,
                    'user_id' => $document['user_id'],
                    'document_type' => $document['document_type'],
                    'original_filename' => $document['original_filename'],
                    'stored_filename' => $document['stored_filename'],
                    'storage_path' => $document['storage_path'],
                    'mime_type' => $document['mime_type'],
                    'file_size_bytes' => $document['file_size_bytes'],
                ]);
            }
            $connection->commit();

            try {
                $serviceName = database()->prepare('SELECT name FROM service_types WHERE id = :id LIMIT 1');
                $serviceName->execute(['id' => (int) $data['service_type_id']]);
                $serviceTypeName = (string) $serviceName->fetchColumn();
                if ($serviceTypeName === '') { $serviceTypeName = 'church service'; }

                $memberName = database()->prepare('SELECT CONCAT(first_name, " ", last_name) FROM members WHERE id = :id LIMIT 1');
                $memberName->execute(['id' => (int) $data['requested_by_member_id']]);
                $memberNameText = trim((string) $memberName->fetchColumn());
                if ($memberNameText === '') { $memberNameText = 'A member'; }

                Notification::notifyMembersByMemberId(
                    (int) $data['requested_by_member_id'],
                    'Request received',
                    'Your request has been received. Church staff will review your requested schedule and assign the appropriate pastor or counselor.',
                    'SERVICE_REQUEST_SUBMITTED',
                    'service_request',
                    $requestId
                );

                $adminTitle = 'New Church Service Request';
                $adminMessage = 'New ' . $serviceTypeName . ' service request submitted by ' . $memberNameText . ' is pending assignment.';
                NotificationService::notifyUsersByRole(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF'], $adminTitle, $adminMessage, 'SERVICE_REQUEST_NEW', 'service_request', $requestId);
                AuditLog::record($historyUserId, 'SERVICE_REQUEST_CREATED', 'SERVICE_REQUEST', $requestId);
            } catch (Throwable $notificationException) {
                error_log('CMMP service request admin notification failed: ' . $notificationException->getMessage());
            }

            return $requestId;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) { $connection->rollBack(); }
            throw $exception;
        }
    }

    public static function paginateForMember(int $memberId): array
    {
        $query = database()->prepare(
            'SELECT sr.id, sr.request_number, sr.subject, sr.status, sr.requested_at,
                    sr.requested_by_member_id, sr.preferred_start_datetime, sr.preferred_end_datetime,
                    sr.completed_at, sr.hidden_from_member_at, st.name AS service_name
             FROM service_requests sr
             INNER JOIN service_types st ON st.id = sr.service_type_id
             WHERE sr.requested_by_member_id = :member_id
               AND sr.hidden_from_member_at IS NULL
             ORDER BY sr.created_at DESC'
        );
        $query->execute(['member_id' => $memberId]);
        return $query->fetchAll();
    }

    public static function memberCanHide(array $request, int $memberId): bool
    {
        if ((int) ($request['requested_by_member_id'] ?? 0) !== $memberId) {
            return false;
        }
        if (!empty($request['hidden_from_member_at'])) {
            return false;
        }
        if (($request['status'] ?? '') !== 'COMPLETED') {
            return false;
        }

        $occurredAt = $request['preferred_end_datetime']
            ?: ($request['preferred_start_datetime'] ?? null)
            ?: ($request['completed_at'] ?? null);
        if ($occurredAt === null || trim((string) $occurredAt) === '') {
            return true;
        }

        $timestamp = strtotime((string) $occurredAt);
        return $timestamp !== false && $timestamp <= time();
    }

    public static function hideFromMember(int $requestId, int $memberId, ?int $userId = null): void
    {
        $query = database()->prepare(
            'SELECT id, requested_by_member_id, status, preferred_start_datetime,
                    preferred_end_datetime, completed_at, hidden_from_member_at
             FROM service_requests
             WHERE id = :id
             LIMIT 1'
        );
        $query->execute(['id' => $requestId]);
        $request = $query->fetch();
        if (!$request) {
            throw new InvalidArgumentException('Service request not found.');
        }
        if (!self::memberCanHide($request, $memberId)) {
            throw new InvalidArgumentException(
                'Only your own completed requests that have already taken place can be removed from your dashboard.'
            );
        }

        $update = database()->prepare(
            'UPDATE service_requests
             SET hidden_from_member_at = NOW()
             WHERE id = :id
               AND requested_by_member_id = :member_id
               AND status = "COMPLETED"
               AND hidden_from_member_at IS NULL'
        );
        $update->execute(['id' => $requestId, 'member_id' => $memberId]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('The request could not be removed from your dashboard.');
        }

        if ($userId) {
            AuditLog::record($userId, 'SERVICE_REQUEST_HIDDEN_BY_MEMBER', 'SERVICE_REQUEST', $requestId);
        }
    }

    public static function paginateForAdmin(string $search, string $status): array
    {
        $conditions = [];
        $parameters = [];
        if ($search !== '') {
            $term = '%' . $search . '%';
            $conditions[] = '(sr.request_number LIKE :search_number OR sr.subject LIKE :search_subject OR CONCAT(COALESCE(m.first_name, ""), " ", COALESCE(m.last_name, "")) LIKE :search_name)';
            $parameters['search_number'] = $term;
            $parameters['search_subject'] = $term;
            $parameters['search_name'] = $term;
        }
        if ($status !== '') {
            $conditions[] = 'sr.status = :status';
            $parameters['status'] = $status;
        }
        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);
        $query = database()->prepare(
            "SELECT sr.id, sr.request_number, sr.subject, sr.status, sr.requested_at,
                    COALESCE(st.name, 'Unknown Service') AS service_name,
                    COALESCE(CONCAT(m.first_name, ' ', m.last_name), 'Unknown Member') AS member_name
             FROM service_requests sr
             LEFT JOIN service_types st ON st.id = sr.service_type_id
             LEFT JOIN members m ON m.id = sr.requested_by_member_id
             $where ORDER BY sr.created_at DESC"
        );
        $query->execute($parameters);
        return $query->fetchAll();
    }

    public static function paginateForAssignedStaff(int $staffId, string $search = '', string $status = ''): array
    {
        $conditions = ['sa.staff_id = :staff_id'];
        $parameters = ['staff_id' => $staffId];
        if ($search !== '') {
            $conditions[] = '(sr.request_number LIKE :search OR sr.subject LIKE :search OR CONCAT(COALESCE(m.first_name, ""), " ", COALESCE(m.last_name, "")) LIKE :search)';
            $parameters['search'] = '%' . $search . '%';
        }
        if ($status !== '') {
            $conditions[] = 'sr.status = :status';
            $parameters['status'] = $status;
        }
        $where = 'WHERE ' . implode(' AND ', $conditions);
        $query = database()->prepare(
            "SELECT sr.id, sr.request_number, sr.subject, sr.status, sr.requested_at,
                    COALESCE(st.name, 'Unknown Service') AS service_name,
                    COALESCE(CONCAT(m.first_name, ' ', m.last_name), 'Unknown Member') AS member_name,
                    sa.assignment_role
             FROM service_assignments sa
             INNER JOIN service_requests sr ON sr.id = sa.service_request_id
             LEFT JOIN service_types st ON st.id = sr.service_type_id
             LEFT JOIN members m ON m.id = sr.requested_by_member_id
             $where
             ORDER BY sr.created_at DESC"
        );
        $query->execute($parameters);

        return $query->fetchAll();
    }

    public static function isAssignedToStaff(int $requestId, int $staffId): bool
    {
        $query = database()->prepare(
            'SELECT 1 FROM service_assignments WHERE service_request_id = :request_id AND staff_id = :staff_id LIMIT 1'
        );
        $query->execute(['request_id' => $requestId, 'staff_id' => $staffId]);

        return (bool) $query->fetchColumn();
    }

    public static function find(int $id): ?array
    {
        $query = database()->prepare(
            'SELECT sr.*, st.code AS service_code, COALESCE(st.name, "Unknown Service") AS service_name,
                    COALESCE(CONCAT(m.first_name, " ", m.last_name), "Unknown Member") AS member_name,
                    f.name AS preferred_facility_name
             FROM service_requests sr
             LEFT JOIN service_types st ON st.id = sr.service_type_id
             LEFT JOIN members m ON m.id = sr.requested_by_member_id
             LEFT JOIN facilities f ON f.id = sr.preferred_facility_id
             WHERE sr.id = :id LIMIT 1'
        );
        $query->execute(['id' => $id]);
        $request = $query->fetch();
        if (!$request) { return null; }
        $request['request_data'] = $request['request_data'] ? json_decode($request['request_data'], true) : [];

        $participants = database()->prepare('SELECT * FROM service_request_participants WHERE service_request_id = :id ORDER BY id');
        $participants->execute(['id' => $id]);
        $request['participants'] = $participants->fetchAll();

        $documents = database()->prepare('SELECT * FROM service_documents WHERE service_request_id = :id ORDER BY created_at');
        $documents->execute(['id' => $id]);
        $request['documents'] = $documents->fetchAll();

        $assignments = database()->prepare(
                'SELECT sa.*,
                    COALESCE(CONCAT(m.first_name, " ", m.last_name), u.username, s.employee_number) AS staff_name,
                    u.username AS staff_username, u.email AS staff_email
             FROM service_assignments sa INNER JOIN staff s ON s.id = sa.staff_id
             LEFT JOIN members m ON m.id = s.member_id
                 LEFT JOIN users u ON u.id = COALESCE(s.user_id, m.user_id)
             WHERE sa.service_request_id = :id ORDER BY sa.assignment_role, staff_name'
        );
        $assignments->execute(['id' => $id]);
        $request['assignments'] = $assignments->fetchAll();

        $history = database()->prepare('SELECT ssh.*, u.username FROM service_status_history ssh LEFT JOIN users u ON u.id = ssh.changed_by_user_id WHERE ssh.service_request_id = :id ORDER BY ssh.created_at DESC');
        $history->execute(['id' => $id]);
        $request['status_history'] = $history->fetchAll();
        return $request;
    }

    public static function updateStatus(int $id, string $status, ?int $userId, ?string $note): void
    {
        $connection = database();
        $connection->beginTransaction();
        try {
            $current = $connection->prepare('SELECT status FROM service_requests WHERE id = :id FOR UPDATE');
            $current->execute(['id' => $id]);
            $oldStatus = $current->fetchColumn();
            if ($oldStatus === false) { throw new RuntimeException('Service request not found.'); }
            if ($oldStatus === $status) { $connection->commit(); return; }
            if (in_array($status, ['APPROVED', 'SCHEDULED', 'CONFIRMED', 'ASSIGNED'], true)) {
                $schedule = $connection->prepare('SELECT sr.preferred_start_datetime, sr.preferred_end_datetime, sr.preferred_facility_id, st.code as service_code 
                                                 FROM service_requests sr 
                                                 JOIN service_types st ON sr.service_type_id = st.id 
                                                 WHERE sr.id = :id LIMIT 1');
                $schedule->execute(['id' => $id]);
                $scheduleData = $schedule->fetch();
                if ($scheduleData && $scheduleData['preferred_start_datetime'] && $scheduleData['preferred_end_datetime']) {
                    ChurchCalendar::lockDateRange($connection, (string) $scheduleData['preferred_start_datetime'], (string) $scheduleData['preferred_end_datetime']);
                    ChurchCalendar::assertAvailable(
                        (string) $scheduleData['preferred_start_datetime'],
                        (string) $scheduleData['preferred_end_datetime'],
                        filter_var($scheduleData['preferred_facility_id'] ?? null, FILTER_VALIDATE_INT) ?: null,
                        $id,
                        null,
                        $scheduleData['service_code'] ?? null
                    );
                }
            }
            $update = $connection->prepare('UPDATE service_requests SET status = :status, reviewed_by_user_id = :user_id, reviewed_at = NOW() WHERE id = :id');
            $update->execute(['status' => $status, 'user_id' => $userId, 'id' => $id]);
            $history = $connection->prepare('INSERT INTO service_status_history (service_request_id, old_status, new_status, changed_by_user_id, change_note) VALUES (:id, :old_status, :new_status, :user_id, :note)');
            $history->execute(['id' => $id, 'old_status' => $oldStatus, 'new_status' => $status, 'user_id' => $userId, 'note' => $note]);
            $connection->commit();

            $memberId = (int) ($connection->query('SELECT requested_by_member_id FROM service_requests WHERE id=' . (int) $id)->fetchColumn());
            $messageMap = [
                'UNDER_REVIEW' => 'Your service request is now under review.',
                'PENDING_ASSIGNMENT' => 'Your request is waiting for church staff to assign a pastor or counselor.',
                'DOCUMENTS_REQUIRED' => $note ?: 'Additional documents are required for your service request.',
                'ASSIGNED' => null,
                'APPROVED' => 'Your service request has been confirmed.',
                'CONFIRMED' => 'Your service request has been confirmed.',
                'REJECTED' => $note ?: 'Your service request was declined.',
                'RESCHEDULE_REQUIRED' => $note ?: 'Please choose another schedule for your request.',
                'SCHEDULED' => 'Your service request has been scheduled.',
                'COMPLETED' => 'Your service request has been completed.',
            ];
            $typeMap = [
                'UNDER_REVIEW' => 'SERVICE_REQUEST_UNDER_REVIEW',
                'PENDING_ASSIGNMENT' => 'SERVICE_REQUEST_PENDING_ASSIGNMENT',
                'DOCUMENTS_REQUIRED' => 'DOCUMENTS_REQUIRED',
                'ASSIGNED' => 'SERVICE_REQUEST_ASSIGNED',
                'APPROVED' => 'SERVICE_REQUEST_APPROVED',
                'CONFIRMED' => 'SERVICE_REQUEST_CONFIRMED',
                'REJECTED' => 'SERVICE_REQUEST_REJECTED',
                'RESCHEDULE_REQUIRED' => 'SERVICE_REQUEST_RESCHEDULE',
                'SCHEDULED' => 'SERVICE_REQUEST_SCHEDULED',
                'COMPLETED' => 'SERVICE_REQUEST_COMPLETED',
            ];
            if (isset($messageMap[$status]) && $messageMap[$status] !== null) {
                Notification::notifyMembersByMemberId($memberId, $status === 'UNDER_REVIEW' ? 'Request under review' : ucfirst(strtolower($status)) . ' service request', $messageMap[$status], $typeMap[$status] ?? 'SERVICE_REQUEST_STATUS_UPDATE', 'service_request', $id);
            }
            if ($status === 'APPROVED') AuditLog::record($userId, 'SERVICE_REQUEST_APPROVED', 'SERVICE_REQUEST', $id);
            if ($status === 'REJECTED') AuditLog::record($userId, 'SERVICE_REQUEST_REJECTED', 'SERVICE_REQUEST', $id);
            if (in_array($status, ['APPROVED', 'SCHEDULED', 'CONFIRMED'], true)) {
                require_once __DIR__ . '/Counseling.php';
                Counseling::syncConfirmedRequest($id, (int) $userId);
            }
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) { $connection->rollBack(); }
            throw $exception;
        }
    }

    public static function markUnderReview(int $id, ?int $userId): bool
    {
        $query = database()->prepare('SELECT status FROM service_requests WHERE id = :id LIMIT 1');
        $query->execute(['id' => $id]);
        $status = (string) $query->fetchColumn();
        if (!in_array($status, ['PENDING'], true)) {
            return false;
        }

        self::updateStatus($id, 'PENDING_ASSIGNMENT', $userId, 'Request opened for staff assignment.');
        return true;
    }

    public static function completeByPastor(int $requestId, int $staffId, int $userId): void
    {
        $assignment = database()->prepare(
            'SELECT sr.status
             FROM service_assignments sa
             INNER JOIN service_requests sr ON sr.id = sa.service_request_id
             WHERE sa.service_request_id = :request_id
               AND sa.staff_id = :staff_id
               AND sa.assignment_role = "PASTOR"
             LIMIT 1'
        );
        $assignment->execute(['request_id' => $requestId, 'staff_id' => $staffId]);
        $status = $assignment->fetchColumn();
        if ($status === false) {
            throw new InvalidArgumentException('This service is not assigned to you.');
        }
        if (in_array((string) $status, ['REJECTED', 'CANCELLED'], true)) {
            throw new InvalidArgumentException('This service can no longer be completed.');
        }
        if ((string) $status !== 'COMPLETED') {
            self::updateStatus($requestId, 'COMPLETED', $userId, 'Marked completed by the assigned pastor.');
        }

        $appointments = database()->prepare(
            'SELECT DISTINCT a.id
             FROM appointments a
             LEFT JOIN appointment_staff aps ON aps.appointment_id = a.id
             WHERE a.service_request_id = :request_id
               AND a.status NOT IN ("CANCELLED", "COMPLETED")
               AND (a.assigned_staff_id = :staff_id OR aps.staff_id = :joined_staff_id)'
        );
        $appointments->execute([
            'request_id' => $requestId,
            'staff_id' => $staffId,
            'joined_staff_id' => $staffId,
        ]);
        $completeAppointment = database()->prepare(
            'UPDATE appointments SET status = "COMPLETED" WHERE id = :id AND status NOT IN ("CANCELLED", "COMPLETED")'
        );
        foreach ($appointments->fetchAll() as $appointment) {
            $completeAppointment->execute(['id' => (int) $appointment['id']]);
        }
    }

    public static function decideByPastor(int $requestId, int $staffId, int $userId, string $decision): void
    {
        if (!in_array($decision, ['approve', 'decline'], true)) {
            throw new InvalidArgumentException('Choose approve or decline.');
        }

        $request = database()->prepare('SELECT status FROM service_requests WHERE id = :id LIMIT 1');
        $request->execute(['id' => $requestId]);
        $status = $request->fetchColumn();
        if ($status === false) {
            throw new InvalidArgumentException('Service request not found.');
        }
        $status = (string) $status;
        if (in_array($status, ['COMPLETED', 'REJECTED', 'CANCELLED'], true)) {
            throw new InvalidArgumentException('This request can no longer be changed.');
        }

        $assignment = database()->prepare(
            'SELECT 1
             FROM service_assignments
             WHERE service_request_id = :request_id
               AND staff_id = :staff_id
               AND assignment_role = "PASTOR"
             LIMIT 1'
        );
        $assignment->execute(['request_id' => $requestId, 'staff_id' => $staffId]);
        $assignedToPastor = (bool) $assignment->fetchColumn();
        if (!$assignedToPastor) {
            throw new InvalidArgumentException('This request is not assigned to you.');
        }

        if ($decision === 'approve') {
            if (in_array($status, ['APPROVED', 'SCHEDULED', 'CONFIRMED'], true)) {
                throw new InvalidArgumentException('This request is already approved.');
            }
            self::updateStatus($requestId, 'APPROVED', $userId, 'Approved by the pastor.');
            return;
        }

        self::updateStatus($requestId, 'REJECTED', $userId, 'Declined by the pastor.');
    }

    public static function markApprovedAfterAssignment(int $id, ?int $userId): bool
    {
        $query = database()->prepare('SELECT status FROM service_requests WHERE id = :id LIMIT 1');
        $query->execute(['id' => $id]);
        if (!in_array((string) $query->fetchColumn(), ['PENDING', 'PENDING_ASSIGNMENT', 'UNDER_REVIEW', 'ASSIGNED'], true)) {
            return false;
        }

        self::updateStatus($id, 'CONFIRMED', $userId, 'Booking confirmed after staff assignment.');
        return true;
    }

    public static function nextRequestNumber(?PDO $connection = null): string
    {
        $db = $connection ?? database();
        $year = date('Y');
        $prefix = 'BK-' . $year . '-';
        $query = $db->prepare(
            'SELECT request_number FROM service_requests
             WHERE request_number LIKE :prefix
             ORDER BY request_number DESC
             LIMIT 1'
        );
        $query->execute(['prefix' => $prefix . '%']);
        $last = (string) $query->fetchColumn();
        $sequence = 1;
        if (preg_match('/^BK-\d{4}-(\d+)$/', $last, $matches) === 1) {
            $sequence = (int) $matches[1] + 1;
        }
        return $prefix . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    private static function ensureCounselingCase(int $serviceRequestId): void
    {
        $request = self::find($serviceRequestId);
        if (!$request || !in_array((string) ($request['service_code'] ?? ''), ['WEDDING', 'PRE_MARRIAGE_COUNSELING'], true)) {
            return;
        }
        $existing = database()->prepare('SELECT id FROM counseling_cases WHERE service_request_id = :id LIMIT 1');
        $existing->execute(['id' => $serviceRequestId]);
        if ($existing->fetchColumn()) {
            return;
        }
        require_once __DIR__ . '/Counseling.php';
        Counseling::createCase($serviceRequestId, 3);
    }

    public static function assign(int $id, int $staffId, string $role, ?int $userId): void
    {
        $trimmedRole = strtoupper(trim($role));
        if (!in_array($trimmedRole, ['PASTOR', 'COUNSELOR'], true)) {
            $trimmedRole = 'STAFF_ASSIGNMENT';
        }

        $connection = database();
        $connection->beginTransaction();
        try {
            $clearRole = $connection->prepare(
                'DELETE FROM service_assignments
                 WHERE service_request_id = :request_id'
            );
            $clearRole->execute(['request_id' => $id]);

            $query = $connection->prepare(
                'INSERT INTO service_assignments
                    (service_request_id, staff_id, assignment_role, assigned_by_user_id)
                 VALUES (:request_id, :staff_id, :role, :user_id)'
            );
            $query->execute(['request_id' => $id, 'staff_id' => $staffId, 'role' => $trimmedRole, 'user_id' => $userId]);
            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $exception;
        }

        $current = database()->prepare('SELECT status FROM service_requests WHERE id = :id LIMIT 1');
        $current->execute(['id' => $id]);
        if (in_array((string) $current->fetchColumn(), ['PENDING', 'PENDING_ASSIGNMENT', 'UNDER_REVIEW'], true)) {
            self::updateStatus($id, 'ASSIGNED', $userId, 'Personnel assigned by church staff.');
        }

        $request = self::find($id);
        if ($request) {
            $roleLabel = strtoupper($trimmedRole);
            $memberName = $request['member_name'] ?? 'a member';
            $requestNumber = $request['request_number'] ?? 'service request';
            $serviceName = $request['service_name'] ?? 'service';
            $assignee = $request['assignments'][0]['staff_name'] ?? ('a ' . strtolower($roleLabel));
            $date = $request['preferred_start_datetime'] ? date('F j, Y', strtotime((string) $request['preferred_start_datetime'])) : 'To be confirmed';
            $time = $request['preferred_start_datetime'] ? date('g:i A', strtotime((string) $request['preferred_start_datetime'])) : '';
            $facility = $request['preferred_facility_name'] ?? 'No facility selected';

            if (in_array($roleLabel, ['PASTOR', 'COUNSELOR'], true)) {
                $message = 'You were assigned as ' . $roleLabel . ' on ' . $requestNumber . ' for ' . $memberName . ' (' . $serviceName . ').';
                NotificationService::notifyStaffByStaffId($staffId, 'Service assignment', $message, 'SERVICE_ASSIGNMENT', 'service_request', $id);
                if ($roleLabel === 'COUNSELOR') {
                    self::ensureCounselingCase($id);
                    require_once __DIR__ . '/Counseling.php';
                    Counseling::syncConfirmedRequest($id, (int) $userId);
                }

                $memberUpdate = 'Your ' . $serviceName . ' booking request has been assigned to ' . $assignee . '.'
                    . "\nService: " . $serviceName
                    . "\nDate: " . $date
                    . "\nTime: " . $time
                    . "\nFacility: " . $facility
                    . "\nAssigned " . ucfirst(strtolower($roleLabel)) . ': ' . $assignee
                    . "\nBooking Reference: " . $requestNumber
                    . "\nStatus: Assigned";
                NotificationService::notifyMemberByMemberId((int) $request['requested_by_member_id'], 'Assignment update', $memberUpdate, 'SERVICE_ASSIGNMENT_MEMBER', 'service_request', $id);
            }
        }
    }
}
