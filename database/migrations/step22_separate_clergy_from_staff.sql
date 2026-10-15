DELETE ur
FROM user_roles ur
INNER JOIN roles r ON r.id = ur.role_id AND r.code = 'STAFF'
INNER JOIN staff s ON s.user_id = ur.user_id
INNER JOIN pastors p ON p.staff_id = s.id;

DELETE ur
FROM user_roles ur
INNER JOIN roles r ON r.id = ur.role_id AND r.code = 'STAFF'
INNER JOIN staff s ON s.user_id = ur.user_id
INNER JOIN counselors c ON c.staff_id = s.id;

DELETE ur
FROM user_roles ur
INNER JOIN roles r ON r.id = ur.role_id AND r.code = 'STAFF'
INNER JOIN members m ON m.user_id = ur.user_id
INNER JOIN staff s ON s.member_id = m.id
INNER JOIN pastors p ON p.staff_id = s.id;

DELETE ur
FROM user_roles ur
INNER JOIN roles r ON r.id = ur.role_id AND r.code = 'STAFF'
INNER JOIN members m ON m.user_id = ur.user_id
INNER JOIN staff s ON s.member_id = m.id
INNER JOIN counselors c ON c.staff_id = s.id;

UPDATE users u
INNER JOIN staff s ON s.user_id = u.id
INNER JOIN pastors p ON p.staff_id = s.id
SET u.role = 'PASTOR'
WHERE u.role = 'STAFF';

UPDATE users u
INNER JOIN staff s ON s.user_id = u.id
INNER JOIN counselors c ON c.staff_id = s.id
SET u.role = 'COUNSELOR'
WHERE u.role = 'STAFF';

UPDATE users u
INNER JOIN members m ON m.user_id = u.id
INNER JOIN staff s ON s.member_id = m.id
INNER JOIN pastors p ON p.staff_id = s.id
SET u.role = 'PASTOR'
WHERE u.role = 'STAFF';

UPDATE users u
INNER JOIN members m ON m.user_id = u.id
INNER JOIN staff s ON s.member_id = m.id
INNER JOIN counselors c ON c.staff_id = s.id
SET u.role = 'COUNSELOR'
WHERE u.role = 'STAFF';
