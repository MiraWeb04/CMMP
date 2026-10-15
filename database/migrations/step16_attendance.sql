USE cmmp_db;

CREATE TABLE IF NOT EXISTS attendance_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id BIGINT UNSIGNED NULL,
    activity_type ENUM('EVENT', 'WORSHIP_SERVICE', 'MINISTRY_ACTIVITY', 'OTHER') NOT NULL,
    title VARCHAR(200) NOT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NULL,
    qr_token_hash CHAR(64) NULL,
    qr_expires_at DATETIME NULL,
    session_status ENUM('OPEN', 'CLOSED', 'CANCELLED') NOT NULL DEFAULT 'OPEN',
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_attendance_sessions_qr_token (qr_token_hash),
    KEY idx_attendance_sessions_activity (activity_type, start_datetime),
    KEY idx_attendance_sessions_event (event_id),
    CONSTRAINT fk_attendance_sessions_event FOREIGN KEY (event_id) REFERENCES events (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_attendance_sessions_creator FOREIGN KEY (created_by_user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attendance_records (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    attendance_session_id BIGINT UNSIGNED NOT NULL,
    member_id BIGINT UNSIGNED NOT NULL,
    attendance_method ENUM('MANUAL', 'QR_CODE') NOT NULL,
    attendance_status ENUM('PRESENT', 'ABSENT', 'EXCUSED') NOT NULL DEFAULT 'PRESENT',
    recorded_by_user_id BIGINT UNSIGNED NULL,
    checked_in_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_attendance_records_session_member (attendance_session_id, member_id),
    KEY idx_attendance_records_member (member_id, checked_in_at),
    CONSTRAINT fk_attendance_records_session FOREIGN KEY (attendance_session_id) REFERENCES attendance_sessions (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_attendance_records_member FOREIGN KEY (member_id) REFERENCES members (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_attendance_records_recorder FOREIGN KEY (recorded_by_user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;