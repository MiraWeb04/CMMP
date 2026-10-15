<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/Member.php';

$connection = database();
$suffix = bin2hex(random_bytes(5));
$memberNumber = 'TEST-' . strtoupper($suffix);
$memberId = null;
$userId = null;

$data = [
    'user_id' => null,
    'member_number' => $memberNumber,
    'first_name' => 'Step',
    'middle_name' => 'Seven',
    'last_name' => 'Test',
    'suffix' => null,
    'date_of_birth' => '1990-01-15',
    'gender' => 'Prefer not to say',
    'phone' => '09170000000',
    'email' => 'step7-' . $suffix . '@example.test',
    'address_line_1' => 'Test Street',
    'address_line_2' => null,
    'city' => 'Manila',
    'state_province' => null,
    'postal_code' => '1000',
    'country' => 'Philippines',
    'baptism_date' => '2010-06-12',
    'baptism_church' => 'Test Church',
    'baptism_notes' => 'Test record',
    'emergency_contact_name' => 'Emergency Test',
    'emergency_contact_relationship' => 'Sibling',
    'emergency_contact_phone' => '09171111111',
    'profile_photo' => null,
    'membership_status' => 'ACTIVE',
    'joined_at' => '2026-09-09',
];

try {
    $userInsert = $connection->prepare(
        'INSERT INTO users (username, email, password_hash, status)
         VALUES (:username, :email, :password_hash, :status)'
    );
    $userInsert->execute([
        'username' => 'member_link_' . $suffix,
        'email' => 'member-link-' . $suffix . '@example.test',
        'password_hash' => password_hash('Step7-test-password', PASSWORD_DEFAULT),
        'status' => 'ACTIVE',
    ]);
    $userId = (int) $connection->lastInsertId();
    $data['user_id'] = $userId;
    $memberId = Member::create($data);
    $member = Member::find($memberId);
    if (!$member || $member['member_number'] !== $memberNumber || $member['baptism_church'] !== 'Test Church') {
        throw new RuntimeException('Member creation/read failed.');
    }
    if (Member::findForUser($userId)['id'] !== $memberId) {
        throw new RuntimeException('User-to-member association failed.');
    }

    Member::update($memberId, ['phone' => '09179999999', 'emergency_contact_name' => 'Updated Test']);
    $updated = Member::find($memberId);
    if ($updated['phone'] !== '09179999999' || $updated['emergency_contact_name'] !== 'Updated Test') {
        throw new RuntimeException('Member update failed.');
    }

    Member::setStatus($memberId, 'INACTIVE');
    $deactivated = Member::find($memberId);
    if ($deactivated['membership_status'] !== 'INACTIVE') {
        throw new RuntimeException('Member soft deactivation failed.');
    }

    echo "Member CRUD smoke test passed.\n";

    // Regression check: registration-style member profile creation should persist a linked members row.
    $profileCreateUserId = null;
    $profileMemberId = null;
    $profileUsername = 'member_profile_' . $suffix;
    $profileEmail = 'member-profile-' . $suffix . '@example.test';
    $profileCreateUser = $connection->prepare(
        'INSERT INTO users (username, email, password_hash, status)
         VALUES (:username, :email, :password_hash, :status)'
    );
    $profileCreateUser->execute([
        'username' => $profileUsername,
        'email' => $profileEmail,
        'password_hash' => password_hash('Step7-test-password', PASSWORD_DEFAULT),
        'status' => 'ACTIVE',
    ]);
    $profileCreateUserId = (int) $connection->lastInsertId();
    $profileMemberId = Member::createFromRegistration($profileCreateUserId, 'Profile', 'User', $profileEmail);
    $profileMember = Member::find($profileMemberId);
    if (!$profileMember || (int) $profileMember['user_id'] !== $profileCreateUserId || $profileMember['first_name'] !== 'Profile' || $profileMember['last_name'] !== 'User') {
        throw new RuntimeException('Registration-style member creation failed.');
    }

    echo "Registration-style member creation smoke test passed.\n";
} finally {
    if ($memberId !== null) {
        $delete = $connection->prepare('DELETE FROM members WHERE id = :id');
        $delete->execute(['id' => $memberId]);
    }
    if ($userId !== null) {
        $deleteUser = $connection->prepare('DELETE FROM users WHERE id = :id');
        $deleteUser->execute(['id' => $userId]);
    }
    if ($profileMemberId !== null) {
        $delete = $connection->prepare('DELETE FROM members WHERE id = :id');
        $delete->execute(['id' => $profileMemberId]);
    }
    if ($profileCreateUserId !== null) {
        $deleteUser = $connection->prepare('DELETE FROM users WHERE id = :id');
        $deleteUser->execute(['id' => $profileCreateUserId]);
    }
}
