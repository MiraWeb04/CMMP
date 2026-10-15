USE cmmp_db;

ALTER TABLE members
    ADD COLUMN IF NOT EXISTS baptism_date DATE NULL AFTER country,
    ADD COLUMN IF NOT EXISTS baptism_church VARCHAR(200) NULL AFTER baptism_date,
    ADD COLUMN IF NOT EXISTS baptism_notes TEXT NULL AFTER baptism_church,
    ADD COLUMN IF NOT EXISTS emergency_contact_name VARCHAR(200) NULL AFTER baptism_notes,
    ADD COLUMN IF NOT EXISTS emergency_contact_relationship VARCHAR(100) NULL AFTER emergency_contact_name,
    ADD COLUMN IF NOT EXISTS emergency_contact_phone VARCHAR(30) NULL AFTER emergency_contact_relationship,
    ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(500) NULL AFTER emergency_contact_phone;