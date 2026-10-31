<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
final class Notification
{
    public static function create(int $userId,string $title,string $message,string $type,?string $referenceType=null,?int $referenceId=null): void { database()->prepare('INSERT INTO notifications (user_id,title,message,notification_type,reference_type,reference_id) VALUES (:user_id,:title,:message,:type,:reference_type,:reference_id)')->execute(['user_id'=>$userId,'title'=>$title,'message'=>$message,'type'=>$type,'reference_type'=>$referenceType,'reference_id'=>$referenceId]); }
    public static function forUser(int $userId): array { $q=database()->prepare('SELECT * FROM notifications WHERE user_id=:user_id ORDER BY created_at DESC');$q->execute(['user_id'=>$userId]);return $q->fetchAll(); }

    public static function filtered(int $userId, array $filters): array
    {
        $where = ['user_id = :user_id'];
        $params = ['user_id' => $userId];
        $status = (string) ($filters['status'] ?? 'all');
        if ($status === 'unread') {
            $where[] = 'read_at IS NULL';
        } elseif ($status === 'read') {
            $where[] = 'read_at IS NOT NULL';
        }
        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '') {
            $where[] = 'notification_type = :type';
            $params['type'] = $type;
        }
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $term = '%' . $search . '%';
            $where[] = '(title LIKE :search_title OR message LIKE :search_message)';
            $params['search_title'] = $term;
            $params['search_message'] = $term;
        }
        $since = (string) ($filters['since'] ?? '');
        if ($since !== '') {
            $where[] = 'created_at >= :since';
            $params['since'] = $since;
        }
        $query = database()->prepare(
            'SELECT * FROM notifications WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC, id DESC'
        );
        $query->execute($params);
        return $query->fetchAll();
    }

    public static function typesForUser(int $userId): array
    {
        $query = database()->prepare(
            'SELECT DISTINCT notification_type
             FROM notifications
             WHERE user_id = :user_id AND notification_type IS NOT NULL AND notification_type <> ""
             ORDER BY notification_type'
        );
        $query->execute(['user_id' => $userId]);
        return array_values(array_filter(array_map(static fn (array $row): string => (string) $row['notification_type'], $query->fetchAll())));
    }

    public static function markRead(int $id,int $userId): void { database()->prepare('UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE id=:id AND user_id=:user_id')->execute(['id'=>$id,'user_id'=>$userId]); }

    public static function findForUser(int $id, int $userId): ?array
    {
        $query = database()->prepare(
            'SELECT * FROM notifications WHERE id = :id AND user_id = :user_id LIMIT 1'
        );
        $query->execute(['id' => $id, 'user_id' => $userId]);
        $notification = $query->fetch();

        return $notification ?: null;
    }

    public static function deleteReadForUser(int $id, int $userId): void
    {
        $notification = self::findForUser($id, $userId);
        if (!$notification) {
            throw new InvalidArgumentException('Notification not found.');
        }
        if (empty($notification['read_at'])) {
            throw new InvalidArgumentException('Mark this notification as read before deleting it.');
        }

        $delete = database()->prepare(
            'DELETE FROM notifications
             WHERE id = :id AND user_id = :user_id AND read_at IS NOT NULL'
        );
        $delete->execute(['id' => $id, 'user_id' => $userId]);
        if ($delete->rowCount() !== 1) {
            throw new RuntimeException('The notification could not be deleted.');
        }
    }

    public static function markAllRead(int $userId): void
    {
        database()->prepare('UPDATE notifications SET read_at = COALESCE(read_at, NOW()) WHERE user_id = :user_id AND read_at IS NULL')->execute(['user_id' => $userId]);
    }
    public static function unreadCount(int $userId): int { $q=database()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=:user_id AND read_at IS NULL');$q->execute(['user_id'=>$userId]);return (int)$q->fetchColumn(); }
    public static function notifyMembersByMemberId(int $memberId,string $title,string $message,string $type,?string $ref=null,?int $refId=null): void { $q=database()->prepare('SELECT user_id FROM members WHERE id=:id AND user_id IS NOT NULL');$q->execute(['id'=>$memberId]);$userId=$q->fetchColumn();if($userId)self::create((int)$userId,$title,$message,$type,$ref,$refId); }

    public static function notifyAdminRolesForNewServiceRequest(int $requestId, int $memberId, string $serviceName): void
    {
        $memberQuery = database()->prepare('SELECT CONCAT(first_name, " ", last_name) AS member_name FROM members WHERE id = :id LIMIT 1');
        $memberQuery->execute(['id' => $memberId]);
        $memberName = (string) $memberQuery->fetchColumn();
        if ($memberName === '') { $memberName = 'Member'; }

        $message = 'Member ' . $memberName . ' submitted a request for ' . $serviceName . '.';
        $adminQuery = database()->prepare(
            'SELECT DISTINCT u.id
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE u.status = :status
               AND r.code IN (\'SUPER_ADMIN\', \'CHURCH_ADMIN\')'
        );
        $adminQuery->execute(['status' => 'ACTIVE']);
        $admins = $adminQuery->fetchAll();

        foreach ($admins as $admin) {
            self::create((int) $admin['id'], 'New Church Service Request', $message, 'SERVICE_REQUEST_NEW', 'service_request', $requestId);
        }
    }

    public static function notifyAdministrators(string $title, string $message, string $type, ?string $referenceType = null, ?int $referenceId = null): void
    {
        $adminQuery = database()->prepare(
            'SELECT DISTINCT u.id
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE u.status = :status
               AND r.code IN (\'SUPER_ADMIN\', \'CHURCH_ADMIN\')'
        );
        $adminQuery->execute(['status' => 'ACTIVE']);
        foreach ($adminQuery->fetchAll() as $admin) {
            self::create((int) $admin['id'], $title, $message, $type, $referenceType, $referenceId);
        }
    }
}
