-- WARNING: Permanently removes all member records, member-only portal accounts, and related data.
-- Admin, staff, pastor, and counselor accounts are kept.
-- Run in phpMyAdmin when you want to register members again from scratch.

USE cmmp_db;

SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM service_documents;
DELETE FROM service_assignments;
DELETE FROM service_status_history;
DELETE FROM service_request_participants;
DELETE FROM service_requests;

DELETE FROM attendance_records;
DELETE FROM event_attendance;
DELETE FROM event_registrations;
DELETE FROM ministry_join_requests;
DELETE FROM ministry_members;
DELETE FROM family_members;

UPDATE staff SET member_id = NULL WHERE member_id IS NOT NULL;

CREATE TEMPORARY TABLE tmp_member_portal_users AS
SELECT DISTINCT m.user_id AS user_id
FROM members m
WHERE m.user_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM user_roles ur
    INNER JOIN roles r ON r.id = ur.role_id
    WHERE ur.user_id = m.user_id
      AND r.code <> 'MEMBER'
  );

DELETE FROM notifications WHERE user_id IN (SELECT user_id FROM tmp_member_portal_users);
DELETE FROM user_roles WHERE user_id IN (SELECT user_id FROM tmp_member_portal_users);
DELETE FROM users WHERE id IN (SELECT user_id FROM tmp_member_portal_users);

DELETE FROM members;

DROP TEMPORARY TABLE tmp_member_portal_users;

SET FOREIGN_KEY_CHECKS = 1;
