USE cmmp_db;

ALTER TABLE appointments
    ADD COLUMN IF NOT EXISTS event_id BIGINT UNSIGNED NULL AFTER service_request_id,
    ADD INDEX IF NOT EXISTS idx_appointments_event (event_id),
    ADD CONSTRAINT fk_appointments_event FOREIGN KEY (event_id) REFERENCES events (id)
        ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE IF NOT EXISTS appointment_staff (
    appointment_id BIGINT UNSIGNED NOT NULL,
    staff_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (appointment_id, staff_id),
    KEY idx_appointment_staff_staff (staff_id),
    CONSTRAINT fk_appointment_staff_appointment FOREIGN KEY (appointment_id) REFERENCES appointments (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_appointment_staff_staff FOREIGN KEY (staff_id) REFERENCES staff (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS scheduling_resource_locks (
    resource_key VARCHAR(180) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (resource_key)
) ENGINE=InnoDB;