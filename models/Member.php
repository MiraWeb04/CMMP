<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/fellowship.php';
require_once __DIR__ . '/AuditLog.php';

final class Member
{
    public static function availableUsers(?int $memberId = null): array
    {
        $query = database()->prepare(
            'SELECT u.id, u.username, u.email
             FROM users u
             LEFT JOIN members m ON m.user_id = u.id
             WHERE u.status = :status AND (m.id IS NULL OR m.id = :member_id)
             ORDER BY u.username, u.email'
        );
        $query->execute(['status' => 'ACTIVE', 'member_id' => $memberId ?? 0]);

        return $query->fetchAll();
    }

    public static function paginate(array $filters, int $page, int $perPage): array
    {
        $conditions = [];
        $parameters = [];

        if ($filters['search'] !== '') {
            $term = '%' . $filters['search'] . '%';
            $conditions[] = '(m.member_number LIKE :search_number OR m.first_name LIKE :search_first OR m.middle_name LIKE :search_middle OR m.last_name LIKE :search_last OR m.email LIKE :search_email)';
            $parameters['search_number'] = $term;
            $parameters['search_first'] = $term;
            $parameters['search_middle'] = $term;
            $parameters['search_last'] = $term;
            $parameters['search_email'] = $term;
        }
        if ($filters['status'] !== '') {
            $conditions[] = 'm.membership_status = :status';
            $parameters['status'] = $filters['status'];
        }
        if ($filters['gender'] !== '') {
            $conditions[] = 'm.gender = :gender';
            $parameters['gender'] = $filters['gender'];
        }

        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);
        $count = database()->prepare("SELECT COUNT(*) FROM members m $where");
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $query = database()->prepare(
            "SELECT m.id, m.member_number, m.first_name, m.middle_name, m.last_name,
                    m.email, m.phone, m.gender, m.membership_status, m.joined_at
             FROM members m
             $where
             ORDER BY m.last_name, m.first_name, m.id
             LIMIT :limit OFFSET :offset"
        );
        foreach ($parameters as $key => $value) {
            $query->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $query->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();

        return ['items' => $query->fetchAll(), 'total' => $total];
    }

    public static function find(int $id): ?array
    {
        $query = database()->prepare('SELECT * FROM members WHERE id = :id LIMIT 1');
        $query->execute(['id' => $id]);
        $member = $query->fetch();

        if (!$member) {
            return null;
        }

        $family = database()->prepare(
            'SELECT f.id, f.family_code, f.family_name, fm.relationship_to_head, fm.is_primary_contact
             FROM family_members fm
             INNER JOIN families f ON f.id = fm.family_id
             WHERE fm.member_id = :member_id
             ORDER BY fm.is_primary_contact DESC, f.family_name'
        );
        $family->execute(['member_id' => $id]);
        $member['families'] = $family->fetchAll();

        $ministries = database()->prepare(
            'SELECT m.id, m.name, mm.membership_role, mm.joined_at, mm.left_at
             FROM ministry_members mm
             INNER JOIN ministries m ON m.id = mm.ministry_id
             WHERE mm.member_id = :member_id
             ORDER BY m.name'
        );
        $ministries->execute(['member_id' => $id]);
        $member['ministries'] = $ministries->fetchAll();

        return $member;
    }

    public static function findForUser(int $userId): ?array
    {
        $query = database()->prepare('SELECT id FROM members WHERE user_id = :user_id LIMIT 1');
        $query->execute(['user_id' => $userId]);
        $memberId = $query->fetchColumn();

        return $memberId === false ? null : self::find((int) $memberId);
    }

    public static function createFromRegistration(int $userId, array $profile): int
    {
        $memberNumber = 'CMMP-' . strtoupper(bin2hex(random_bytes(5)));
        $member = [
            'user_id' => $userId,
            'member_number' => $memberNumber,
            'first_name' => trim((string) ($profile['first_name'] ?? '')) ?: 'Member',
            'middle_name' => null,
            'last_name' => trim((string) ($profile['last_name'] ?? '')) ?: 'User',
            'suffix' => null,
            'date_of_birth' => trim((string) ($profile['date_of_birth'] ?? '')) ?: null,
            'gender' => trim((string) ($profile['gender'] ?? '')) ?: null,
            'phone' => null,
            'email' => trim((string) ($profile['email'] ?? '')) ?: null,
            'address_line_1' => null,
            'address_line_2' => null,
            'city' => null,
            'state_province' => null,
            'postal_code' => null,
            'country' => 'Philippines',
            'baptism_date' => null,
            'baptism_church' => null,
            'baptism_notes' => null,
            'emergency_contact_name' => null,
            'emergency_contact_relationship' => null,
            'emergency_contact_phone' => null,
            'profile_photo' => null,
            'membership_status' => 'INACTIVE',
            'joined_at' => null,
        ];

        return self::create($member);
    }

    public static function create(array $data): int
    {
        $query = database()->prepare(
            'INSERT INTO members
                (user_id, member_number, first_name, middle_name, last_name, suffix, date_of_birth,
                 gender, phone, email, address_line_1, address_line_2, city, state_province,
                 postal_code, country, baptism_date, baptism_church, baptism_notes,
                 emergency_contact_name, emergency_contact_relationship, emergency_contact_phone,
                 profile_photo, membership_status, joined_at)
             VALUES
                (:user_id, :member_number, :first_name, :middle_name, :last_name, :suffix, :date_of_birth,
                 :gender, :phone, :email, :address_line_1, :address_line_2, :city, :state_province,
                 :postal_code, :country, :baptism_date, :baptism_church, :baptism_notes,
                 :emergency_contact_name, :emergency_contact_relationship, :emergency_contact_phone,
                 :profile_photo, :membership_status, :joined_at)'
        );
        $query->execute($data);

        $memberId = (int) database()->lastInsertId();
        if (($data['membership_status'] ?? 'ACTIVE') === 'ACTIVE') {
            fellowship_sync_member(
                $memberId,
                $data['date_of_birth'] ?? null,
                $data['gender'] ?? null
            );
        }

        return $memberId;
    }

    public static function update(int $id, array $data): void
    {
        $allowed = [
            'user_id', 'first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'gender',
            'phone', 'email', 'address_line_1', 'address_line_2', 'city', 'state_province',
            'postal_code', 'country', 'baptism_date', 'baptism_church', 'baptism_notes',
            'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_phone',
            'profile_photo', 'membership_status', 'joined_at',
        ];
        $data = array_intersect_key($data, array_flip($allowed));
        $assignments = [];
        foreach (array_keys($data) as $column) {
            $assignments[] = $column . ' = :' . $column;
        }
        if ($assignments === []) {
            return;
        }

        $data['id'] = $id;
        $query = database()->prepare('UPDATE members SET ' . implode(', ', $assignments) . ' WHERE id = :id');
        $query->execute($data);

        $profile = self::find($id);
        if ($profile) {
            fellowship_sync_member($id, $profile['date_of_birth'] ?? null, $profile['gender'] ?? null);
        }

        AuditLog::record(null, 'MEMBER_UPDATED', 'MEMBER', $id);
    }

    public static function setStatus(int $id, string $status): void
    {
        $query = database()->prepare('UPDATE members SET membership_status = :status WHERE id = :id');
        $query->execute(['id' => $id, 'status' => $status]);
    }
}