USE cmmp_db;

ALTER TABLE service_requests
    ADD COLUMN IF NOT EXISTS request_data JSON NULL AFTER description,
    ADD COLUMN IF NOT EXISTS preferred_start_datetime DATETIME NULL AFTER request_data,
    ADD COLUMN IF NOT EXISTS preferred_end_datetime DATETIME NULL AFTER preferred_start_datetime,
    ADD COLUMN IF NOT EXISTS preferred_facility_id BIGINT UNSIGNED NULL AFTER preferred_end_datetime;

ALTER TABLE service_requests
    ADD INDEX IF NOT EXISTS idx_service_requests_preferred_schedule (preferred_facility_id, preferred_start_datetime, preferred_end_datetime),
    ADD CONSTRAINT fk_service_requests_preferred_facility FOREIGN KEY (preferred_facility_id) REFERENCES facilities (id)
        ON DELETE SET NULL ON UPDATE CASCADE;