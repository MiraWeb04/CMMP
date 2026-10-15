-- Instructor documentation upgrades:
-- Facility reservation, re-enable baptism scheduling, and retire Sunday Worship and Other.

UPDATE service_types
SET is_active = 1,
    name = 'Baptism',
    description = 'Baptism service request',
    updated_at = CURRENT_TIMESTAMP
WHERE code = 'BAPTISM';

UPDATE service_types
SET is_active = 0,
    updated_at = CURRENT_TIMESTAMP
WHERE code IN ('OTHER', 'SUNDAY_WORSHIP');

INSERT INTO service_types (code, name, description, is_active)
VALUES
    ('FACILITY_RESERVATION', 'Facility Reservation', 'Request the use of a church facility for an approved church event', 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    is_active = 1,
    updated_at = CURRENT_TIMESTAMP;
