<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/http.php';
require_once __DIR__ . '/AuditLog.php';

final class User
{
    public const RESET_TTL_MINUTES = 60;
    public const LOCKOUT_WINDOW_MINUTES = 15;
    public const LOCKOUT_THRESHOLD = 5;

    public static function ensureSecurityTables(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }

        $pdo = database();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS login_attempts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                identifier VARCHAR(255) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                was_successful TINYINT(1) NOT NULL DEFAULT 0,
                attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_login_attempts_lookup (identifier, attempted_at),
                KEY idx_login_attempts_ip (ip_address, attempted_at)
            ) ENGINE=InnoDB'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS password_reset_tokens (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_password_reset_hash (token_hash),
                KEY idx_password_reset_user (user_id, expires_at),
                CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB'
        );
        $ready = true;
    }

    public static function paginate(string $search, string $status, int $page, int $perPage): array
    {
        $conditions = [];
        $parameters = [];
        if ($search !== '') {
            $term = '%' . $search . '%';
            $conditions[] = '(u.username LIKE :search_username OR u.email LIKE :search_email)';
            $parameters['search_username'] = $term;
            $parameters['search_email'] = $term;
        }
        if ($status !== '') {
            $conditions[] = 'u.status = :status';
            $parameters['status'] = $status;
        }
        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

        $count = database()->prepare("SELECT COUNT(*) FROM users u $where");
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();

        $query = database()->prepare(
            "SELECT u.id, u.username, u.email, u.role, u.status, u.last_login_at,
                    COALESCE(u.role, GROUP_CONCAT(r.code ORDER BY r.code SEPARATOR ', ')) AS roles,
                    GROUP_CONCAT(ur.role_id ORDER BY ur.role_id SEPARATOR ',') AS role_ids
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             $where
             GROUP BY u.id
             ORDER BY u.username ASC, u.id ASC
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
        $query = database()->prepare(
            'SELECT id, username, email, status, last_login_at FROM users WHERE id = :id LIMIT 1'
        );
        $query->execute(['id' => $id]);
        $user = $query->fetch();
        return $user ?: null;
    }

    public static function roleCodes(int $id): array
    {
        $query = database()->prepare(
            'SELECT r.code
             FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :id'
        );
        $query->execute(['id' => $id]);
        return array_column($query->fetchAll(), 'code');
    }

    public static function isLastActiveSuperAdmin(int $id): bool
    {
        if (!in_array('SUPER_ADMIN', self::roleCodes($id), true)) {
            return false;
        }
        $query = database()->prepare(
            'SELECT COUNT(*)
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE r.code = "SUPER_ADMIN"
               AND u.status = "ACTIVE"
               AND u.id <> :id'
        );
        $query->execute(['id' => $id]);

        return (int) $query->fetchColumn() === 0;
    }

    public static function setStatus(int $id, string $status, ?int $actorId = null): void
    {
        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'LOCKED', 'PENDING'], true)) {
            throw new InvalidArgumentException('Invalid user status.');
        }
        $user = self::find($id);
        if (!$user) {
            throw new InvalidArgumentException('User not found.');
        }
        if ($actorId && $actorId === $id && $status !== 'ACTIVE') {
            throw new InvalidArgumentException('You cannot deactivate your own account.');
        }
        if ($status !== 'ACTIVE' && self::isLastActiveSuperAdmin($id)) {
            throw new InvalidArgumentException('The last active super administrator cannot be deactivated.');
        }

        $query = database()->prepare('UPDATE users SET status = :status WHERE id = :id');
        $query->execute(['id' => $id, 'status' => $status]);
        AuditLog::record(
            $actorId,
            $status === 'ACTIVE' ? 'USER_REACTIVATED' : 'USER_DEACTIVATED',
            'USER',
            $id,
            ['status' => $status]
        );
    }

    public static function deactivate(int $id, ?int $actorId = null): void
    {
        self::setStatus($id, 'INACTIVE', $actorId);
    }

    public static function reactivate(int $id, ?int $actorId = null): void
    {
        self::setStatus($id, 'ACTIVE', $actorId);
    }

    public static function relationshipCounts(int $id): array
    {
        $pdo = database();
        $count = static function (string $sql) use ($pdo, $id): int {
            $query = $pdo->prepare($sql);
            $query->execute(['id' => $id]);
            return (int) $query->fetchColumn();
        };

        return [
            'members' => $count('SELECT COUNT(*) FROM members WHERE user_id = :id'),
            'staff' => $count('SELECT COUNT(*) FROM staff WHERE user_id = :id'),
            'events' => $count('SELECT COUNT(*) FROM events WHERE created_by_user_id = :id'),
            'announcements' => $count('SELECT COUNT(*) FROM announcements WHERE created_by_user_id = :id'),
            'reviewed_requests' => $count('SELECT COUNT(*) FROM service_requests WHERE reviewed_by_user_id = :id'),
        ];
    }

    public static function canPermanentlyDelete(int $id): bool
    {
        $user = self::find($id);
        if (!$user) {
            return false;
        }
        if (!in_array((string) $user['status'], ['PENDING', 'INACTIVE'], true)) {
            return false;
        }
        if (self::isLastActiveSuperAdmin($id)) {
            return false;
        }
        $counts = self::relationshipCounts($id);

        return (int) $counts['members'] === 0
            && (int) $counts['staff'] === 0
            && (int) $counts['events'] === 0
            && (int) $counts['announcements'] === 0
            && (int) $counts['reviewed_requests'] === 0;
    }

    public static function deletePermanently(int $id, int $actorId): void
    {
        if ($actorId === $id) {
            throw new InvalidArgumentException('You cannot delete your own account.');
        }
        if (!self::canPermanentlyDelete($id)) {
            throw new InvalidArgumentException(
                'This account is linked to church records and can only be deactivated.'
            );
        }
        $delete = database()->prepare('DELETE FROM users WHERE id = :id');
        $delete->execute(['id' => $id]);
        if ($delete->rowCount() !== 1) {
            throw new RuntimeException('The user could not be deleted.');
        }
        AuditLog::record($actorId, 'USER_DELETED', 'USER', $id);
    }

    public static function findByEmail(string $email): ?array
    {
        $query = database()->prepare(
            'SELECT id, username, email, status FROM users WHERE email = :email LIMIT 1'
        );
        $query->execute(['email' => $email]);
        $user = $query->fetch();
        return $user ?: null;
    }

    public static function emailTaken(string $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM users WHERE email = :email';
        $parameters = ['email' => $email];
        if ($exceptId) {
            $sql .= ' AND id <> :id';
            $parameters['id'] = $exceptId;
        }
        $query = database()->prepare($sql . ' LIMIT 1');
        $query->execute($parameters);
        return (bool) $query->fetchColumn();
    }

    public static function updateEmail(int $id, string $email): void
    {
        $query = database()->prepare('UPDATE users SET email = :email WHERE id = :id');
        $query->execute(['id' => $id, 'email' => $email]);
    }

    public static function updatePassword(int $id, string $password): void
    {
        $query = database()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $query->execute(['id' => $id, 'hash' => password_hash($password, PASSWORD_DEFAULT)]);
    }

    public static function verifyPassword(int $id, string $password): bool
    {
        $query = database()->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
        $query->execute(['id' => $id]);
        $hash = $query->fetchColumn();
        return is_string($hash) && password_verify($password, $hash);
    }

    public static function isTemporarilyLocked(string $identifier, string $ipAddress): bool
    {
        self::ensureSecurityTables();
        $minutes = self::LOCKOUT_WINDOW_MINUTES;
        $query = database()->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE was_successful = 0
               AND attempted_at >= DATE_SUB(NOW(), INTERVAL {$minutes} MINUTE)
               AND (identifier = :identifier OR ip_address = :ip_address)"
        );
        $query->execute([
            'identifier' => mb_strtolower(trim($identifier)),
            'ip_address' => $ipAddress,
        ]);

        return (int) $query->fetchColumn() >= self::LOCKOUT_THRESHOLD;
    }

    public static function recordLoginAttempt(string $identifier, string $ipAddress, bool $successful): void
    {
        self::ensureSecurityTables();
        $query = database()->prepare(
            'INSERT INTO login_attempts (identifier, ip_address, was_successful)
             VALUES (:identifier, :ip_address, :was_successful)'
        );
        $query->execute([
            'identifier' => mb_strtolower(trim($identifier)),
            'ip_address' => $ipAddress,
            'was_successful' => $successful ? 1 : 0,
        ]);

        if ($successful) {
            $minutes = self::LOCKOUT_WINDOW_MINUTES;
            $clear = database()->prepare(
                "UPDATE login_attempts SET was_successful = 1
                 WHERE identifier = :identifier AND was_successful = 0
                   AND attempted_at >= DATE_SUB(NOW(), INTERVAL {$minutes} MINUTE)"
            );
            $clear->execute(['identifier' => mb_strtolower(trim($identifier))]);
        }
    }

    public static function createPasswordReset(int $userId): string
    {
        self::ensureSecurityTables();
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        database()->prepare(
            'UPDATE password_reset_tokens SET used_at = NOW()
             WHERE user_id = :user_id AND used_at IS NULL'
        )->execute(['user_id' => $userId]);

        $minutes = self::RESET_TTL_MINUTES;
        $insert = database()->prepare(
            "INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
             VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL {$minutes} MINUTE))"
        );
        $insert->execute([
            'user_id' => $userId,
            'token_hash' => $hash,
        ]);

        return $token;
    }

    public static function consumePasswordReset(string $token, string $password): bool
    {
        self::ensureSecurityTables();
        $hash = hash('sha256', $token);
        $query = database()->prepare(
            'SELECT id, user_id FROM password_reset_tokens
             WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > NOW()
             LIMIT 1'
        );
        $query->execute(['token_hash' => $hash]);
        $row = $query->fetch();
        if (!$row) {
            return false;
        }

        $pdo = database();
        $pdo->beginTransaction();
        try {
            self::updatePassword((int) $row['user_id'], $password);
            $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = :id')
                ->execute(['id' => $row['id']]);
            $pdo->prepare("UPDATE users SET status = 'ACTIVE' WHERE id = :id AND status = 'LOCKED'")
                ->execute(['id' => $row['user_id']]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        AuditLog::record((int) $row['user_id'], 'PASSWORD_RESET', 'USER', (int) $row['user_id']);
        return true;
    }
}
