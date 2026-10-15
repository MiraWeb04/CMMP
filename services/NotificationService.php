<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

final class NotificationService
{
    public static function notifyUser(int $userId, string $title, string $message, string $notificationType = 'GENERIC', ?string $referenceType = null, ?int $referenceId = null): int
    {
        $query = database()->prepare(
            'INSERT INTO notifications (user_id, title, message, notification_type, reference_type, reference_id, read_at)
             VALUES (:user_id, :title, :message, :notification_type, :reference_type, :reference_id, NULL)'
        );
        $query->execute([
            'user_id' => $userId,
            'title' => trim($title),
            'message' => trim($message),
            'notification_type' => $notificationType,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
        return (int) database()->lastInsertId();
    }

    public static function notifyUsers(array $userIds, string $title, string $message, string $notificationType = 'GENERIC', ?string $referenceType = null, ?int $referenceId = null): int
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if ($userIds === []) {
            return 0;
        }

        $inserted = 0;
        foreach ($userIds as $userId) {
            if (self::notifyUser($userId, $title, $message, $notificationType, $referenceType, $referenceId) > 0) {
                $inserted++;
            }
        }

        return $inserted;
    }

    public static function notifyUsersByRole(array $roles, string $title, string $message, string $notificationType = 'GENERIC', ?string $referenceType = null, ?int $referenceId = null): int
    {
        $roles = array_values(array_unique(array_filter(array_map('strtoupper', $roles))));
        if ($roles === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $query = database()->prepare(
            'SELECT DISTINCT u.id
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE u.status = ?
               AND r.code IN (' . $placeholders . ')'
        );

        $params = ['ACTIVE'];
        foreach ($roles as $role) {
            $params[] = $role;
        }
        $query->execute($params);

        $userIds = array_map('intval', array_column($query->fetchAll(), 'id'));
        return self::notifyUsers($userIds, $title, $message, $notificationType, $referenceType, $referenceId);
    }

    public static function notifyMemberByMemberId(int $memberId, string $title, string $message, string $notificationType = 'GENERIC', ?string $referenceType = null, ?int $referenceId = null): void
    {
        $query = database()->prepare('SELECT user_id FROM members WHERE id = :id AND user_id IS NOT NULL LIMIT 1');
        $query->execute(['id' => $memberId]);
        $userId = $query->fetchColumn();
        if ($userId !== false && (int) $userId > 0) {
            self::notifyUser((int) $userId, $title, $message, $notificationType, $referenceType, $referenceId);
        }
    }

    public static function notifyStaffByStaffId(int $staffId, string $title, string $message, string $notificationType = 'GENERIC', ?string $referenceType = null, ?int $referenceId = null): void
    {
        $query = database()->prepare(
            'SELECT COALESCE(s.user_id, m.user_id)
             FROM staff s
             LEFT JOIN members m ON m.id = s.member_id
             WHERE s.id = :staff_id AND s.employment_status = :status
               AND COALESCE(s.user_id, m.user_id) IS NOT NULL
             LIMIT 1'
        );
        $query->execute(['staff_id' => $staffId, 'status' => 'ACTIVE']);
        $userId = $query->fetchColumn();
        if ($userId !== false && (int) $userId > 0) {
            self::notifyUser((int) $userId, $title, $message, $notificationType, $referenceType, $referenceId);
        }
    }

    public static function notifyStaffRoleAssignment(int $serviceRequestId, int $staffId, string $assignmentRole): void
    {
        $roleLabel = strtoupper($assignmentRole);
        $requestQuery = database()->prepare(
            'SELECT sr.request_number, st.name AS service_name, CONCAT(m.first_name, " ", m.last_name) AS member_name
             FROM service_requests sr
             INNER JOIN service_types st ON st.id = sr.service_type_id
             INNER JOIN members m ON m.id = sr.requested_by_member_id
             WHERE sr.id = :id LIMIT 1'
        );
        $requestQuery->execute(['id' => $serviceRequestId]);
        $request = $requestQuery->fetch();
        if (!$request) {
            return;
        }

        $title = 'Service assignment notice';
        $message = 'You were assigned as ' . $roleLabel . ' for service request ' . ($request['request_number'] ?? 'N/A') . ' for ' . ($request['member_name'] ?? 'member') . ' (' . ($request['service_name'] ?? 'service') . ').';

        if (in_array($roleLabel, ['PASTOR', 'COUNSELOR'], true)) {
            self::notifyStaffByStaffId($staffId, $title, $message, 'SERVICE_ASSIGNMENT', 'service_request', $serviceRequestId);
        } else {
            self::notifyStaffByStaffId($staffId, $title, $message, 'SERVICE_ASSIGNMENT', 'service_request', $serviceRequestId);
        }
    }

    public static function notifyAppointmentParticipants(int $appointmentId, array $staffIds, ?int $memberId = null): int
    {
        $staffIds = array_values(array_unique(array_filter(array_map('intval', $staffIds))));
        if ($staffIds === []) {
            return 0;
        }

        $count = 0;
        foreach ($staffIds as $staffId) {
            $title = 'Appointment assignment';
            $message = 'You have been assigned to appointment #' . $appointmentId . ' and must review the appointment details.';
            if (NotificationService::notifyStaffByStaffId($staffId, $title, $message, 'APPOINTMENT_ASSIGNED', 'appointment', $appointmentId)) {
                $count++;
            }
        }

        if ($memberId) {
            $requestTitle = 'Appointment confirmation';
            $requestMessage = 'Your appointment #' . $appointmentId . ' has been confirmed and the assigned church personnel have been notified.';
            NotificationService::notifyMemberByMemberId($memberId, $requestTitle, $requestMessage, 'APPOINTMENT_MEMBER_CONFIRMATION', 'appointment', $appointmentId);
        }

        return $count;
    }

    public static function markAsRead(int $notificationId, int $userId): bool
    {
        $query = database()->prepare(
            'UPDATE notifications
             SET read_at = CURRENT_TIMESTAMP
             WHERE id = :id AND user_id = :user_id AND read_at IS NULL'
        );
        $query->execute(['id' => $notificationId, 'user_id' => $userId]);
        return $query->rowCount() === 1;
    }

    public static function markAllAsRead(int $userId): int
    {
        $query = database()->prepare(
            'UPDATE notifications
             SET read_at = CURRENT_TIMESTAMP
             WHERE user_id = :user_id AND read_at IS NULL'
        );
        $query->execute(['user_id' => $userId]);
        return $query->rowCount();
    }

    public static function getUnreadCount(int $userId): int
    {
        $query = database()->prepare(
            'SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND read_at IS NULL'
        );
        $query->execute(['user_id' => $userId]);
        return (int) $query->fetchColumn();
    }

    public static function notifyMinistryActivityPublished(int $activityId): int
    {
        $query = database()->prepare(
            'SELECT ma.id, ma.name, ma.description, ma.start_datetime, ma.ministry_id,
                    m.name AS ministry_name
             FROM ministry_activities ma
             INNER JOIN ministries m ON m.id = ma.ministry_id
             WHERE ma.id = :id
             LIMIT 1'
        );
        $query->execute(['id' => $activityId]);
        $activity = $query->fetch();
        if (!$activity) {
            return 0;
        }

        $members = database()->prepare(
            'SELECT DISTINCT mm.member_id, m.user_id, CONCAT(member.first_name, " ", member.last_name) AS member_name
             FROM ministry_members mm
             INNER JOIN members member ON member.id = mm.member_id
             INNER JOIN users m ON m.id = member.user_id AND m.status = "ACTIVE"
             WHERE mm.ministry_id = :ministry_id AND mm.left_at IS NULL
             ORDER BY member_name'
        );
        $members->execute(['ministry_id' => (int) $activity['ministry_id']]);

        $count = 0;
        foreach ($members->fetchAll() as $member) {
            $userId = (int) $member['user_id'];
            if ($userId < 1) {
                continue;
            }
            $count += self::notifyUser(
                $userId,
                'New ministry activity published',
                'The ministry activity ' . $activity['name'] . ' has been published for ' . $activity['ministry_name'] . '.',
                'MINISTRY_ACTIVITY_PUBLISHED',
                'ministry_activity',
                (int) $activity['id']
            ) > 0 ? 1 : 0;
        }

        return $count;
    }

    public static function getUserNotifications(int $userId): array
    {
        $query = database()->prepare(
            'SELECT id, user_id, title, message, notification_type, reference_type, reference_id,
                    read_at, created_at
             FROM notifications
             WHERE user_id = :user_id
             ORDER BY created_at DESC, id DESC'
        );
        $query->execute(['user_id' => $userId]);
        return $query->fetchAll();
    }
}
