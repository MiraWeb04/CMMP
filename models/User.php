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
