USE cmmp_db;

CREATE TABLE IF NOT EXISTS event_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_event_categories_name (name)
) ENGINE=InnoDB;

ALTER TABLE events
    ADD COLUMN IF NOT EXISTS category_id BIGINT UNSIGNED NULL AFTER created_by_user_id,
    ADD COLUMN IF NOT EXISTS facility_id BIGINT UNSIGNED NULL AFTER category_id,
    ADD COLUMN IF NOT EXISTS registration_deadline DATETIME NULL AFTER registration_required,
    ADD COLUMN IF NOT EXISTS event_image VARCHAR(500) NULL AFTER registration_deadline;

ALTER TABLE events
    ADD INDEX IF NOT EXISTS idx_events_category (category_id),
    ADD INDEX IF NOT EXISTS idx_events_facility (facility_id);

ALTER TABLE events
    ADD CONSTRAINT fk_events_category FOREIGN KEY (category_id) REFERENCES event_categories (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_events_facility FOREIGN KEY (facility_id) REFERENCES facilities (id)
        ON DELETE SET NULL ON UPDATE CASCADE;

INSERT INTO event_categories (name, description)
VALUES
    ('Worship', 'Church worship and service gatherings'),
    ('Fellowship', 'Community fellowship and social gatherings'),
    ('Outreach', 'Church outreach and community activities'),
    ('Training', 'Training, classes, and workshops'),
    ('Youth', 'Youth-focused church activities'),
    ('Other', 'Other church events')
ON DUPLICATE KEY UPDATE
    description = VALUES(description),
    updated_at = CURRENT_TIMESTAMP;