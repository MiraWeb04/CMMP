<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/MailService.php';

final class NotificationService
{
    public static function notifyUser(int $userId, string $title, string $message, string $notificationType = 'GENERIC', ?string $referenceType = null, ?int $referenceId = null): int
    {
        $title = trim($title);
        $message = trim($message);
        $query = database()->prepare(
            'INSERT INTO notifications (user_id, title, message, notification_type, reference_type, reference_id, read_at)
             VALUES (:user_id, :title, :message, :notification_type, :reference_type, :reference_id, NULL)'
        );
        $query->execute([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'notification_type' => $notificationType,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
        $notificationId = (int) database()->lastInsertId();
        self::emailUser($userId, $title, $message);
        return $notificationId;
    }

    public static function emailForUser(int $userId): ?array
    {
        $query = database()->prepare(
            'SELECT u.email AS user_email, u.username,
                    m.email AS member_email, m.first_name, m.last_name
             FROM users u
             LEFT JOIN members m ON m.user_id = u.id
             WHERE u.id = :id
             LIMIT 1'
        );
        $query->execute(['id' => $userId]);
        $row = $query->fetch();
        if (!$row) {
            return null;
        }

        $email = trim((string) ($row['user_email'] ?: $row['member_email'] ?: ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        $name = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($row['username'] ?? '')) ?: $email;
        }

        return ['email' => $email, 'name' => $name];
    }

    public static function emailUser(int $userId, string $title, string $message): bool
    {
        $recipient = self::emailForUser($userId);
        if ($recipient === null) {
            return false;
        }

        $portalUrl = rtrim((string) APP_BASE_URL, '/') . '/member-portal.php';
        $safeName = htmlspecialchars($recipient['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $safeUrl = htmlspecialchars($portalUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;color:#16324f">'
            . '<p>Hello ' . $safeName . ',</p>'
            . '<h2 style="color:#d35400;font-size:20px;margin:0 0 12px">' . $safeTitle . '</h2>'
            . '<p>' . $safeMessage . '</p>'
            . '<p><a href="' . $safeUrl . '" style="display:inline-block;background:#d35400;color:#fff;padding:10px 16px;text-decoration:none;border-radius:8px">Open CMMP portal</a></p>'
            . '<p style="color:#5b6b7c;font-size:13px">This message was sent by the UCCP Church Management &amp; Member Portal.</p>'
            . '</div>';

        return MailService::trySend($recipient['email'], $title, $html, true);
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

    public static function notifyStaffByStaffId(int $staffId, string $title, string $message, string $notificationType = 'GENERIC', ?string $referenceType = null, ?int $referenceId = null): bool
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
            return self::notifyUser((int) $userId, $title, $message, $notificationType, $referenceType, $referenceId) > 0;
        }

        return false;
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

    public static function notifyAnnouncementPublished(int $announcementId, ?int $excludeUserId = null): int
    {
        $query = database()->prepare(
            'SELECT id, title, body, audience_role
             FROM announcements
             WHERE id = :id AND announcement_status = "PUBLISHED"
             LIMIT 1'
        );
        $query->execute(['id' => $announcementId]);
        $announcement = $query->fetch();
        if (!$announcement) {
            return 0;
        }

        $audience = strtoupper(trim((string) ($announcement['audience_role'] ?? '')));
        $roles = match ($audience) {
            'MEMBERS' => ['MEMBER'],
            'STAFF' => ['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF'],
            'PASTORS' => ['PASTOR'],
            'COUNSELORS' => ['COUNSELOR'],
            'MINISTRY' => ['MINISTRY_LEADER'],
            default => ['MEMBER', 'STAFF', 'SUPER_ADMIN', 'CHURCH_ADMIN', 'PASTOR', 'COUNSELOR', 'MINISTRY_LEADER'],
        };

        $title = 'Church announcement: ' . (string) $announcement['title'];
        $message = trim(strip_tags((string) $announcement['body']));
        if ($message === '') {
            $message = 'A new church announcement was published.';
        }

        return self::notifyUsersByRole($roles, $title, $message, 'ANNOUNCEMENT_PUBLISHED', 'announcement', $announcementId);
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
