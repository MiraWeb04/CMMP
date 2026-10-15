ALTER TABLE users
    ADD COLUMN role VARCHAR(50) NULL AFTER email,
    ADD KEY idx_users_role (role);

UPDATE users u
LEFT JOIN (
    SELECT ur.user_id, MIN(r.code) AS role_code
    FROM user_roles ur
    INNER JOIN roles r ON r.id = ur.role_id
    GROUP BY ur.user_id
) assigned_role ON assigned_role.user_id = u.id
SET u.role = assigned_role.role_code
WHERE u.role IS NULL;
