<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
final class Resource
{
    public const TYPES=['SERMON','BIBLE_STUDY','DEVOTIONAL','CHURCH_FORM','MINISTRY_DOCUMENT','EVENT_PROGRAM','OTHER'];
    public const VISIBILITY=['PUBLIC','MEMBERS','STAFF'];

    public static function listForUser(array $roles, string $search = ''): array
    {
        $staff = count(array_intersect($roles, ['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF', 'PASTOR', 'COUNSELOR'])) > 0;
        $where = $staff
            ? 'WHERE r.published_at IS NOT NULL AND r.published_at <= NOW()'
            : 'WHERE r.visibility IN ("PUBLIC", "MEMBERS") AND r.published_at IS NOT NULL AND r.published_at <= NOW()';

        $params = [];
        if ($search !== '') {
            $where .= ' AND (r.title LIKE :search OR r.description LIKE :search OR r.resource_type LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $query = database()->prepare(
            'SELECT r.*
             FROM church_resources r
             ' . $where . '
             ORDER BY r.published_at DESC, r.created_at DESC'
        );
        $query->execute($params);

        return $query->fetchAll();
    }

    public static function find(int $id): ?array { $q=database()->prepare('SELECT * FROM church_resources WHERE id=:id LIMIT 1');$q->execute(['id'=>$id]);$r=$q->fetch();return $r?:null; }
    public static function create(array $data): int { $q=database()->prepare('INSERT INTO church_resources (uploaded_by_user_id,title,description,resource_type,storage_path,mime_type,file_size_bytes,visibility,published_at) VALUES (:user_id,:title,:description,:type,:path,:mime,:size,:visibility,:published_at)');$q->execute($data);return (int)database()->lastInsertId(); }
    public static function update(int $id,array $data): void { $q=database()->prepare('UPDATE church_resources SET title=:title,description=:description,resource_type=:type,visibility=:visibility,published_at=:published_at WHERE id=:id');$q->execute([...$data,'id'=>$id]); }
    public static function delete(int $id): void { database()->prepare('DELETE FROM church_resources WHERE id=:id')->execute(['id'=>$id]); }
}
