<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/fellowship.php';
require_once __DIR__ . '/../services/NotificationService.php';

final class Ministry
{
    public static function memberIdForUser(int $userId): ?int
    {
        $query = database()->prepare('SELECT id FROM members WHERE user_id = :user_id LIMIT 1');
        $query->execute(['user_id' => $userId]);
        $id = $query->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public static function listForUser(?int $userId = null): array
    {
        $query = database()->prepare(
            'SELECT m.id, m.name, m.code, m.description, m.ministry_status,
                    COALESCE(CONCAT(leader_member.first_name, " ", leader_member.last_name), "Not assigned") AS leader_name,
                    COUNT(DISTINCT mm.member_id) AS member_count
             FROM ministries m
             LEFT JOIN staff leader_staff ON leader_staff.id = m.leader_staff_id
             LEFT JOIN members leader_member ON leader_member.id = leader_staff.member_id
             LEFT JOIN ministry_members mm ON mm.ministry_id = m.id AND mm.left_at IS NULL
             WHERE m.ministry_status = "ACTIVE"
             GROUP BY m.id ORDER BY m.name'
        );
        $query->execute();
        $ministries = $query->fetchAll();

        foreach ($ministries as &$ministry) {
            $activityQuery = database()->prepare(
                'SELECT id, ministry_id, name, description, start_datetime, end_datetime,
                        location, activity_status
                 FROM ministry_activities
                 WHERE ministry_id = :ministry_id
                   AND activity_status <> "CANCELLED"
                   AND (start_datetime IS NULL OR start_datetime >= NOW())
                 ORDER BY start_datetime ASC, id ASC'
            );
            $activityQuery->execute(['ministry_id' => (int) $ministry['id']]);
            $ministry['activities'] = $activityQuery->fetchAll();
        }
        unset($ministry);

        return $ministries;
    }

    public static function find(int $id): ?array
    {
        $query = database()->prepare(
            'SELECT m.*, leader_staff.id AS leader_staff_id,
                    COALESCE(CONCAT(leader_member.first_name, " ", leader_member.last_name), "Not assigned") AS leader_name
             FROM ministries m LEFT JOIN staff leader_staff ON leader_staff.id = m.leader_staff_id
             LEFT JOIN members leader_member ON leader_member.id = leader_staff.member_id
             WHERE m.id = :id LIMIT 1'
        );
        $query->execute(['id' => $id]);
        $ministry = $query->fetch();
        if (!$ministry) { return null; }
        $members = database()->prepare(
            'SELECT mm.member_id, mm.membership_role, mm.joined_at,
                    m.member_number, CONCAT(m.first_name, " ", m.last_name) AS member_name
             FROM ministry_members mm INNER JOIN members m ON m.id = mm.member_id
             WHERE mm.ministry_id = :id AND mm.left_at IS NULL ORDER BY member_name'
        );
        $members->execute(['id' => $id]);
        $ministry['members'] = $members->fetchAll();
        $activities = database()->prepare('SELECT * FROM ministry_activities WHERE ministry_id = :id ORDER BY start_datetime DESC');
        $activities->execute(['id' => $id]);
        $ministry['activities'] = $activities->fetchAll();
        $events = database()->prepare('SELECT id, title, start_datetime, end_datetime, event_status FROM events WHERE ministry_id = :id ORDER BY start_datetime DESC');
        $events->execute(['id' => $id]);
        $ministry['events'] = $events->fetchAll();
        return $ministry;
    }

    public static function create(array $data): int
    {
        $leaderStaffId = isset($data['leader_staff_id']) ? (int) $data['leader_staff_id'] : 0;
        if ($leaderStaffId > 0) {
            self::assertStaffCanLeadMinistry($leaderStaffId, null);
        }

        $query = database()->prepare('INSERT INTO ministries (name, code, description, ministry_status, leader_staff_id) VALUES (:name, :code, :description, :status, :leader_staff_id)');
        $query->execute(['name' => $data['name'], 'code' => $data['code'], 'description' => $data['description'], 'status' => 'ACTIVE', 'leader_staff_id' => $leaderStaffId > 0 ? $leaderStaffId : null]);
        return (int) database()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $leaderStaffId = isset($data['leader_staff_id']) ? (int) $data['leader_staff_id'] : 0;
        if ($leaderStaffId > 0) {
            self::assertStaffCanLeadMinistry($leaderStaffId, $id);
        }

        $query = database()->prepare('UPDATE ministries SET name = :name, description = :description, leader_staff_id = :leader_staff_id WHERE id = :id');
        $query->execute(['id' => $id, 'name' => $data['name'], 'description' => $data['description'], 'leader_staff_id' => $leaderStaffId > 0 ? $leaderStaffId : null]);
    }

    public static function setStatus(int $id, string $status): void
    {
        $query = database()->prepare('UPDATE ministries SET ministry_status = :status WHERE id = :id');
        $query->execute(['id' => $id, 'status' => $status]);
    }

    public static function activeMembersWithMinistries(?int $excludeMinistryId = null): array
    {
        $query = database()->prepare(
            'SELECT m.id, m.member_number, m.first_name, m.last_name,
                    GROUP_CONCAT(DISTINCT ministry.name ORDER BY ministry.name SEPARATOR ", ") AS other_ministries
             FROM members m
             LEFT JOIN ministry_members mm
                ON mm.member_id = m.id
               AND mm.left_at IS NULL
               AND mm.ministry_id <> :exclude_id
             LEFT JOIN ministries ministry
                ON ministry.id = mm.ministry_id
               AND ministry.ministry_status = "ACTIVE"
             WHERE m.membership_status = "ACTIVE"
             GROUP BY m.id, m.member_number, m.first_name, m.last_name
             ORDER BY m.last_name, m.first_name'
        );
        $query->execute(['exclude_id' => $excludeMinistryId ?? 0]);
        return $query->fetchAll();
    }

    /**
     * Active members who are not already assigned to another ministry (member or leader).
     *
     * @return list<array<string, mixed>>
     */
    public static function membersEligibleForAssignment(?int $ministryId = null): array
    {
        $members = self::activeMembersWithMinistries($ministryId);
        return array_values(array_filter(
            $members,
            static function (array $member): bool {
                if (trim((string) ($member['other_ministries'] ?? '')) !== '') {
                    return false;
                }

                return self::activeMinistryLedByMember((int) $member['id']) === null;
            }
        ));
    }

    public static function activePersonnel(): array
    {
        return database()->query(
            "SELECT s.id, s.employment_status,
                    COALESCE(NULLIF(TRIM(CONCAT(m.first_name, ' ', m.last_name)), ''), u.username, u.email) AS display_name,
                    u.username, u.email
             FROM users u
             LEFT JOIN members m ON m.user_id = u.id
             LEFT JOIN staff s ON s.user_id = u.id OR s.member_id = m.id
             ORDER BY display_name, u.username"
        )->fetchAll();
    }

    public static function canManageMinistry(int $ministryId, int $userId): bool
    {
        $query = database()->prepare(
            'SELECT COUNT(*)
             FROM ministries m
             INNER JOIN staff s ON s.id = m.leader_staff_id
             INNER JOIN members mem ON mem.id = s.member_id
             WHERE m.id = :ministry_id AND mem.user_id = :user_id AND m.ministry_status = "ACTIVE" AND s.employment_status = "ACTIVE"'
        );
        $query->execute(['ministry_id' => $ministryId, 'user_id' => $userId]);
        return (int) $query->fetchColumn() === 1;
    }

    public static function isLeader(int $ministryId, int $userId): bool
    {
        return self::canManageMinistry($ministryId, $userId);
    }

    public static function ledByUser(int $userId): array
    {
        $query = database()->prepare(
            'SELECT m.id, m.name, m.code, m.description, m.ministry_status,
                    COUNT(DISTINCT mm.member_id) AS member_count
             FROM ministries m
             INNER JOIN staff s ON s.id = m.leader_staff_id
             LEFT JOIN members mem ON mem.id = s.member_id
             LEFT JOIN ministry_members mm ON mm.ministry_id = m.id AND mm.left_at IS NULL
             WHERE m.ministry_status = "ACTIVE"
               AND s.employment_status = "ACTIVE"
               AND COALESCE(s.user_id, mem.user_id) = :user_id
             GROUP BY m.id
             ORDER BY m.name'
        );
        $query->execute(['user_id' => $userId]);
        return $query->fetchAll();
    }

    public static function createActivity(array $data): int
    {
        $required = ['ministry_id', 'name'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                throw new InvalidArgumentException('Ministry activity data is incomplete.');
            }
        }

        $query = database()->prepare(
            'INSERT INTO ministry_activities
                (ministry_id, created_by_user_id, name, description, start_datetime,
                 end_datetime, location, activity_status)
             VALUES
                (:ministry_id, :created_by_user_id, :name, :description,
                 :start_datetime, :end_datetime, :location, :activity_status)'
        );
        $query->execute([
            'ministry_id' => (int) $data['ministry_id'],
            'created_by_user_id' => isset($data['created_by_user_id']) ? (int) $data['created_by_user_id'] : null,
            'name' => trim((string) $data['name']),
            'description' => trim((string) ($data['description'] ?? '')),
            'start_datetime' => $data['start_datetime'] ?? null,
            'end_datetime' => $data['end_datetime'] ?? null,
            'location' => trim((string) ($data['location'] ?? '')) ?: null,
            'activity_status' => $data['activity_status'] ?? 'PLANNED',
        ]);

        return (int) database()->lastInsertId();
    }

    public static function updateActivity(int $activityId, array $data): void
    {
        $allowed = ['name', 'description', 'start_datetime', 'end_datetime', 'location', 'activity_status'];
        $updates = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = $field . ' = :' . $field;
            }
        }
        if ($updates === []) {
            return;
        }

        $query = database()->prepare('UPDATE ministry_activities SET ' . implode(', ', $updates) . ' WHERE id = :id');
        $query->execute(array_merge($data, ['id' => $activityId]));
    }

    public static function publishActivity(int $activityId): void
    {
        $existing = database()->prepare('SELECT ministry_id, activity_status FROM ministry_activities WHERE id = :id LIMIT 1');
        $existing->execute(['id' => $activityId]);
        $activity = $existing->fetch();
        if (!$activity) {
            throw new RuntimeException('Ministry activity not found.');
        }

        database()->prepare('UPDATE ministry_activities SET activity_status = :status WHERE id = :id')
            ->execute(['status' => 'PLANNED', 'id' => $activityId]);

        NotificationService::notifyMinistryActivityPublished($activityId);
    }

    public static function activitiesForMember(int $memberId): array
    {
        $query = database()->prepare(
            'SELECT ma.id, ma.ministry_id, ma.name, ma.description, ma.start_datetime,
                    ma.end_datetime, ma.location, ma.activity_status,
                    m.name AS ministry_name,
                    CONCAT(leader_member.first_name, " ", leader_member.last_name) AS leader_name
             FROM ministry_activities ma
             INNER JOIN ministry_members mm ON mm.ministry_id = ma.ministry_id AND mm.member_id = :member_id AND mm.left_at IS NULL
             INNER JOIN ministries m ON m.id = ma.ministry_id AND m.ministry_status = "ACTIVE"
             LEFT JOIN staff leader_staff ON leader_staff.id = m.leader_staff_id
             LEFT JOIN members leader_member ON leader_member.id = leader_staff.member_id
             WHERE ma.activity_status <> "CANCELLED"
               AND ma.start_datetime IS NOT NULL
               AND (ma.start_datetime >= NOW() OR ma.activity_status IN ("PLANNED", "ONGOING"))
             ORDER BY ma.start_datetime ASC, ma.id DESC'
        );
        $query->execute(['member_id' => $memberId]);
        return $query->fetchAll();
    }

    public static function members(int $ministryId): array { return self::find($ministryId)['members'] ?? []; }

    public static function memberIdForStaff(int $staffId): ?int
    {
        $query = database()->prepare('SELECT member_id FROM staff WHERE id = :id LIMIT 1');
        $query->execute(['id' => $staffId]);
        $memberId = $query->fetchColumn();

        return $memberId === false || $memberId === null ? null : (int) $memberId;
    }

    /**
     * @return array{ministry_id: int, ministry_name: string, membership_role: string|null}|null
     */
    public static function activeMembershipForMember(int $memberId): ?array
    {
        $query = database()->prepare(
            'SELECT mm.ministry_id, mm.membership_role, m.name AS ministry_name
             FROM ministry_members mm
             INNER JOIN ministries m ON m.id = mm.ministry_id AND m.ministry_status = "ACTIVE"
             WHERE mm.member_id = :member_id AND mm.left_at IS NULL
             LIMIT 1'
        );
        $query->execute(['member_id' => $memberId]);
        $row = $query->fetch();
        if (!$row) {
            return null;
        }

        return [
            'ministry_id' => (int) $row['ministry_id'],
            'ministry_name' => (string) $row['ministry_name'],
            'membership_role' => $row['membership_role'] !== null ? (string) $row['membership_role'] : null,
        ];
    }

    /**
     * @return array{ministry_id: int, ministry_name: string}|null
     */
    public static function activeMinistryLedByMember(int $memberId, ?int $exceptMinistryId = null): ?array
    {
        $sql = 'SELECT m.id AS ministry_id, m.name AS ministry_name
                FROM ministries m
                INNER JOIN staff s ON s.id = m.leader_staff_id AND s.employment_status = "ACTIVE"
                WHERE s.member_id = :member_id AND m.ministry_status = "ACTIVE"';
        $params = ['member_id' => $memberId];
        if ($exceptMinistryId !== null && $exceptMinistryId > 0) {
            $sql .= ' AND m.id <> :except_id';
            $params['except_id'] = $exceptMinistryId;
        }
        $sql .= ' LIMIT 1';

        $query = database()->prepare($sql);
        $query->execute($params);
        $row = $query->fetch();
        if (!$row) {
            return null;
        }

        return [
            'ministry_id' => (int) $row['ministry_id'],
            'ministry_name' => (string) $row['ministry_name'],
        ];
    }

    public static function assertMemberAvailableForMinistry(int $memberId, int $ministryId): void
    {
        $membership = self::activeMembershipForMember($memberId);
        if ($membership !== null) {
            $roleLabel = $membership['membership_role'] !== null && $membership['membership_role'] !== ''
                ? $membership['membership_role']
                : 'Member';
            if ($membership['ministry_id'] === $ministryId) {
                throw new InvalidArgumentException(
                    'This member is already assigned to ' . $membership['ministry_name'] . ' as ' . $roleLabel . '. Remove them first to assign a different role.'
                );
            }

            throw new InvalidArgumentException(
                'This member is already assigned to ' . $membership['ministry_name'] . '. Remove that assignment before adding them to another ministry.'
            );
        }

        $led = self::activeMinistryLedByMember($memberId);
        if ($led !== null) {
            throw new InvalidArgumentException(
                'This member is the leader of ' . $led['ministry_name'] . '. Remove that leadership assignment before adding them as a ministry member.'
            );
        }
    }

    public static function assertStaffCanLeadMinistry(int $staffId, ?int $ministryId = null): void
    {
        if ($staffId <= 0) {
            return;
        }

        $memberId = self::memberIdForStaff($staffId);
        if ($memberId === null) {
            throw new InvalidArgumentException('The selected leader must be linked to a church member record.');
        }

        $led = self::activeMinistryLedByMember($memberId, $ministryId);
        if ($led !== null) {
            throw new InvalidArgumentException(
                'This person is already the leader of ' . $led['ministry_name'] . '. They cannot lead another ministry.'
            );
        }

        $membership = self::activeMembershipForMember($memberId);
        if ($membership !== null) {
            $roleLabel = $membership['membership_role'] !== null && $membership['membership_role'] !== ''
                ? $membership['membership_role']
                : 'Member';
            throw new InvalidArgumentException(
                'This person is already assigned to ' . $membership['ministry_name'] . ' as ' . $roleLabel . '. Remove that assignment before setting them as a ministry leader.'
            );
        }
    }

    public static function addMember(int $ministryId, int $memberId, ?string $role, bool $fromFellowshipSync = false): void
    {
        $ministryQuery = database()->prepare('SELECT code FROM ministries WHERE id = :id LIMIT 1');
        $ministryQuery->execute(['id' => $ministryId]);
        $code = (string) ($ministryQuery->fetchColumn() ?: '');

        if (fellowship_is_organization_code($code)) {
            if (!$fromFellowshipSync) {
                fellowship_assert_member_eligible($memberId, $code);
            }

            $existingFellowship = fellowship_active_code_for_member($memberId);
            if ($existingFellowship !== null && strtoupper($existingFellowship) !== strtoupper($code)) {
                throw new InvalidArgumentException(
                    'This member is already assigned to ' . fellowship_label_for_code($existingFellowship) . '. Fellowship organizations cannot be changed manually.'
                );
            }
        }

        self::assertMemberAvailableForMinistry($memberId, $ministryId);

        $roleValue = $role !== null && trim($role) !== '' ? trim($role) : 'Member';
        $existingRow = database()->prepare(
            'SELECT left_at FROM ministry_members WHERE ministry_id = :ministry_id AND member_id = :member_id LIMIT 1'
        );
        $existingRow->execute(['ministry_id' => $ministryId, 'member_id' => $memberId]);
        $row = $existingRow->fetch();

        if ($row && $row['left_at'] === null) {
            throw new InvalidArgumentException('This member is already assigned to this ministry.');
        }

        if ($row) {
            database()->prepare(
                'UPDATE ministry_members
                 SET membership_role = :role, joined_at = CURDATE(), left_at = NULL
                 WHERE ministry_id = :ministry_id AND member_id = :member_id'
            )->execute(['ministry_id' => $ministryId, 'member_id' => $memberId, 'role' => $roleValue]);

            return;
        }

        database()->prepare(
            'INSERT INTO ministry_members (ministry_id, member_id, membership_role, joined_at, left_at)
             VALUES (:ministry_id, :member_id, :role, CURDATE(), NULL)'
        )->execute(['ministry_id' => $ministryId, 'member_id' => $memberId, 'role' => $roleValue]);
    }
    public static function removeMember(int $ministryId, int $memberId): void { database()->prepare('UPDATE ministry_members SET left_at = CURDATE() WHERE ministry_id = :ministry_id AND member_id = :member_id AND left_at IS NULL')->execute(['ministry_id' => $ministryId, 'member_id' => $memberId]); }

    public static function membershipIdsForMember(int $memberId): array
    {
        $query = database()->prepare(
            'SELECT ministry_id FROM ministry_members WHERE member_id = :member_id AND left_at IS NULL'
        );
        $query->execute(['member_id' => $memberId]);
        return array_map('intval', array_column($query->fetchAll(), 'ministry_id'));
    }

    public static function requestJoin(int $ministryId, int $memberId): string
    {
        throw new RuntimeException('Members cannot request to join a ministry. Church administration assigns ministry members.');
    }
}
