<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/datetime.php';
require_once __DIR__ . '/AuditLog.php';
final class Announcement
{
    public const AUDIENCES=['ALL','MEMBERS','STAFF','PASTORS','COUNSELORS','MINISTRY'];
    public static function listForUser(array $roles): array
    {
        $audiences = ['ALL'];
        foreach ($roles as $role) {
            $audiences[] = match ($role) {
                'MEMBER' => 'MEMBERS',
                'PASTOR' => 'PASTORS',
                'COUNSELOR' => 'COUNSELORS',
                'MINISTRY_LEADER' => 'MINISTRY',
                'SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF' => 'STAFF',
                default => ''
            };
        }
        $audiences = array_values(array_unique(array_filter($audiences)));
        if ($audiences === []) {
            return [];
        }

        // Existing announcement DATETIME values are naive Asia/Manila wall-clock
        // timestamps written by PHP. Compare against the application clock, not
        // MySQL NOW(), because the database session may be UTC.
        $now = app_now_sql();
        $placeholders = implode(',', array_fill(0, count($audiences), '?'));
        $query = database()->prepare(
            'SELECT *
             FROM announcements
             WHERE announcement_status = "PUBLISHED"
               AND published_at IS NOT NULL
               AND published_at <= ?
               AND (expires_at IS NULL OR expires_at > ?)
               AND (audience_role IS NULL OR audience_role = "ALL" OR audience_role IN (' . $placeholders . '))
             ORDER BY published_at DESC, created_at DESC'
        );
        $query->execute(array_merge([$now, $now], $audiences));
        return $query->fetchAll();
    }
    public static function all(): array { return database()->query('SELECT * FROM announcements ORDER BY created_at DESC')->fetchAll(); }

    public static function paginate(int $page, int $perPage, string $status = ''): array
    {
        $status = strtoupper(trim($status));
        $where = '';
        $params = [];
        if (in_array($status, ['DRAFT', 'PUBLISHED', 'ARCHIVED'], true)) {
            $where = 'WHERE announcement_status = :status';
            $params['status'] = $status;
        }
        $count = database()->prepare('SELECT COUNT(*) FROM announcements ' . $where);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $query = database()->prepare(
            'SELECT * FROM announcements ' . $where . ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $query->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $query->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $query->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $query->execute();
        return ['items' => $query->fetchAll(), 'total' => $total];
    }

    public static function archive(int $id, ?int $userId = null): void
    {
        $current = database()->prepare('SELECT announcement_status FROM announcements WHERE id = :id LIMIT 1');
        $current->execute(['id' => $id]);
        $status = $current->fetchColumn();
        if ($status === false) {
            throw new InvalidArgumentException('Announcement not found.');
        }
        if ((string) $status === 'ARCHIVED') {
            throw new InvalidArgumentException('This announcement is already archived.');
        }
        $query = database()->prepare('UPDATE announcements SET announcement_status = "ARCHIVED" WHERE id = :id');
        $query->execute(['id' => $id]);
        AuditLog::record($userId, 'ANNOUNCEMENT_ARCHIVED', 'ANNOUNCEMENT', $id);
    }

    public static function restore(int $id, ?int $userId = null): void
    {
        $current = database()->prepare(
            'SELECT announcement_status, published_at FROM announcements WHERE id = :id LIMIT 1'
        );
        $current->execute(['id' => $id]);
        $row = $current->fetch();
        if (!$row) {
            throw new InvalidArgumentException('Announcement not found.');
        }
        if ((string) $row['announcement_status'] !== 'ARCHIVED') {
            throw new InvalidArgumentException('Only archived announcements can be restored.');
        }
        $status = $row['published_at'] !== null ? 'PUBLISHED' : 'DRAFT';
        $query = database()->prepare('UPDATE announcements SET announcement_status = :status WHERE id = :id');
        $query->execute(['status' => $status, 'id' => $id]);
        AuditLog::record($userId, 'ANNOUNCEMENT_RESTORED', 'ANNOUNCEMENT', $id, ['status' => $status]);
    }
    public static function save(array $data, ?int $id=null): int
    {
        require_once __DIR__ . '/../services/NotificationService.php';
        $wasPublished = false;
        $publishedAt = store_app_datetime($data['published_at'] ?? null);
        $expiresAt = store_app_datetime($data['expires_at'] ?? null);

        if ($id) {
            $current = database()->prepare('SELECT announcement_status FROM announcements WHERE id = :id LIMIT 1');
            $current->execute(['id' => $id]);
            $wasPublished = (string) $current->fetchColumn() === 'PUBLISHED';
            $q = database()->prepare('UPDATE announcements SET title=:title,body=:body,audience_role=:audience_role,published_at=:published_at,expires_at=:expires_at,announcement_status=:status WHERE id=:id');
            $q->execute([
                'title' => $data['title'],
                'body' => $data['body'],
                'audience_role' => $data['audience_role'],
                'published_at' => $publishedAt,
                'expires_at' => $expiresAt,
                'status' => $data['status'],
                'id' => $id,
            ]);
        } else {
            $q = database()->prepare('INSERT INTO announcements (created_by_user_id,title,body,audience_role,published_at,expires_at,announcement_status) VALUES (:user_id,:title,:body,:audience_role,:published_at,:expires_at,:status)');
            $q->execute([
                'user_id' => $data['user_id'],
                'title' => $data['title'],
                'body' => $data['body'],
                'audience_role' => $data['audience_role'],
                'published_at' => $publishedAt,
                'expires_at' => $expiresAt,
                'status' => $data['status'],
            ]);
            $id = (int) database()->lastInsertId();
        }

        if ($data['status'] === 'PUBLISHED') {
            AuditLog::record($data['user_id'] ?? null, 'ANNOUNCEMENT_PUBLISHED', 'ANNOUNCEMENT', $id);
            if (!$wasPublished) {
                NotificationService::notifyAnnouncementPublished($id, isset($data['user_id']) ? (int) $data['user_id'] : null);
            }
        }

        return $id;
    }
}
