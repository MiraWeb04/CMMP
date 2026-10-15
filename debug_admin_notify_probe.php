<?php
require_once __DIR__ . '/config/database.php';
$db = database();
$q = $db->query("SELECT COUNT(*) FROM users u INNER JOIN user_roles ur ON ur.user_id = u.id INNER JOIN roles r ON r.id = ur.role_id WHERE u.status = 'ACTIVE' AND r.code IN ('SUPER_ADMIN', 'CHURCH_ADMIN')");
echo 'admin_user_count=' . (int)$q->fetchColumn() . PHP_EOL;

$q = $db->query("SELECT u.id, u.username, u.email, u.status, GROUP_CONCAT(DISTINCT r.code ORDER BY r.code SEPARATOR ',') AS roles FROM users u LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN roles r ON r.id = ur.role_id GROUP BY u.id ORDER BY u.id");
foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo json_encode($row) . PHP_EOL;
}

$q = $db->query("SELECT * FROM notifications WHERE notification_type = 'SERVICE_REQUEST_NEW' ORDER BY created_at DESC LIMIT 20");
foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo json_encode($row) . PHP_EOL;
}
