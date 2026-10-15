<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

final class Facility
{
    public static function paginate(string $search, string $status, string $type, int $page, int $perPage): array
    {
        $conditions = [];
        $parameters = [];
        if ($search !== '') {
            $conditions[] = '(f.name LIKE :search OR f.code LIKE :search OR f.location LIKE :search)';
            $parameters['search'] = '%' . $search . '%';
        }
        if ($status !== '') {
            $conditions[] = 'f.facility_status = :status';
            $parameters['status'] = $status;
        }
        if ($type !== '') {
            $conditions[] = 'f.facility_type = :type';
            $parameters['type'] = $type;
        }
        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

        $count = database()->prepare("SELECT COUNT(*) FROM facilities f $where");
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();

        $query = database()->prepare(
            "SELECT f.id, f.name, f.code, f.location, f.capacity, f.facility_type, f.facility_status,
                    COUNT(DISTINCT a.id) AS appointment_count
             FROM facilities f
             LEFT JOIN appointments a ON a.facility_id = f.id AND a.status <> 'CANCELLED'
             $where
             GROUP BY f.id
             ORDER BY f.name, f.id
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

    public static function counselingOffice(): array
    {
        $query = database()->prepare(
            'SELECT id, name, code, location
             FROM facilities
             WHERE code = :code OR name = :name
             ORDER BY code = :code_order DESC
             LIMIT 1'
        );
        $query->execute([
            'code' => 'COUNSELING-OFFICE',
            'name' => 'Counseling Office',
            'code_order' => 'COUNSELING-OFFICE',
        ]);
        $office = $query->fetch();
        if ($office) {
            return $office;
        }

        $id = self::create([
            'name' => 'Counseling Office',
            'code' => 'COUNSELING-OFFICE',
            'description' => 'Private church office where pre-marriage and pastoral counseling sessions are conducted.',
            'capacity' => 6,
            'location' => 'Church office',
            'facility_type' => 'CONFERENCE_ROOM',
            'facility_status' => 'AVAILABLE',
        ]);

        return [
            'id' => $id,
            'name' => 'Counseling Office',
            'code' => 'COUNSELING-OFFICE',
            'location' => 'Church office',
        ];
    }

    public static function find(int $id): ?array
    {
        $query = database()->prepare(
            'SELECT f.*, COUNT(DISTINCT a.id) AS appointment_count
             FROM facilities f
             LEFT JOIN appointments a ON a.facility_id = f.id AND a.status <> :cancelled
             WHERE f.id = :id
             GROUP BY f.id
             LIMIT 1'
        );
        $query->execute(['cancelled' => 'CANCELLED', 'id' => $id]);
        $facility = $query->fetch();

        return $facility ?: null;
    }

    public static function create(array $data): int
    {
        $query = database()->prepare(
            'INSERT INTO facilities (name, code, description, capacity, location, facility_type, facility_status)
             VALUES (:name, :code, :description, :capacity, :location, :facility_type, :facility_status)'
        );
        $query->execute($data);

        return (int) database()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $allowed = ['name', 'description', 'capacity', 'location', 'facility_type'];
        $data = array_intersect_key($data, array_flip($allowed));
        $assignments = [];
        foreach (array_keys($data) as $column) {
            $assignments[] = $column . ' = :' . $column;
        }
        if ($assignments === []) {
            return;
        }
        $data['id'] = $id;
        $query = database()->prepare('UPDATE facilities SET ' . implode(', ', $assignments) . ' WHERE id = :id');
        $query->execute($data);
    }

    public static function setStatus(int $id, string $status): void
    {
        $query = database()->prepare('UPDATE facilities SET facility_status = :status WHERE id = :id');
        $query->execute(['status' => $status, 'id' => $id]);
    }
}
