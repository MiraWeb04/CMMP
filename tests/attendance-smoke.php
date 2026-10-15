<?php
declare(strict_types=1);
require_once __DIR__ . '/../models/Attendance.php';
$connection = database(); $suffix = strtoupper(bin2hex(random_bytes(4))); $memberIds = []; $sessionId = null;
try {
    $insert = $connection->prepare('INSERT INTO members (member_number, first_name, last_name, membership_status) VALUES (:number, :first, :last, :status)');
    foreach (['One', 'Two'] as $name) { $insert->execute(['number' => 'ATT-' . $suffix . '-' . $name, 'first' => 'Test', 'last' => $name, 'status' => 'ACTIVE']); $memberIds[] = (int) $connection->lastInsertId(); }
    $token = 'attendance-token-' . $suffix;
    $sessionId = Attendance::createSession(['event_id' => null, 'activity_type' => 'WORSHIP_SERVICE', 'title' => 'Test worship service', 'start_datetime' => date('Y-m-d H:i:s', time() - 60), 'end_datetime' => null, 'qr_token_hash' => hash('sha256', $token), 'qr_expires_at' => null, 'session_status' => 'OPEN', 'created_by_user_id' => null]);
    if (!Attendance::sessionByQr($token)) { throw new RuntimeException('QR session lookup failed.'); }
    Attendance::record($sessionId, $memberIds[0], 'QR_CODE', null);
    try { Attendance::record($sessionId, $memberIds[0], 'QR_CODE', null); throw new RuntimeException('Duplicate attendance accepted.'); } catch (DomainException $expected) {}
    Attendance::record($sessionId, $memberIds[1], 'MANUAL', null);
    if (count(Attendance::memberHistory($memberIds[0])) !== 1 || count(Attendance::memberHistory($memberIds[1])) !== 1) { throw new RuntimeException('Member attendance history failed.'); }
    echo "Attendance duplicate and authorization data smoke test passed.\n";
} finally {
    if ($sessionId !== null) { $connection->prepare('DELETE FROM attendance_sessions WHERE id = :id')->execute(['id' => $sessionId]); }
    foreach ($memberIds as $id) { $connection->prepare('DELETE FROM members WHERE id = :id')->execute(['id' => $id]); }
}
