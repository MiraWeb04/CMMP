<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

final class Family
{
    public static function paginate(string $search, int $page, int $perPage): array
    {
        $conditions = [];
        $parameters = [];

        if ($search !== '') {
            $conditions[] = 'f.family_code LIKE :search_code';
            $conditions[] = 'f.family_name LIKE :search_name';
            $conditions[] = 'f.city LIKE :search_city';
            $parameters['search_code'] = '%' . $search . '%';
            $parameters['search_name'] = '%' . $search . '%';
            $parameters['search_city'] = '%' . $search . '%';
        }

        $where = $conditions === [] ? '' : 'WHERE ' . implode(' OR ', $conditions);

        $count = database()->prepare("SELECT COUNT(*) FROM families f $where");
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();

        $query = database()->prepare(
            "SELECT f.id, f.family_code, f.family_name, f.city, f.state_province,
                    COUNT(fm.member_id) AS member_count,
                    MAX(CASE WHEN fm.is_primary_contact = 1 THEN CONCAT(m.first_name, ' ', m.last_name) END) AS primary_contact
             FROM families f
             LEFT JOIN family_members fm ON fm.family_id = f.id
             LEFT JOIN members m ON m.id = fm.member_id
             $where
             GROUP BY f.id
             ORDER BY f.family_name, f.id
             LIMIT :limit OFFSET :offset"
        );

        foreach ($parameters as $key => $value) {
            $query->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $query->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $query->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $query->execute();

        return ['items' => $query->fetchAll(), 'total' => $total];
    }

    public static function find(int $id): ?array
    {
        $query = database()->prepare('SELECT * FROM families WHERE id = :id LIMIT 1');
        $query->execute(['id' => $id]);
        $family = $query->fetch();
        if (!$family) {
            return null;
        }

        $members = database()->prepare(
            'SELECT m.id, m.member_number, m.first_name, m.middle_name, m.last_name, m.suffix,
                    m.email, m.phone, fm.relationship_to_head, fm.is_primary_contact
             FROM family_members fm
             INNER JOIN members m ON m.id = fm.member_id
             WHERE fm.family_id = :family_id
             ORDER BY fm.is_primary_contact DESC, m.last_name, m.first_name'
        );
        $members->execute(['family_id' => $id]);
        $family['members'] = $members->fetchAll();

        return $family;
    }

    public static function create(array $data): int
    {
        $query = database()->prepare(
            'INSERT INTO families
                (family_code, family_name, address_line_1, address_line_2, city, state_province, postal_code, country)
             VALUES
                (:family_code, :family_name, :address_line_1, :address_line_2, :city, :state_province, :postal_code, :country)'
        );
        $query->execute($data);

        return (int) database()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $allowed = ['family_name', 'address_line_1', 'address_line_2', 'city', 'state_province', 'postal_code', 'country'];
        $data = array_intersect_key($data, array_flip($allowed));
        $assignments = [];
        foreach (array_keys($data) as $column) {
            $assignments[] = $column . ' = :' . $column;
        }
        if ($assignments === []) {
            return;
        }
        $data['id'] = $id;
        $query = database()->prepare('UPDATE families SET ' . implode(', ', $assignments) . ' WHERE id = :id');
        $query->execute($data);
    }

    public static function addMember(int $familyId, int $memberId, ?string $relationship): void
    {
        $query = database()->prepare(
            'INSERT INTO family_members (family_id, member_id, relationship_to_head, is_primary_contact)
             VALUES (:family_id, :member_id, :relationship, 0)'
        );
        $query->execute(['family_id' => $familyId, 'member_id' => $memberId, 'relationship' => $relationship ?: null]);
    }

    public static function removeMember(int $familyId, int $memberId): void
    {
        $query = database()->prepare('DELETE FROM family_members WHERE family_id = :family_id AND member_id = :member_id');
        $query->execute(['family_id' => $familyId, 'member_id' => $memberId]);
    }

    public static function setPrimaryContact(int $familyId, int $memberId): void
    {
        $connection = database();
        $connection->beginTransaction();
        try {
            $clear = $connection->prepare('UPDATE family_members SET is_primary_contact = 0 WHERE family_id = :family_id');
            $clear->execute(['family_id' => $familyId]);
            $set = $connection->prepare(
                'UPDATE family_members SET is_primary_contact = 1 WHERE family_id = :family_id AND member_id = :member_id'
            );
            $set->execute(['family_id' => $familyId, 'member_id' => $memberId]);
            if ($set->rowCount() !== 1) {
                throw new RuntimeException('The selected member is not part of this family.');
            }
            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }

    public static function availableMembers(int $familyId): array
    {
        $query = database()->prepare(
            'SELECT m.id, m.member_number, m.first_name, m.last_name
             FROM members m
             LEFT JOIN family_members fm ON fm.member_id = m.id AND fm.family_id = :family_id
             WHERE fm.member_id IS NULL AND m.membership_status = :status
             ORDER BY m.last_name, m.first_name'
        );
        $query->execute(['family_id' => $familyId, 'status' => 'ACTIVE']);

        return $query->fetchAll();
    }
}
