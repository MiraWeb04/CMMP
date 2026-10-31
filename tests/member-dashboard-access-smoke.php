<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../models/Member.php';

$passed = 0;
$failed = 0;
$pdo = database();
$suffix = bin2hex(random_bytes(3));
$cleanupUsers = [];

function assert_true(bool $ok, string $message): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        fwrite(STDOUT, "PASS {$message}\n");
        return;
    }
    $failed++;
    fwrite(STDERR, "FAIL {$message}\n");
}

try {
    start_secure_session();
    $_SESSION['user_id'] = '42';
    assert_true(is_logged_in(), 'Numeric string session user_id still counts as logged in');
    unset($_SESSION['user_id']);

    assert_true(request_is_https() === false, 'HTTP requests do not force a Secure session cookie');

    $pdo->prepare(
        'INSERT INTO users (username, email, role, password_hash, status)
         VALUES (:username, :email, :role, :password_hash, :status)'
    )->execute([
        'username' => 'memrole_' . $suffix,
        'email' => 'memrole-' . $suffix . '@example.test',
        'role' => 'MEMBER',
        'password_hash' => password_hash('Password123!', PASSWORD_DEFAULT),
        'status' => 'ACTIVE',
    ]);
    $roleOnlyId = (int) $pdo->lastInsertId();
    $cleanupUsers[] = $roleOnlyId;
    $roleId = (int) $pdo->query("SELECT id FROM roles WHERE code = 'MEMBER'")->fetchColumn();
    $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)')
        ->execute(['user_id' => $roleOnlyId, 'role_id' => $roleId]);

    $_SESSION['user_id'] = $roleOnlyId;
    $created = ensure_member_profile($roleOnlyId);
    assert_true($created !== null && $created > 0, 'MEMBER role without a members row gets a profile');
    assert_true(member_id_for_user($roleOnlyId) === $created, 'Created profile is linked to the user');

    $pdo->prepare(
        'INSERT INTO users (username, email, role, password_hash, status)
         VALUES (:username, :email, :role, :password_hash, :status)'
    )->execute([
        'username' => 'staffmem_' . $suffix,
        'email' => 'staffmem-' . $suffix . '@example.test',
        'role' => 'STAFF',
        'password_hash' => password_hash('Password123!', PASSWORD_DEFAULT),
        'status' => 'ACTIVE',
    ]);
    $staffId = (int) $pdo->lastInsertId();
    $cleanupUsers[] = $staffId;
    $staffRoleId = (int) $pdo->query("SELECT id FROM roles WHERE code = 'STAFF'")->fetchColumn();
    $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)')
        ->execute(['user_id' => $staffId, 'role_id' => $staffRoleId]);
    Member::create([
        'user_id' => $staffId,
        'member_number' => 'ACC-' . strtoupper($suffix),
        'first_name' => 'Linked',
        'middle_name' => null,
        'last_name' => 'Staff',
        'suffix' => null,
        'date_of_birth' => null,
        'gender' => null,
        'phone' => null,
        'email' => 'staffmem-' . $suffix . '@example.test',
        'address_line_1' => null,
        'address_line_2' => null,
        'city' => null,
        'state_province' => null,
        'postal_code' => null,
        'country' => 'Philippines',
        'baptism_date' => null,
        'baptism_church' => null,
        'baptism_notes' => null,
        'emergency_contact_name' => null,
        'emergency_contact_relationship' => null,
        'emergency_contact_phone' => null,
        'profile_photo' => null,
        'membership_status' => 'INACTIVE',
        'joined_at' => null,
    ]);
    $_SESSION['user_id'] = $staffId;
    ensure_member_role($staffId);
    $staffRoles = current_user_roles();
    assert_true(in_array('MEMBER', $staffRoles, true), 'Staff with a member record receive the MEMBER role');
    assert_true(in_array('STAFF', $staffRoles, true), 'Staff role is kept when MEMBER is added');

    $legacyIdUser = $pdo->prepare(
        'INSERT INTO users (username, email, role, password_hash, status)
         VALUES (:username, :email, :role, :password_hash, :status)'
    );
    $legacyIdUser->execute([
        'username' => 'legacy_' . $suffix,
        'email' => 'legacy-' . $suffix . '@example.test',
        'role' => 'MEMBER',
        'password_hash' => password_hash('Password123!', PASSWORD_DEFAULT),
        'status' => 'ACTIVE',
    ]);
    $legacyId = (int) $pdo->lastInsertId();
    $cleanupUsers[] = $legacyId;
    $_SESSION['user_id'] = $legacyId;
    $legacyRoles = current_user_roles();
    assert_true($legacyRoles === ['MEMBER'], 'users.role is used when user_roles is empty');
    $linked = (int) $pdo->prepare('SELECT COUNT(*) FROM user_roles WHERE user_id = :id')->execute(['id' => $legacyId])
        ? (int) $pdo->query('SELECT COUNT(*) FROM user_roles ur INNER JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ' . $legacyId . ' AND r.code = "MEMBER"')->fetchColumn()
        : 0;
    assert_true($linked === 1, 'Legacy users.role is copied into user_roles');
} catch (Throwable $exception) {
    assert_true(false, 'Access smoke test crashed: ' . $exception->getMessage());
}

foreach ($cleanupUsers as $userId) {
    $pdo->prepare('DELETE FROM members WHERE user_id = :id')->execute(['id' => $userId]);
    $pdo->prepare('DELETE FROM user_roles WHERE user_id = :id')->execute(['id' => $userId]);
    $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
}

fwrite(STDOUT, "{$passed} passed, {$failed} failed\n");
exit($failed === 0 ? 0 : 1);
