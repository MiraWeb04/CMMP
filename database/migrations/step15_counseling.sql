USE cmmp_db;

CREATE TABLE IF NOT EXISTS counseling_cases (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    service_request_id BIGINT UNSIGNED NOT NULL,
    status ENUM('NOT_STARTED', 'IN_PROGRESS', 'COMPLETED', 'NOT_REQUIRED') NOT NULL DEFAULT 'NOT_STARTED',
    required_sessions INT UNSIGNED NOT NULL DEFAULT 3,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_counseling_cases_request (service_request_id),
    CONSTRAINT fk_counseling_cases_request FOREIGN KEY (service_request_id) REFERENCES service_requests (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS counseling_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    counseling_case_id BIGINT UNSIGNED NOT NULL,
    session_number INT UNSIGNED NOT NULL,
    appointment_id BIGINT UNSIGNED NULL,
    status ENUM('PLANNED', 'COMPLETED', 'CANCELLED') NOT NULL DEFAULT 'PLANNED',
    private_notes TEXT NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_counseling_sessions_number (counseling_case_id, session_number),
    UNIQUE KEY uq_counseling_sessions_appointment (appointment_id),
    KEY idx_counseling_sessions_status (status),
    CONSTRAINT fk_counseling_sessions_case FOREIGN KEY (counseling_case_id) REFERENCES counseling_cases (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_counseling_sessions_appointment FOREIGN KEY (appointment_id) REFERENCES appointments (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;