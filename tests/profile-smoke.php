<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/account_profile.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Member.php';

$passed = 0;
$failed = 0;
$pdo = database();
$suffix = bin2hex(random_bytes(3));
$cleanup = ['users' => [], 'files' => []];

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

function profile_test_png(): string
{
    $path = sys_get_temp_dir() . '/cmmp-profile-' . bin2hex(random_bytes(4)) . '.png';
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+XWuoAAAAASUVORK5CYII=');
    file_put_contents($path, $png ?: '');
    return $path;
}

function profile_test_user(PDO $pdo, string $username, string $email, string $role, string $password): int
{
    $pdo->prepare(
        'INSERT INTO users (username, email, role, password_hash, status)
         VALUES (:username, :email, :role, :password_hash, :status)'
    )->execute([
        'username' => $username,
        'email' => $email,
        'role' => $role,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'status' => 'ACTIVE',
    ]);
    $userId = (int) $pdo->lastInsertId();
    $roleId = (int) $pdo->query("SELECT id FROM roles WHERE code = " . $pdo->quote($role))->fetchColumn();
    $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)')
        ->execute(['user_id' => $userId, 'role_id' => $roleId]);
    return $userId;
}

$roles = [
    'MEMBER' => 'Church Member',
    'PASTOR' => 'Pastor',
    'COUNSELOR' => 'Counselor',
    'STAFF' => 'Church Secretary',
    'CHURCH_ADMIN' => 'Church Administrator',
    'SUPER_ADMIN' => 'Super Administrator',
];

try {
    start_secure_session();
    $png = profile_test_png();
    $cleanup['files'][] = $png;

    foreach ($roles as $code => $label) {
        $userId = profile_test_user($pdo, 'prof_' . strtolower($code) . '_' . $suffix, 'prof-' . strtolower($code) . '-' . $suffix . '@example.test', $code, 'StartPass9!');
        $cleanup['users'][] = $userId;
        $_SESSION['user_id'] = $userId;

        $beforeRoles = current_user_roles();
        $errors = save_account_profile($userId, [
            'first_name' => $label,
            'middle_name' => 'Test',
            'last_name' => 'Profile',
            'suffix' => '',
            'email' => 'prof-updated-' . strtolower($code) . '-' . $suffix . '@example.test',
            'current_password' => '',
            'password' => '',
            'password_confirmation' => '',
            'phone' => '09170000111',
            'address_line_1' => '1 Church Road',
            'address_line_2' => '',
            'city' => 'Cebu',
            'state_province' => 'Cebu',
            'postal_code' => '6000',
            'country' => 'Philippines',
            'emergency_contact_name' => 'Kin',
            'emergency_contact_relationship' => 'Sibling',
            'emergency_contact_phone' => '09170000222',
            'remove_profile_photo' => false,
        ], Member::findForUser($userId));
        assert_true($errors === [], $code . ' can update name and contact details');

        $member = Member::findForUser($userId);
        assert_true($member !== null && $member['first_name'] === $label && $member['phone'] === '09170000111', $code . ' profile fields persist');
        assert_true(current_user_roles() === $beforeRoles, $code . ' roles stay unchanged after a profile save');

        $passwordErrors = save_account_profile($userId, array_merge(account_profile_payload([
            'first_name' => $label,
            'last_name' => 'Profile',
            'email' => 'prof-updated-' . strtolower($code) . '-' . $suffix . '@example.test',
            'phone' => '09170000111',
        ]), [
            'current_password' => 'wrong-pass',
            'password' => 'NewerPass9!',
            'password_confirmation' => 'NewerPass9!',
            'remove_profile_photo' => false,
        ]), $member);
        assert_true(in_array('Current password is incorrect.', $passwordErrors, true), $code . ' password change requires the current password');

        $passwordOk = save_account_profile($userId, array_merge(account_profile_payload([
            'first_name' => $label,
            'last_name' => 'Profile',
            'email' => 'prof-updated-' . strtolower($code) . '-' . $suffix . '@example.test',
            'phone' => '09170000111',
        ]), [
            'current_password' => 'StartPass9!',
            'password' => 'NewerPass9!',
            'password_confirmation' => 'NewerPass9!',
            'remove_profile_photo' => false,
        ]), $member);
        assert_true($passwordOk === [] && User::verifyPassword($userId, 'NewerPass9!'), $code . ' can change password with confirmation');
    }

    $memberUserId = $cleanup['users'][0];
    $_SESSION['user_id'] = $memberUserId;
    $member = Member::findForUser($memberUserId);
    $stored = store_profile_photo($png, (int) filesize($png), false);
    $cleanup['files'][] = dirname(__DIR__) . '/' . $stored;
    Member::update((int) $member['id'], ['profile_photo' => $stored]);
    assert_true(profile_photo_url($stored) !== null, 'Uploaded profile photo has a safe public URL');

    $replacementPng = profile_test_png();
    $cleanup['files'][] = $replacementPng;
    $replacement = store_profile_photo($replacementPng, (int) filesize($replacementPng), false);
    $cleanup['files'][] = dirname(__DIR__) . '/' . $replacement;
    $replaceErrors = save_account_profile($memberUserId, array_merge(account_profile_payload([
        'first_name' => 'Church Member',
        'last_name' => 'Profile',
        'email' => 'prof-updated-member-' . $suffix . '@example.test',
        'phone' => '09170000111',
    ]), ['remove_profile_photo' => false]), Member::findForUser($memberUserId));
    assert_true($replaceErrors === [], 'Profile save still works after a photo is stored');
    Member::update((int) Member::findForUser($memberUserId)['id'], ['profile_photo' => $replacement]);
    delete_profile_photo($stored);
    assert_true(!is_file(dirname(__DIR__) . '/' . $stored), 'Replacing a photo deletes the previous file when asked');

    $removeErrors = save_account_profile($memberUserId, array_merge(account_profile_payload([
        'first_name' => 'Church Member',
        'last_name' => 'Profile',
        'email' => 'prof-updated-member-' . $suffix . '@example.test',
        'phone' => '09170000111',
    ]), ['remove_profile_photo' => true]), Member::findForUser($memberUserId));
    $afterRemove = Member::findForUser($memberUserId);
    assert_true($removeErrors === [] && empty($afterRemove['profile_photo']), 'Users can remove their profile photo');
    assert_true(!is_file(dirname(__DIR__) . '/' . $replacement), 'Removed photo is deleted from the upload directory');

    $fakePhp = sys_get_temp_dir() . '/cmmp-profile-' . $suffix . '.php';
    file_put_contents($fakePhp, '<?php echo "no";');
    $cleanup['files'][] = $fakePhp;
    try {
        store_profile_photo($fakePhp, (int) filesize($fakePhp), false);
        assert_true(false, 'Executable-looking uploads are rejected');
    } catch (RuntimeException $exception) {
        assert_true(str_contains($exception->getMessage(), 'valid JPG, PNG, or WebP'), 'Non-image files are rejected');
    }

    $otherId = $cleanup['users'][1];
    $source = (string) file_get_contents(__DIR__ . '/../account/profile.php');
    assert_true(!str_contains($source, '$_GET[\'id\']') && str_contains($source, 'current_user_id()'), 'Profile page edits only the signed-in user');
    assert_true(account_profile_url() === APP_BASE_URL . '/account/profile.php', 'Every role uses the same My Profile URL');
    unset($otherId);
} catch (Throwable $exception) {
    assert_true(false, 'Profile smoke test crashed: ' . $exception->getMessage());
}

foreach ($cleanup['users'] as $userId) {
    $pdo->prepare('DELETE FROM members WHERE user_id = :id')->execute(['id' => $userId]);
    $pdo->prepare('DELETE FROM user_roles WHERE user_id = :id')->execute(['id' => $userId]);
    $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
}
foreach ($cleanup['files'] as $file) {
    if (is_file($file)) {
        @unlink($file);
    }
}

fwrite(STDOUT, "{$passed} passed, {$failed} failed\n");
exit($failed === 0 ? 0 : 1);
