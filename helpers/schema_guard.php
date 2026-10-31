<?php
declare(strict_types=1);

function cmmp_ensure_schema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $ready = true;

    $columns = [
        ['service_requests', 'hidden_from_member_at', 'DATETIME NULL'],
        ['ministries', 'leader_staff_id', 'BIGINT UNSIGNED NULL'],
        ['events', 'ministry_id', 'BIGINT UNSIGNED NULL'],
        ['events', 'event_image', 'VARCHAR(500) NULL'],
        ['users', 'role', 'VARCHAR(50) NULL'],
        ['users', 'last_login_at', 'DATETIME NULL'],
        ['members', 'preferred_name', 'VARCHAR(100) NULL'],
    ];

    foreach ($columns as [$table, $column, $definition]) {
        cmmp_schema_add_column($pdo, $table, $column, $definition);
    }

    cmmp_schema_exec(
        $pdo,
        'CREATE TABLE IF NOT EXISTS login_attempts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            identifier VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            was_successful TINYINT(1) NOT NULL DEFAULT 0,
            attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_login_attempts_lookup (identifier, attempted_at)
        ) ENGINE=InnoDB'
    );
    cmmp_schema_exec(
        $pdo,
        'CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_password_reset_hash (token_hash),
            KEY idx_password_reset_user (user_id)
        ) ENGINE=InnoDB'
    );
    cmmp_schema_exec(
        $pdo,
        'CREATE TABLE IF NOT EXISTS attendance_sessions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id BIGINT UNSIGNED NULL,
            activity_type ENUM("EVENT", "WORSHIP_SERVICE", "MINISTRY_ACTIVITY", "OTHER") NOT NULL,
            title VARCHAR(200) NOT NULL,
            start_datetime DATETIME NOT NULL,
            end_datetime DATETIME NULL,
            qr_token_hash CHAR(64) NULL,
            qr_expires_at DATETIME NULL,
            session_status ENUM("OPEN", "CLOSED", "CANCELLED") NOT NULL DEFAULT "OPEN",
            created_by_user_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_attendance_sessions_qr_token (qr_token_hash),
            KEY idx_attendance_sessions_activity (activity_type, start_datetime),
            KEY idx_attendance_sessions_event (event_id)
        ) ENGINE=InnoDB'
    );
    cmmp_schema_exec(
        $pdo,
        'CREATE TABLE IF NOT EXISTS attendance_records (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            attendance_session_id BIGINT UNSIGNED NOT NULL,
            member_id BIGINT UNSIGNED NOT NULL,
            attendance_method ENUM("MANUAL", "QR_CODE") NOT NULL,
            attendance_status ENUM("PRESENT", "ABSENT", "EXCUSED") NOT NULL DEFAULT "PRESENT",
            recorded_by_user_id BIGINT UNSIGNED NULL,
            checked_in_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_attendance_records_session_member (attendance_session_id, member_id),
            KEY idx_attendance_records_member (member_id, checked_in_at)
        ) ENGINE=InnoDB'
    );
    cmmp_schema_exec(
        $pdo,
        'CREATE TABLE IF NOT EXISTS event_service_blocks (
            event_category_id BIGINT UNSIGNED NOT NULL,
            service_type_code VARCHAR(50) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (event_category_id, service_type_code)
        ) ENGINE=InnoDB'
    );
}

function cmmp_schema_add_column(PDO $pdo, string $table, string $column, string $definition): void
{
    try {
        $found = $pdo->query('SHOW COLUMNS FROM `' . $table . '` LIKE ' . $pdo->quote($column))->fetch();
        if (!$found) {
            $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
        }
    } catch (Throwable $exception) {
        error_log('CMMP schema guard skipped ' . $table . '.' . $column . ': ' . $exception->getMessage());
    }
}

function cmmp_schema_exec(PDO $pdo, string $sql): void
{
    try {
        $pdo->exec($sql);
    } catch (Throwable $exception) {
        error_log('CMMP schema guard skipped statement: ' . $exception->getMessage());
    }
}
