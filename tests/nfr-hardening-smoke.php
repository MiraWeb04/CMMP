<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/account_profile.php';
require_once __DIR__ . '/../helpers/pagination.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Member.php';
require_once __DIR__ . '/../models/ServiceRequest.php';
require_once __DIR__ . '/../models/Announcement.php';
require_once __DIR__ . '/../models/Personnel.php';
require_once __DIR__ . '/../models/Ministry.php';

$passed = 0;
$failed = 0;
$cleanup = [];

function assert_true(bool $condition, string $message): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        fwrite(STDOUT, "PASS {$message}\n");
        return;
    }
    $failed++;
    fwrite(STDERR, "FAIL {$message}\n");
}

$suffix = bin2hex(random_bytes(4));
$pdo = database();
User::ensureSecurityTables();

try {
    $pdo->prepare(
        'INSERT INTO users (username, email, password_hash, status)
         VALUES (:username, :email, :password_hash, :status)'
    )->execute([
        'username' => 'nfr_' . $suffix,
        'email' => 'nfr-' . $suffix . '@example.test',
        'password_hash' => password_hash('StartPass9', PASSWORD_DEFAULT),
        'status' => 'ACTIVE',
    ]);
    $userId = (int) $pdo->lastInsertId();
    $cleanup['user'] = $userId;

    foreach ([
        ['CYF', 'Christian Youth Fellowship'],
        ['CYAF', 'Christian Young Adult Fellowship'],
        ['UCM', 'United Churchmen'],
        ['CWA', "Church Women's Association"],
    ] as $org) {
        $pdo->prepare(
            'INSERT IGNORE INTO ministries (code, name, description, ministry_status)
             VALUES (:code, :name, :description, :status)'
        )->execute([
            'code' => $org[0],
            'name' => $org[1],
            'description' => $org[1],
            'status' => 'ACTIVE',
        ]);
    }

    $memberId = Member::create([
        'user_id' => $userId,
        'member_number' => 'NFR-' . strtoupper($suffix),
        'first_name' => 'Nfr',
        'middle_name' => null,
        'last_name' => 'Tester',
        'suffix' => null,
        'date_of_birth' => '1991-02-02',
        'gender' => 'Female',
        'phone' => '09170000001',
        'email' => 'nfr-' . $suffix . '@example.test',
        'address_line_1' => 'Old Street',
        'address_line_2' => null,
        'city' => 'Cebu',
        'state_province' => null,
        'postal_code' => null,
        'country' => 'Philippines',
        'baptism_date' => null,
        'baptism_church' => null,
        'baptism_notes' => null,
        'emergency_contact_name' => 'Kin',
        'emergency_contact_relationship' => 'Sibling',
        'emergency_contact_phone' => '09170000002',
        'profile_photo' => null,
        'membership_status' => 'ACTIVE',
        'joined_at' => '2024-01-01',
    ]);
    $cleanup['member'] = $memberId;

    $ip = '203.0.113.10';
    for ($i = 0; $i < User::LOCKOUT_THRESHOLD; $i++) {
        User::recordLoginAttempt('nfr_' . $suffix, $ip, false);
    }
    assert_true(User::isTemporarilyLocked('nfr_' . $suffix, $ip), 'Five failed logins lock the identifier');
    $locked = authenticate_user('nfr_' . $suffix, 'StartPass9');
    assert_true(($locked['status'] ?? '') === 'LOCKED_TEMPORARY', 'authenticate_user reports temporary lockout');

    $pdo->prepare('DELETE FROM login_attempts WHERE identifier = :identifier')->execute(['identifier' => 'nfr_' . $suffix]);
    $authed = authenticate_user('nfr_' . $suffix, 'StartPass9');
    assert_true(is_array($authed) && (int) ($authed['id'] ?? 0) === $userId, 'Valid password authenticates after lockout clears');

    $token = User::createPasswordReset($userId);
    assert_true(preg_match('/^[a-f0-9]{64}$/', $token) === 1, 'Password reset token is 64 hex characters');
    assert_true(User::consumePasswordReset($token, 'NewPass12'), 'Valid reset token updates the password');
    assert_true(User::consumePasswordReset($token, 'OtherPass12') === false, 'Used reset token cannot be reused');
    assert_true(User::verifyPassword($userId, 'NewPass12'), 'Reset password is stored as a hash that verifies');

    $errors = save_account_profile($userId, [
        'email' => 'nfr-updated-' . $suffix . '@example.test',
        'current_password' => 'NewPass12',
        'password' => 'FinalPass12',
        'password_confirmation' => 'FinalPass12',
        'phone' => '09170000999',
        'address_line_1' => 'New Street',
        'address_line_2' => '',
        'city' => 'Manila',
        'emergency_contact_name' => 'Guardian',
        'emergency_contact_relationship' => 'Parent',
        'emergency_contact_phone' => '09170000888',
    ], Member::find($memberId));
    assert_true($errors === [], 'Staff-style profile update succeeds');
    $fresh = User::find($userId);
    $freshMember = Member::find($memberId);
    assert_true(($fresh['email'] ?? '') === 'nfr-updated-' . $suffix . '@example.test', 'Profile email updates the user row');
    assert_true(($freshMember['phone'] ?? '') === '09170000999', 'Profile phone updates the member row');
    assert_true(User::verifyPassword($userId, 'FinalPass12'), 'Profile password change verifies');

    $users = User::paginate('nfr-updated-' . $suffix, 'ACTIVE', 1, 15);
    assert_true($users['total'] >= 1 && $users['items'] !== [], 'User directory search uses unique placeholders');

    $paged = ServiceRequest::paginateForAdmin('no-such-request-' . $suffix, '', 1, 15);
    assert_true(isset($paged['items'], $paged['total']) && is_array($paged['items']), 'Service request admin list pages');
    $all = ServiceRequest::paginateForAdmin('', '');
    assert_true(array_is_list($all), 'Unpaged admin service list keeps the original array shape');

    $personnel = Personnel::paginate('STAFF', 1, 15);
    assert_true(isset($personnel['items'], $personnel['total']), 'Staff directory pages');
    $ministries = Ministry::paginateAdmin(1, 15);
    assert_true(isset($ministries['items'], $ministries['total']), 'Ministry directory pages');
    $announcements = Announcement::paginate(1, 15);
    assert_true(isset($announcements['items'], $announcements['total']), 'Announcement directory pages');
    assert_true(pagination_total_pages(40, 15) === 3, 'Pagination helper computes page counts');

    $health = json_decode((string) shell_exec('php ' . escapeshellarg(__DIR__ . '/../health.php')), true);
    assert_true(is_array($health) && in_array($health['status'] ?? '', ['ok', 'degraded'], true), 'Health endpoint returns JSON status');
    assert_true(isset($health['checks']['database']), 'Health endpoint reports database check');
} catch (Throwable $exception) {
    assert_true(false, 'NFR smoke test crashed: ' . $exception->getMessage());
}

if (isset($cleanup['member'])) {
    $pdo->prepare('DELETE FROM members WHERE id = :id')->execute(['id' => $cleanup['member']]);
}
if (isset($cleanup['user'])) {
    $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :id')->execute(['id' => $cleanup['user']]);
    $pdo->prepare('DELETE FROM login_attempts WHERE identifier LIKE :identifier')->execute(['identifier' => 'nfr_' . $suffix]);
    $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $cleanup['user']]);
}

fwrite(STDOUT, "{$passed} passed, {$failed} failed\n");
exit($failed === 0 ? 0 : 1);
