<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../models/Personnel.php';

function nav_attention_counts(): array
{
    static $counts = null;
    if ($counts !== null) {
        return $counts;
    }

    $counts = [];
    $userId = (int) (current_user_id() ?? 0);
    if ($userId < 1) {
        return $counts;
    }

    $db = database();
    $unread = Notification::unreadCount($userId);
    nav_count_set($counts, ['notifications', 'staff-notifications', 'admin-notifications'], $unread);

    if (has_role('MEMBER')) {
        $memberId = nav_member_id($db, $userId);
        if ($memberId > 0) {
            nav_count_set($counts, ['events'], nav_scalar($db,
                'SELECT COUNT(*)
                 FROM events
                 WHERE event_status = "PUBLISHED"
                   AND end_datetime >= NOW()'
            ));
            nav_count_set($counts, ['registrations'], nav_scalar($db,
                'SELECT COUNT(*)
                 FROM event_registrations er
                 INNER JOIN events e ON e.id = er.event_id
                 WHERE er.member_id = :member_id
                   AND er.registration_status IN ("REGISTERED", "WAITLISTED")
                   AND e.event_status NOT IN ("CANCELLED", "COMPLETED")
                   AND e.end_datetime >= NOW()',
                ['member_id' => $memberId]
            ));
            nav_count_set($counts, ['requests'], nav_scalar($db,
                'SELECT COUNT(*)
                 FROM service_requests
                 WHERE requested_by_member_id = :member_id
                   AND status NOT IN ("COMPLETED", "REJECTED", "CANCELLED")',
                ['member_id' => $memberId]
            ));
            nav_count_set($counts, ['ministries'], nav_scalar($db,
                'SELECT COUNT(*)
                 FROM ministry_activities a
                 INNER JOIN ministry_members mm ON mm.ministry_id = a.ministry_id
                    AND mm.member_id = :member_id
                    AND mm.left_at IS NULL
                 WHERE a.activity_status IN ("PLANNED", "ONGOING")',
                ['member_id' => $memberId]
            ));
        }
    }

    if (has_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF'])) {
        $openRequests = nav_scalar($db,
            'SELECT COUNT(*) FROM service_requests
             WHERE status IN ("PENDING", "PENDING_ASSIGNMENT", "UNDER_REVIEW", "DOCUMENTS_REQUIRED")'
        );
        nav_count_set($counts, ['service-requests'], $openRequests);
        $openEvents = nav_scalar($db,
            'SELECT COUNT(*) FROM events
             WHERE event_status = "DRAFT"
                OR (event_status = "PUBLISHED" AND end_datetime < NOW())'
        );
        nav_count_set($counts, ['events-admin', 'staff-events'], $openEvents);
        $draftAnnouncements = nav_scalar($db, 'SELECT COUNT(*) FROM announcements WHERE announcement_status = "DRAFT"');
        nav_count_set($counts, ['announcements', 'staff-announcements'], $draftAnnouncements);
    }

    if (has_role(['SUPER_ADMIN', 'CHURCH_ADMIN'])) {
        nav_count_set($counts, ['member-registrations'], nav_scalar($db,
            'SELECT COUNT(*)
             FROM users u
             WHERE u.status = "PENDING"
               AND EXISTS (
                   SELECT 1 FROM user_roles ur
                   INNER JOIN roles r ON r.id = ur.role_id
                   WHERE ur.user_id = u.id AND r.code = "MEMBER"
               )'
        ));
        nav_count_set($counts, ['users'], nav_scalar($db, 'SELECT COUNT(*) FROM users WHERE status = "PENDING"'));
    }

    if (has_role(['STAFF', 'PASTOR', 'COUNSELOR', 'MINISTRY_LEADER'])) {
        $person = Personnel::findForUser($userId);
        $staffId = (int) ($person['id'] ?? 0);
        if ($staffId > 0) {
            $openAppointments = nav_scalar($db,
                'SELECT COUNT(DISTINCT a.id)
                 FROM appointments a
                 LEFT JOIN appointment_staff aps ON aps.appointment_id = a.id
                 WHERE a.status IN ("PENDING", "CONFIRMED")
                   AND a.end_datetime >= NOW()
                   AND (a.assigned_staff_id = :assigned_staff_id OR aps.staff_id = :linked_staff_id)',
                ['assigned_staff_id' => $staffId, 'linked_staff_id' => $staffId]
            );
            nav_count_set($counts, ['pastor-appointments', 'pastor-schedule', 'counselor-schedule', 'staff-calendar'], $openAppointments);

            $openAssigned = nav_scalar($db,
                'SELECT COUNT(*)
                 FROM service_assignments sa
                 INNER JOIN service_requests sr ON sr.id = sa.service_request_id
                 WHERE sa.staff_id = :staff_id
                   AND sr.status NOT IN ("COMPLETED", "REJECTED", "CANCELLED")',
                ['staff_id' => $staffId]
            );
            nav_count_set($counts, ['pastor-services', 'pastor-review', 'staff-assignments'], $openAssigned);

            $openCases = nav_scalar($db,
                'SELECT COUNT(*)
                 FROM counseling_cases cc
                 INNER JOIN service_assignments sa ON sa.service_request_id = cc.service_request_id
                    AND sa.staff_id = :staff_id
                    AND sa.assignment_role = "COUNSELOR"
                 WHERE cc.status <> "COMPLETED"',
                ['staff_id' => $staffId]
            );
            nav_count_set($counts, ['counselor-couples', 'counselor-sessions'], $openCases);
        }
    }

    if (has_role('MINISTRY_LEADER')) {
        nav_count_set($counts, ['ministry-activities'], nav_scalar($db,
            'SELECT COUNT(*)
             FROM ministry_activities a
             INNER JOIN ministries m ON m.id = a.ministry_id
             INNER JOIN staff s ON s.id = m.leader_staff_id
             LEFT JOIN members mem ON mem.id = s.member_id
             WHERE a.activity_status IN ("PLANNED", "ONGOING")
               AND m.ministry_status = "ACTIVE"
               AND COALESCE(s.user_id, mem.user_id) = :user_id',
            ['user_id' => $userId]
        ));
        nav_count_set($counts, ['ministry-events'], nav_scalar($db,
            'SELECT COUNT(*)
             FROM events e
             INNER JOIN ministries m ON m.id = e.ministry_id
             INNER JOIN staff s ON s.id = m.leader_staff_id
             LEFT JOIN members mem ON mem.id = s.member_id
             WHERE e.event_status IN ("DRAFT", "PUBLISHED")
               AND (e.event_status = "DRAFT" OR e.end_datetime >= NOW())
               AND COALESCE(s.user_id, mem.user_id) = :user_id',
            ['user_id' => $userId]
        ));
    }

    return $counts;
}

function nav_count_set(array &$counts, array $keys, int $value): void
{
    if ($value < 1) {
        return;
    }
    foreach ($keys as $key) {
        $counts[$key] = $value;
    }
}

function nav_scalar(PDO $db, string $sql, array $params = []): int
{
    $query = $db->prepare($sql);
    $query->execute($params);
    return (int) $query->fetchColumn();
}

function nav_member_id(PDO $db, int $userId): int
{
    $query = $db->prepare('SELECT id FROM members WHERE user_id = :user_id LIMIT 1');
    $query->execute(['user_id' => $userId]);
    return (int) $query->fetchColumn();
}
