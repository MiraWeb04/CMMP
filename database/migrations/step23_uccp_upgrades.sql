USE cmmp_db;

INSERT INTO service_types (code, name, description, is_active)
VALUES
    ('MANANITA', 'Mañanita', 'Birthday surprise worship service (mañanita) request', 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    is_active = 1,
    updated_at = CURRENT_TIMESTAMP;

INSERT INTO ministries (code, name, description, ministry_status)
VALUES
    ('CYF', 'Christian Youth Fellowship (CYF)', 'Fellowship organization for members aged 12–29.', 'ACTIVE'),
    ('CYAF', 'Christian Young Adult Fellowship (CYAF)', 'Fellowship organization for members aged 30–49.', 'ACTIVE'),
    ('UCM', 'United Churchmen (UCM)', 'Fellowship organization for male members aged 50 and above.', 'ACTIVE'),
    ('CWA', 'Church Women''s Association (CWA)', 'Fellowship organization for female members aged 50 and above.', 'ACTIVE')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    ministry_status = 'ACTIVE',
    updated_at = CURRENT_TIMESTAMP;
