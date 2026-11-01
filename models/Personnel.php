<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

final class Personnel
{
    public static function availablePortalAccounts(?int $staffId = null): array
    {
        $query = database()->prepare(
                        'SELECT u.id AS user_id, m.id AS member_id,
                                        m.first_name, m.middle_name, m.last_name, m.member_number,
                                        u.username, u.email
                         FROM users u
                         LEFT JOIN members m ON m.user_id = u.id
                         LEFT JOIN staff s ON s.user_id = u.id OR s.member_id = m.id
                         WHERE u.status = "ACTIVE"
                             AND (m.id IS NULL OR m.membership_status = "ACTIVE")
                               AND (s.id IS NULL OR s.id = :staff_id)
                             AND NOT EXISTS (
                                     SELECT 1
                                     FROM user_roles excluded_ur
                                     INNER JOIN roles excluded_role ON excluded_role.id = excluded_ur.role_id
                                     WHERE excluded_ur.user_id = u.id
                                         AND excluded_role.code IN ("PASTOR", "COUNSELOR", "SUPER_ADMIN", "CHURCH_ADMIN")
                             )
             ORDER BY m.last_name, m.first_name'
        );

        $query->execute(['staff_id' => $staffId ?? 0]);
        return $query->fetchAll();
    }

    public static function availablePastorAccounts(): array
    {
        $query = database()->query(
            'SELECT u.id AS user_id, m.id AS member_id,
                    m.first_name, m.middle_name, m.last_name, m.member_number,
                    u.username, u.email
                         FROM users u
                                                 LEFT JOIN members m ON m.user_id = u.id
                         WHERE NOT EXISTS (
                                     SELECT 1
                                     FROM staff s
                                     WHERE s.user_id = u.id
                                            OR (m.id IS NOT NULL AND s.member_id = m.id)
                         )
                         ORDER BY m.last_name, m.first_name, u.username'
        );

        return $query->fetchAll();
    }

    public static function availableCounselorAccounts(): array
    {
        $query = database()->query(
            'SELECT u.id AS user_id, m.id AS member_id,
                    m.first_name, m.middle_name, m.last_name, m.member_number,
                    u.username, u.email
                         FROM users u
                         LEFT JOIN members m ON m.user_id = u.id
             WHERE NOT EXISTS (
                   SELECT 1
                   FROM staff s
                   WHERE s.user_id = u.id
                      OR (m.id IS NOT NULL AND s.member_id = m.id)
             )
             ORDER BY m.last_name, m.first_name, u.username'
        );

        return $query->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $query = database()->prepare(
                'SELECT s.*, COALESCE(s.user_id, m.user_id) AS user_id, m.first_name, m.middle_name, m.last_name, m.member_number,
                    COALESCE(CONCAT(m.first_name, " ", m.last_name), u.username, s.employee_number) AS display_name,
                    u.username, u.email,
                    CASE WHEN p.id IS NOT NULL THEN "PASTOR" WHEN c.id IS NOT NULL THEN "COUNSELOR" ELSE "STAFF" END AS personnel_type,
                    p.ordination_date, c.specialization, c.credentials
             FROM staff s
             LEFT JOIN members m ON m.id = s.member_id
             LEFT JOIN users u ON u.id = COALESCE(s.user_id, m.user_id)
             LEFT JOIN pastors p ON p.staff_id = s.id
             LEFT JOIN counselors c ON c.staff_id = s.id
             WHERE s.id = :id LIMIT 1'
        );
        $query->execute(['id' => $id]);
        $person = $query->fetch();
        return $person ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = database();
        $pdo->beginTransaction();

        try {
            $staff = $pdo->prepare(
                'INSERT INTO staff (user_id, member_id, employee_number, position_title, employment_status, hired_at)
                 VALUES (:user_id, :member_id, :employee_number, :position_title, :employment_status, :hired_at)'
            );
            $staff->execute([
                'user_id' => $data['user_id'] ?: null,
                'member_id' => $data['member_id'] ?: null,
                'employee_number' => $data['employee_number'],
                'position_title' => $data['position_title'],
                'employment_status' => $data['employment_status'],
                'hired_at' => $data['hired_at'] ?: null,
            ]);
            $staffId = (int) $pdo->lastInsertId();

            if ($data['personnel_type'] === 'PASTOR') {
                $specialization = $pdo->prepare('INSERT INTO pastors (staff_id, ordination_date) VALUES (:staff_id, :ordination_date)');
                $specialization->execute([
                    'staff_id' => $staffId,
                    'ordination_date' => !empty($data['ordination_date']) ? $data['ordination_date'] : null,
                ]);
            } elseif ($data['personnel_type'] === 'COUNSELOR') {
                $specialization = $pdo->prepare('INSERT INTO counselors (staff_id, specialization, credentials) VALUES (:staff_id, :specialization, :credentials)');
                $specialization->execute([
                    'staff_id' => $staffId,
                    'specialization' => !empty($data['specialization']) ? $data['specialization'] : null,
                    'credentials' => !empty($data['credentials']) ? $data['credentials'] : null,
                ]);
            }

            self::syncPortalRole($pdo, filter_var($data['user_id'] ?? null, FILTER_VALIDATE_INT) ?: null, (string) ($data['personnel_type'] ?? 'STAFF'));
            $pdo->commit();
            return $staffId;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function update(int $id, array $data): void
    {
        $pdo = database();
        $pdo->beginTransaction();
        try {
            $staff = $pdo->prepare(
                'UPDATE staff SET user_id = :user_id, member_id = :member_id, employee_number = :employee_number,
                    position_title = :position_title, employment_status = :employment_status,
                    hired_at = :hired_at WHERE id = :id'
            );
            $staff->execute([
                'id' => $id,
                'user_id' => $data['user_id'] ?: null,
                'member_id' => $data['member_id'] ?: null,
                'employee_number' => $data['employee_number'],
                'position_title' => $data['position_title'],
                'employment_status' => $data['employment_status'],
                'hired_at' => $data['hired_at'] ?: null,
            ]);

            $type = (string) ($data['personnel_type'] ?? 'STAFF');
            $pdo->prepare('DELETE FROM pastors WHERE staff_id = :staff_id')->execute(['staff_id' => $id]);
            $pdo->prepare('DELETE FROM counselors WHERE staff_id = :staff_id')->execute(['staff_id' => $id]);
            if ($type === 'PASTOR') {
                $pdo->prepare('INSERT INTO pastors (staff_id, ordination_date) VALUES (:staff_id, :ordination_date)')->execute(['staff_id' => $id, 'ordination_date' => $data['ordination_date'] ?: null]);
            } elseif ($type === 'COUNSELOR') {
                $pdo->prepare('INSERT INTO counselors (staff_id, specialization, credentials) VALUES (:staff_id, :specialization, :credentials)')->execute(['staff_id' => $id, 'specialization' => $data['specialization'] ?: null, 'credentials' => $data['credentials'] ?: null]);
            }
            self::syncPortalRole($pdo, filter_var($data['user_id'] ?? null, FILTER_VALIDATE_INT) ?: null, $type);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function deactivate(int $id): void
    {
        $query = database()->prepare("UPDATE staff SET employment_status = 'INACTIVE' WHERE id = :id");
        $query->execute(['id' => $id]);
    }

    public static function isClergy(array $person): bool
    {
        return in_array((string) ($person['personnel_type'] ?? ''), ['PASTOR', 'COUNSELOR'], true);
    }

    public static function clergyDirectoryPath(array $person): string
    {
        return ((string) ($person['personnel_type'] ?? '') === 'COUNSELOR')
            ? '../counselors/'
            : '../pastors/';
    }

    public static function roleIdsMixStaffAndClergy(array $roleIds): bool
    {
        $roleIds = array_values(array_filter(array_map('intval', $roleIds)));
        if ($roleIds === []) {
            return false;
        }
        $query = database()->prepare(
            'SELECT code FROM roles WHERE id IN (' . implode(',', array_fill(0, count($roleIds), '?')) . ')'
        );
        $query->execute($roleIds);
        $codes = array_column($query->fetchAll(), 'code');
        return in_array('STAFF', $codes, true) && array_intersect($codes, ['PASTOR', 'COUNSELOR']) !== [];
    }

    public static function list(string $type = 'STAFF'): array
    {
        return self::paginate($type, 1, 500)['items'];
    }

    public static function paginate(string $type, int $page, int $perPage): array
    {
        $typeFilter = '';
        $where = "s.employment_status = 'ACTIVE'";

        if ($type === 'PASTOR') {
            $typeFilter = ' AND p.id IS NOT NULL';
        } elseif ($type === 'COUNSELOR') {
            $typeFilter = ' AND c.id IS NOT NULL';
        } else {
            $typeFilter = ' AND p.id IS NULL AND c.id IS NULL';
        }

        $from = ' FROM staff s
             LEFT JOIN members m ON m.id = s.member_id
             LEFT JOIN users u ON u.id = COALESCE(s.user_id, m.user_id)
             LEFT JOIN pastors p ON p.staff_id = s.id
             LEFT JOIN counselors c ON c.staff_id = s.id
             WHERE ' . $where . $typeFilter;
        $total = (int) database()->query('SELECT COUNT(*)' . $from)->fetchColumn();
        $offset = ($page - 1) * $perPage;
        $query = database()->query(
                'SELECT s.id, s.employee_number, s.position_title, s.employment_status, s.hired_at,
                    p.ordination_date, c.specialization, c.credentials, u.email,
                    COALESCE(CONCAT(m.first_name, " ", m.last_name), u.username, s.employee_number) AS display_name,
                    CASE WHEN p.id IS NOT NULL THEN "PASTOR" WHEN c.id IS NOT NULL THEN "COUNSELOR" ELSE "STAFF" END AS personnel_type
             ' . $from . ' ORDER BY display_name LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset
        );
        return ['items' => $query->fetchAll(), 'total' => $total];
    }

    public static function findForUser(int $userId): ?array
    {
        $query = database()->prepare(
            'SELECT s.id, s.employee_number, s.position_title,
                    COALESCE(CONCAT(m.first_name, " ", m.last_name), u.username, s.employee_number) AS display_name,
                    CASE WHEN p.id IS NOT NULL THEN "PASTOR" WHEN c.id IS NOT NULL THEN "COUNSELOR" ELSE "STAFF" END AS personnel_type
             FROM staff s LEFT JOIN members m ON m.id = s.member_id
             LEFT JOIN users u ON u.id = COALESCE(s.user_id, m.user_id)
             LEFT JOIN pastors p ON p.staff_id = s.id
             LEFT JOIN counselors c ON c.staff_id = s.id
             WHERE COALESCE(s.user_id, m.user_id) = :user_id LIMIT 1'
        );
        $query->execute(['user_id' => $userId]);
        $person = $query->fetch();
        return $person ?: null;
    }

    public static function serviceAssignments(int $staffId): array
    {
        $query = database()->prepare(
            'SELECT sr.id, sr.request_number, sr.subject, sr.status, st.name AS service_name, sa.assignment_role,
                    COALESCE(CONCAT(m.first_name, " ", m.last_name), "") AS member_name,
                    cc.id AS counseling_case_id
             FROM service_assignments sa INNER JOIN service_requests sr ON sr.id = sa.service_request_id
             INNER JOIN service_types st ON st.id = sr.service_type_id
             LEFT JOIN members m ON m.id = sr.requested_by_member_id
             LEFT JOIN counseling_cases cc ON cc.service_request_id = sr.id
             WHERE sa.staff_id = :staff_id ORDER BY sr.updated_at DESC'
        );
        $query->execute(['staff_id' => $staffId]);
        return $query->fetchAll();
    }

    public static function eventAssignments(int $staffId): array
    {
        $query = database()->prepare(
            'SELECT e.id, e.title, e.start_datetime, e.end_datetime, e.event_status, ea.assignment_role
             FROM event_assignments ea INNER JOIN events e ON e.id = ea.event_id
             WHERE ea.staff_id = :staff_id ORDER BY e.start_datetime DESC'
        );
        $query->execute(['staff_id' => $staffId]);
        return $query->fetchAll();
    }

    public static function saveEventAssignment(int $eventId, int $staffId, string $role): void
    {
        $query = database()->prepare(
            'INSERT INTO event_assignments (event_id, staff_id, assignment_role)
             VALUES (:event_id, :staff_id, :assignment_role)
             ON DUPLICATE KEY UPDATE assignment_role = VALUES(assignment_role)'
        );
        $query->execute([
            'event_id' => $eventId,
            'staff_id' => $staffId,
            'assignment_role' => trim($role) !== '' ? trim($role) : 'Event assignment',
        ]);
    }

    private static function syncPortalRole(PDO $pdo, ?int $userId, string $personnelType): void
    {
        if (!$userId) {
            return;
        }

        $roleCode = match ($personnelType) {
            'PASTOR' => 'PASTOR',
            'COUNSELOR' => 'COUNSELOR',
            default => 'STAFF',
        };
        $roleLookup = $pdo->prepare('SELECT id FROM roles WHERE code = :code LIMIT 1');
        $roleLookup->execute(['code' => $roleCode]);
        $roleId = $roleLookup->fetchColumn();
        if ($roleId === false) {
            return;
        }

        $assign = $pdo->prepare(
            'INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
        );
        $assign->execute(['user_id' => $userId, 'role_id' => (int) $roleId]);

        if (in_array($personnelType, ['PASTOR', 'COUNSELOR'], true)) {
            $pdo->prepare(
                'DELETE ur FROM user_roles ur
                 INNER JOIN roles r ON r.id = ur.role_id
                 WHERE ur.user_id = :user_id AND r.code = "STAFF"'
            )->execute(['user_id' => $userId]);
            $pdo->prepare('UPDATE users SET role = :role WHERE id = :user_id AND role = "STAFF"')
                ->execute(['role' => $roleCode, 'user_id' => $userId]);
        }
    }
}
