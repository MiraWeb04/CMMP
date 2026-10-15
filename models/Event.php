<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Notification.php';
require_once __DIR__ . '/ChurchCalendar.php';

final class Event
{
    public static function categories(): array
    {
        return database()->query('SELECT id, name FROM event_categories WHERE is_active = 1 ORDER BY name')->fetchAll();
    }

    public static function facilities(): array
    {
        return database()->query("SELECT id, name, code, capacity FROM facilities WHERE facility_status = 'AVAILABLE' ORDER BY name")->fetchAll();
    }

    public static function paginateAdmin(string $search, string $status, int $page, int $perPage): array
    {
        $conditions = [];
        $parameters = [];
        if ($search !== '') {
            $conditions[] = '(e.title LIKE :search OR e.description LIKE :search)';
            $parameters['search'] = '%' . $search . '%';
        }
        if ($status !== '') {
            $conditions[] = 'e.event_status = :status';
            $parameters['status'] = $status;
        }
        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);
        $count = database()->prepare("SELECT COUNT(*) FROM events e $where");
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();

        $query = database()->prepare(
            "SELECT e.id, e.title, e.start_datetime, e.end_datetime, e.capacity,
                    e.registration_required, e.registration_deadline, e.event_status,
                    c.name AS category_name, f.name AS facility_name,
                    COUNT(DISTINCT CASE WHEN er.registration_status = 'REGISTERED' THEN er.id END) AS registration_count
             FROM events e
             LEFT JOIN event_categories c ON c.id = e.category_id
             LEFT JOIN facilities f ON f.id = e.facility_id
             LEFT JOIN event_registrations er ON er.event_id = e.id
             $where
             GROUP BY e.id
             ORDER BY e.start_datetime DESC, e.id DESC
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

    public static function upcoming(int $memberId): array
    {
        $query = database()->prepare(
            "SELECT e.id, e.title, e.description, e.start_datetime, e.end_datetime,
                    e.location, e.capacity, e.registration_required, e.registration_deadline,
                    e.event_image, e.event_status, c.name AS category_name, f.name AS facility_name,
                    COUNT(DISTINCT CASE WHEN all_er.registration_status = 'REGISTERED' THEN all_er.id END) AS registration_count,
                    own_er.registration_status AS own_registration_status
             FROM events e
             LEFT JOIN event_categories c ON c.id = e.category_id
             LEFT JOIN facilities f ON f.id = e.facility_id
             LEFT JOIN event_registrations all_er ON all_er.event_id = e.id
             LEFT JOIN event_registrations own_er ON own_er.event_id = e.id AND own_er.member_id = :member_id
             WHERE e.event_status = 'PUBLISHED' AND e.end_datetime >= NOW()
             GROUP BY e.id, own_er.registration_status
             ORDER BY e.start_datetime, e.id"
        );
        $query->execute(['member_id' => $memberId]);

        return $query->fetchAll();
    }

    public static function registered(int $memberId): array
    {
        $query = database()->prepare(
            "SELECT e.id, e.title, e.start_datetime, e.end_datetime, e.location,
                    c.name AS category_name, er.registration_status, er.registered_at
             FROM event_registrations er
             INNER JOIN events e ON e.id = er.event_id
             LEFT JOIN event_categories c ON c.id = e.category_id
             WHERE er.member_id = :member_id AND er.registration_status <> 'CANCELLED'
             ORDER BY e.start_datetime DESC"
        );
        $query->execute(['member_id' => $memberId]);

        return $query->fetchAll();
    }

    public static function find(int $id, ?int $memberId = null): ?array
    {
        $query = database()->prepare(
            "SELECT e.*, c.name AS category_name, f.name AS facility_name,
                    COUNT(DISTINCT CASE WHEN er.registration_status = 'REGISTERED' THEN er.id END) AS registration_count,
                    own_er.registration_status AS own_registration_status
             FROM events e
             LEFT JOIN event_categories c ON c.id = e.category_id
             LEFT JOIN facilities f ON f.id = e.facility_id
             LEFT JOIN event_registrations er ON er.event_id = e.id
             LEFT JOIN event_registrations own_er ON own_er.event_id = e.id AND own_er.member_id = :member_id
             WHERE e.id = :id
             GROUP BY e.id, own_er.registration_status
             LIMIT 1"
        );
        $query->execute(['id' => $id, 'member_id' => $memberId ?? 0]);
        $event = $query->fetch();

        return $event ?: null;
    }

    public static function create(array $data): int
    {
        $connection = database();
        $connection->beginTransaction();
        try {
            if (($data['event_status'] ?? 'DRAFT') === 'PUBLISHED') {
                ChurchCalendar::lockDateRange($connection, (string) $data['start_datetime'], (string) $data['end_datetime']);
                ChurchCalendar::assertAvailable(
                    (string) $data['start_datetime'],
                    (string) $data['end_datetime'],
                    filter_var($data['facility_id'] ?? null, FILTER_VALIDATE_INT) ?: null
                );
            }
            $query = $connection->prepare(
            'INSERT INTO events
                (created_by_user_id, category_id, facility_id, title, description, start_datetime,
                 end_datetime, location, capacity, registration_required, registration_deadline,
                 event_image, event_status)
             VALUES
                (:created_by_user_id, :category_id, :facility_id, :title, :description, :start_datetime,
                 :end_datetime, :location, :capacity, :registration_required, :registration_deadline,
                 :event_image, :event_status)'
            );
            $query->execute($data);
            $eventId = (int) $connection->lastInsertId();
            $connection->commit();
            return $eventId;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) { $connection->rollBack(); }
            throw $exception;
        }
    }

    public static function update(int $id, array $data): void
    {
        $allowed = [
            'category_id', 'facility_id', 'title', 'description', 'start_datetime', 'end_datetime',
            'location', 'capacity', 'registration_required', 'registration_deadline', 'event_image',
        ];
        $data = array_intersect_key($data, array_flip($allowed));
        $connection = database();
        $connection->beginTransaction();
        try {
            $currentQuery = $connection->prepare('SELECT start_datetime, end_datetime, facility_id, event_status FROM events WHERE id = :id FOR UPDATE');
            $currentQuery->execute(['id' => $id]);
            $current = $currentQuery->fetch();
            if (!$current) { throw new RuntimeException('Event not found.'); }
            $schedule = array_merge($current, $data);
            if ($current['event_status'] === 'PUBLISHED') {
                ChurchCalendar::lockDateRange($connection, (string) $schedule['start_datetime'], (string) $schedule['end_datetime']);
                ChurchCalendar::assertAvailable(
                    (string) $schedule['start_datetime'],
                    (string) $schedule['end_datetime'],
                    filter_var($schedule['facility_id'] ?? null, FILTER_VALIDATE_INT) ?: null,
                    null,
                    $id
                );
            }
        $assignments = [];
        foreach (array_keys($data) as $column) {
            $assignments[] = $column . ' = :' . $column;
        }
        if ($assignments === []) {
                $connection->commit();
            return;
        }
        $data['id'] = $id;
        $query = $connection->prepare('UPDATE events SET ' . implode(', ', $assignments) . ' WHERE id = :id');
        $query->execute($data);
            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) { $connection->rollBack(); }
            throw $exception;
        }
    }

    public static function setStatus(int $id, string $status): void
    {
        $connection = database();
        $connection->beginTransaction();
        try {
            $currentQuery = $connection->prepare('SELECT start_datetime, end_datetime, facility_id, event_status FROM events WHERE id = :id FOR UPDATE');
            $currentQuery->execute(['id' => $id]);
            $current = $currentQuery->fetch();
            if (!$current) { throw new RuntimeException('Event not found.'); }
            if ($status === 'PUBLISHED' && $current['event_status'] !== 'PUBLISHED') {
                ChurchCalendar::lockDateRange($connection, (string) $current['start_datetime'], (string) $current['end_datetime']);
                ChurchCalendar::assertAvailable(
                    (string) $current['start_datetime'],
                    (string) $current['end_datetime'],
                    filter_var($current['facility_id'] ?? null, FILTER_VALIDATE_INT) ?: null,
                    null,
                    $id
                );
            }
            $query = $connection->prepare('UPDATE events SET event_status = :status WHERE id = :id');
            $query->execute(['status' => $status, 'id' => $id]);
            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) { $connection->rollBack(); }
            throw $exception;
        }
    }

    public static function register(int $eventId, int $memberId): void
    {
        $connection = database();
        $connection->beginTransaction();
        try {
            $eventQuery = $connection->prepare(
                'SELECT id, capacity, registration_required, registration_deadline, event_status
                 FROM events WHERE id = :id FOR UPDATE'
            );
            $eventQuery->execute(['id' => $eventId]);
            $event = $eventQuery->fetch();
            if (!$event || $event['event_status'] !== 'PUBLISHED') {
                throw new DomainException('This event is not open for registration.');
            }
            if (!(bool) $event['registration_required']) {
                throw new DomainException('Registration is not required for this event.');
            }
            if ($event['registration_deadline'] !== null && strtotime($event['registration_deadline']) < time()) {
                throw new DomainException('Registration for this event has closed.');
            }

            $existingQuery = $connection->prepare(
                'SELECT id, registration_status FROM event_registrations
                 WHERE event_id = :event_id AND member_id = :member_id FOR UPDATE'
            );
            $existingQuery->execute(['event_id' => $eventId, 'member_id' => $memberId]);
            $existing = $existingQuery->fetch();
            if ($existing && $existing['registration_status'] !== 'CANCELLED') {
                throw new DomainException('You are already registered for this event.');
            }

            $countQuery = $connection->prepare(
                "SELECT COUNT(*) FROM event_registrations WHERE event_id = :event_id AND registration_status = 'REGISTERED'"
            );
            $countQuery->execute(['event_id' => $eventId]);
            $registeredCount = (int) $countQuery->fetchColumn();
            if ($event['capacity'] !== null && $registeredCount >= (int) $event['capacity']) {
                throw new DomainException('This event has reached capacity.');
            }

            if ($existing) {
                $save = $connection->prepare("UPDATE event_registrations SET registration_status = 'REGISTERED', registered_at = NOW() WHERE id = :id");
                $save->execute(['id' => $existing['id']]);
            } else {
                $save = $connection->prepare(
                    "INSERT INTO event_registrations (event_id, member_id, registration_status)
                     VALUES (:event_id, :member_id, 'REGISTERED')"
                );
                $save->execute(['event_id' => $eventId, 'member_id' => $memberId]);
            }
            $connection->commit();
            Notification::notifyMembersByMemberId($memberId, 'Event registration', 'Your event registration was recorded.', 'EVENT_REGISTRATION', 'event', $eventId);
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }

    public static function cancelRegistration(int $eventId, int $memberId): void
    {
        $query = database()->prepare(
            "UPDATE event_registrations er
             INNER JOIN events e ON e.id = er.event_id
             SET er.registration_status = 'CANCELLED'
             WHERE er.event_id = :event_id AND er.member_id = :member_id
               AND er.registration_status = 'REGISTERED'
               AND e.start_datetime > NOW()"
        );
        $query->execute(['event_id' => $eventId, 'member_id' => $memberId]);
        if ($query->rowCount() !== 1) {
            throw new DomainException('This registration cannot be cancelled.');
        }
    }
}
