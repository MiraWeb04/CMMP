USE cmmp_db;

CREATE TABLE IF NOT EXISTS event_service_blocks (
    event_category_id BIGINT UNSIGNED NOT NULL,
    service_type_code VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (event_category_id, service_type_code),
    CONSTRAINT fk_event_service_blocks_category FOREIGN KEY (event_category_id) REFERENCES event_categories (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

INSERT INTO event_service_blocks (event_category_id, service_type_code)
SELECT c.id, 'FACILITY_RESERVATION'
FROM event_categories c
WHERE c.name = 'Worship'
ON DUPLICATE KEY UPDATE service_type_code = VALUES(service_type_code);

INSERT INTO event_service_blocks (event_category_id, service_type_code)
SELECT c.id, 'MANANITA'
FROM event_categories c
WHERE c.name = 'Worship'
ON DUPLICATE KEY UPDATE service_type_code = VALUES(service_type_code);

INSERT INTO event_service_blocks (event_category_id, service_type_code)
SELECT c.id, 'FACILITY_RESERVATION'
FROM event_categories c
WHERE c.name = 'Youth'
ON DUPLICATE KEY UPDATE service_type_code = VALUES(service_type_code);
