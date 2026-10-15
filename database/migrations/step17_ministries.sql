USE cmmp_db;

ALTER TABLE ministries
    ADD COLUMN IF NOT EXISTS leader_staff_id BIGINT UNSIGNED NULL AFTER code,
    ADD INDEX IF NOT EXISTS idx_ministries_leader (leader_staff_id),
    ADD CONSTRAINT fk_ministries_leader FOREIGN KEY (leader_staff_id) REFERENCES staff (id)
        ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE events
    ADD COLUMN IF NOT EXISTS ministry_id BIGINT UNSIGNED NULL AFTER facility_id,
    ADD INDEX IF NOT EXISTS idx_events_ministry (ministry_id),
    ADD CONSTRAINT fk_events_ministry FOREIGN KEY (ministry_id) REFERENCES ministries (id)
        ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE IF NOT EXISTS ministry_join_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ministry_id BIGINT UNSIGNED NOT NULL,
    member_id BIGINT UNSIGNED NOT NULL,
    request_status ENUM('PENDING', 'APPROVED', 'REJECTED', 'CANCELLED') NOT NULL DEFAULT 'PENDING',
    reviewed_by_user_id BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ministry_join_request (ministry_id, member_id),
    KEY idx_ministry_join_status (request_status),
    CONSTRAINT fk_ministry_join_ministry FOREIGN KEY (ministry_id) REFERENCES ministries (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ministry_join_member FOREIGN KEY (member_id) REFERENCES members (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ministry_join_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;