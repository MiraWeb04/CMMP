<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this script from the command line.');
}

if (($argv[1] ?? '') !== '--confirm') {
    echo "This permanently deletes all members and member-only portal accounts.\n";
    echo "Run: php scripts/purge-all-members.php --confirm\n";
    exit(1);
}

$pdo = database();
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$pdo->exec('DELETE FROM service_documents');
$pdo->exec('DELETE FROM service_assignments');
$pdo->exec('DELETE FROM service_status_history');
$pdo->exec('DELETE FROM service_request_participants');
$pdo->exec('DELETE FROM service_requests');
$pdo->exec('DELETE FROM attendance_records');
$pdo->exec('DELETE FROM event_attendance');
$pdo->exec('DELETE FROM event_registrations');
$pdo->exec('DELETE FROM ministry_join_requests');
$pdo->exec('DELETE FROM ministry_members');
$pdo->exec('DELETE FROM family_members');
$pdo->exec('UPDATE staff SET member_id = NULL WHERE member_id IS NOT NULL');

$pdo->exec(
    'CREATE TEMPORARY TABLE tmp_member_portal_users AS
     SELECT DISTINCT m.user_id AS user_id
     FROM members m
     WHERE m.user_id IS NOT NULL
       AND NOT EXISTS (
         SELECT 1
         FROM user_roles ur
         INNER JOIN roles r ON r.id = ur.role_id
         WHERE ur.user_id = m.user_id
           AND r.code <> "MEMBER"
       )'
);

$pdo->exec('DELETE FROM notifications WHERE user_id IN (SELECT user_id FROM tmp_member_portal_users)');
$pdo->exec('DELETE FROM user_roles WHERE user_id IN (SELECT user_id FROM tmp_member_portal_users)');
$pdo->exec('DELETE FROM users WHERE id IN (SELECT user_id FROM tmp_member_portal_users)');
$pdo->exec('DELETE FROM members');
$pdo->exec('DROP TEMPORARY TABLE tmp_member_portal_users');
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

echo "All member records and member-only portal accounts were removed.\n";
