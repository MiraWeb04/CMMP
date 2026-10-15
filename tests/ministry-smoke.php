<?php
declare(strict_types=1);
require_once __DIR__ . '/../models/Ministry.php';

$connection = database();
$suffix = strtoupper(bin2hex(random_bytes(4)));
$ministryId = null;
$otherMinistryId = null;
$memberId = null;
$leaderMemberId = null;
$staffId = null;

try {
    $insert = $connection->prepare('INSERT INTO members (member_number,first_name,last_name,membership_status) VALUES (:number,:first,:last,"ACTIVE")');
    $insert->execute(['number' => 'MIN-' . $suffix, 'first' => 'Ministry', 'last' => 'Member']);
    $memberId = (int) $connection->lastInsertId();
    $insert->execute(['number' => 'LEAD-' . $suffix, 'first' => 'Ministry', 'last' => 'Leader']);
    $leaderMemberId = (int) $connection->lastInsertId();
    $connection->prepare('INSERT INTO staff (member_id,employee_number,position_title,employment_status) VALUES (:member_id,:number,"Ministry Leader","ACTIVE")')->execute(['member_id' => $leaderMemberId, 'number' => 'MINSTAFF-' . $suffix]);
    $staffId = (int) $connection->lastInsertId();
    $ministryId = Ministry::create(['name' => 'Test Ministry ' . $suffix, 'code' => 'MIN-' . $suffix, 'description' => 'Test', 'leader_staff_id' => $staffId]);
    if (Ministry::isLeader($ministryId, 999999)) {
        throw new RuntimeException('Invalid user was treated as ministry leader.');
    }
    Ministry::addMember($ministryId, $memberId, 'Member');
    if (count(Ministry::find($ministryId)['members']) !== 1) {
        throw new RuntimeException('Member add failed.');
    }
    Ministry::removeMember($ministryId, $memberId);
    if (count(Ministry::find($ministryId)['members']) !== 0) {
        throw new RuntimeException('Member remove failed.');
    }
    Ministry::addMember($ministryId, $memberId, 'Member');
    if (count(Ministry::find($ministryId)['members']) !== 1) {
        throw new RuntimeException('Admin assignment failed.');
    }
    try {
        Ministry::addMember($ministryId, $memberId, 'Leader');
        throw new RuntimeException('Duplicate ministry role assignment must be blocked.');
    } catch (InvalidArgumentException $exception) {
        if (!str_contains($exception->getMessage(), 'already assigned')) {
            throw $exception;
        }
    }
    $otherMinistryId = Ministry::create(['name' => 'Other Ministry ' . $suffix, 'code' => 'OTHER-' . $suffix, 'description' => 'Test', 'leader_staff_id' => null]);
    try {
        Ministry::addMember($otherMinistryId, $memberId, 'Member');
        throw new RuntimeException('Cross-ministry assignment must be blocked.');
    } catch (InvalidArgumentException $exception) {
        if (!str_contains($exception->getMessage(), 'already assigned')) {
            throw $exception;
        }
    }
    try {
        Ministry::requestJoin($ministryId, $memberId);
        throw new RuntimeException('Member join requests must be disabled.');
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), 'cannot request to join')) {
            throw $exception;
        }
    }
    echo "Ministry management smoke test passed.\n";
} finally {
    if (isset($otherMinistryId) && $otherMinistryId) {
        $connection->prepare('DELETE FROM ministries WHERE id=:id')->execute(['id' => $otherMinistryId]);
    }
    if ($ministryId) {
        $connection->prepare('DELETE FROM ministries WHERE id=:id')->execute(['id' => $ministryId]);
    }
    if ($staffId) {
        $connection->prepare('DELETE FROM staff WHERE id=:id')->execute(['id' => $staffId]);
    }
    foreach ([$memberId, $leaderMemberId] as $id) {
        if ($id) {
            $connection->prepare('DELETE FROM members WHERE id=:id')->execute(['id' => $id]);
        }
    }
}
