<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
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

        $placeholders = implode(',', array_fill(0, count($audiences), '?'));
        $query = database()->prepare(
            'SELECT *
             FROM announcements
             WHERE announcement_status = "PUBLISHED"
               AND published_at IS NOT NULL
               AND published_at <= NOW()
               AND (expires_at IS NULL OR expires_at > NOW())
               AND (audience_role IS NULL OR audience_role = "ALL" OR audience_role IN (' . $placeholders . '))
             ORDER BY published_at DESC, created_at DESC'
        );
        $query->execute($audiences);
        return $query->fetchAll();
    }
    public static function all(): array { return database()->query('SELECT * FROM announcements ORDER BY created_at DESC')->fetchAll(); }
    public static function save(array $data, ?int $id=null): int
    {
        require_once __DIR__ . '/../services/NotificationService.php';
        $wasPublished = false;
        if ($id) {
            $current = database()->prepare('SELECT announcement_status FROM announcements WHERE id = :id LIMIT 1');
            $current->execute(['id' => $id]);
            $wasPublished = (string) $current->fetchColumn() === 'PUBLISHED';
            $q = database()->prepare('UPDATE announcements SET title=:title,body=:body,audience_role=:audience_role,published_at=:published_at,expires_at=:expires_at,announcement_status=:status WHERE id=:id');
            $q->execute([
                'title' => $data['title'],
                'body' => $data['body'],
                'audience_role' => $data['audience_role'],
                'published_at' => $data['published_at'],
                'expires_at' => $data['expires_at'],
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
                'published_at' => $data['published_at'],
                'expires_at' => $data['expires_at'],
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
