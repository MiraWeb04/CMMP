USE cmmp_db;

ALTER TABLE facilities
    ADD COLUMN IF NOT EXISTS facility_type VARCHAR(50) NOT NULL DEFAULT 'OTHER' AFTER location;