<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

final class Attendance
{
    public static function createSession(array $data): int
    {
        $query = database()->prepare(
            'INSERT INTO attendance_sessions
                (event_id, activity_type, title, start_datetime, end_datetime, qr_token_hash, qr_expires_at, session_status, created_by_user_id)
             VALUES (:event_id, :activity_type, :title, :start_datetime, :end_datetime, :qr_token_hash, :qr_expires_at, :session_status, :created_by_user_id)'
        );
        $query->execute($data);
        return (int) database()->lastInsertId();
    }

    public static function findSession(int $id): ?array
    {
        $query = database()->prepare(
            'SELECT ats.*, e.title AS event_title
             FROM attendance_sessions ats LEFT JOIN events e ON e.id = ats.event_id
             WHERE ats.id = :id LIMIT 1'
        );
        $query->execute(['id' => $id]);
        $session = $query->fetch();
        return $session ?: null;
    }

    public static function sessionByQr(string $token): ?array
    {
        $hash = hash('sha256', $token);
        $query = database()->prepare(
            "SELECT * FROM attendance_sessions
             WHERE qr_token_hash = :hash AND session_status = 'OPEN'
               AND (qr_expires_at IS NULL OR qr_expires_at >= NOW()) LIMIT 1"
        );
        $query->execute(['hash' => $hash]);
        $session = $query->fetch();
        return $session ?: null;
    }

    public static function memberIdForUser(int $userId): ?int
    {
        $query = database()->prepare('SELECT id FROM members WHERE user_id = :user_id LIMIT 1');
        $query->execute(['user_id' => $userId]);
        $id = $query->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    public static function record(int $sessionId, int $memberId, string $method, ?int $recorderId): int
    {
        if (!in_array($method, ['MANUAL', 'QR_CODE'], true)) {
            throw new InvalidArgumentException('Invalid attendance method.');
        }
        $session = self::findSession($sessionId);
        if (!$session || $session['session_status'] !== 'OPEN') {
            throw new DomainException('Attendance is not open for this session.');
        }
        if ($session['start_datetime'] > date('Y-m-d H:i:s')) {
            throw new DomainException('Attendance is not open yet.');
        }
        $query = database()->prepare(
            'INSERT INTO attendance_records
                (attendance_session_id, member_id, attendance_method, attendance_status, recorded_by_user_id)
             VALUES (:session_id, :member_id, :method, "PRESENT", :recorder_id)'
        );
        try {
            $query->execute(['session_id' => $sessionId, 'member_id' => $memberId, 'method' => $method, 'recorder_id' => $recorderId]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw new DomainException('Attendance has already been recorded for this member.');
            }
            throw $exception;
        }
        return (int) database()->lastInsertId();
    }

    public static function memberHistory(int $memberId): array
    {
        $query = database()->prepare(
            'SELECT ar.checked_in_at, ar.attendance_method, ar.attendance_status,
                    ats.title, ats.activity_type, ats.start_datetime
             FROM attendance_records ar INNER JOIN attendance_sessions ats ON ats.id = ar.attendance_session_id
             WHERE ar.member_id = :member_id ORDER BY ar.checked_in_at DESC'
        );
        $query->execute(['member_id' => $memberId]);
        return $query->fetchAll();
    }

    public static function adminList(string $search): array
    {
        $where = $search === '' ? '' : 'WHERE ats.title LIKE :search OR m.member_number LIKE :search OR CONCAT(m.first_name, " ", m.last_name) LIKE :search';
        $query = database()->prepare(
            "SELECT ar.id, ar.checked_in_at, ar.attendance_method, ar.attendance_status,
                    ats.title, ats.activity_type, m.member_number,
                    CONCAT(m.first_name, ' ', m.last_name) AS member_name
             FROM attendance_records ar INNER JOIN attendance_sessions ats ON ats.id = ar.attendance_session_id
             INNER JOIN members m ON m.id = ar.member_id $where ORDER BY ar.checked_in_at DESC"
        );
        $query->execute($search === '' ? [] : ['search' => '%' . $search . '%']);
        return $query->fetchAll();
    }

    public static function correct(int $recordId, string $status, int $userId): void
    {
        if (!in_array($status, ['PRESENT', 'ABSENT', 'EXCUSED'], true)) { throw new InvalidArgumentException('Invalid attendance status.'); }
        $query = database()->prepare('UPDATE attendance_records SET attendance_status = :status, recorded_by_user_id = :user_id WHERE id = :id');
        $query->execute(['status' => $status, 'user_id' => $userId, 'id' => $recordId]);
    }
}
