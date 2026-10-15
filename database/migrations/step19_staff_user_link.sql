ALTER TABLE staff
    ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER id,
    ADD UNIQUE KEY uq_staff_user (user_id),
    ADD KEY idx_staff_user (user_id),
    ADD CONSTRAINT fk_staff_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE;

UPDATE staff s
INNER JOIN members m ON m.id = s.member_id
SET s.user_id = m.user_id
WHERE s.user_id IS NULL AND m.user_id IS NOT NULL;

INSERT INTO staff (user_id, member_id, employee_number, position_title, employment_status)
SELECT u.id, m.id,
             CONCAT('PORTAL-', u.id),
             CASE WHEN r.code = 'PASTOR' THEN 'Pastor / Minister' ELSE 'Counselor' END,
             'ACTIVE'
FROM users u
INNER JOIN user_roles ur ON ur.user_id = u.id
INNER JOIN roles r ON r.id = ur.role_id
LEFT JOIN members m ON m.user_id = u.id
LEFT JOIN staff existing_staff ON existing_staff.user_id = u.id OR existing_staff.member_id = m.id
WHERE u.status = 'ACTIVE'
    AND r.code IN ('PASTOR', 'COUNSELOR')
    AND existing_staff.id IS NULL;
