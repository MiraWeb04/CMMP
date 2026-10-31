<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../models/Member.php';

$base = getenv('CMMP_SWEEP_BASE') ?: 'http://127.0.0.1:8080';
$password = 'SweepPass123!';
$pdo = database();
$failed = 0;
$passed = 0;

function sweep_role_id(PDO $pdo, string $code): int
{
    $query = $pdo->prepare('SELECT id FROM roles WHERE code = :code LIMIT 1');
    $query->execute(['code' => $code]);
    $id = $query->fetchColumn();
    if ($id === false) {
        throw new RuntimeException('Missing role ' . $code);
    }
    return (int) $id;
}

function sweep_user(PDO $pdo, string $username, string $email, string $role, string $password): int
{
    $existing = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
    $existing->execute(['username' => $username]);
    $id = $existing->fetchColumn();
    if ($id === false) {
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
        $id = (int) $pdo->lastInsertId();
    } else {
        $id = (int) $id;
        $pdo->prepare(
            'UPDATE users SET email = :email, role = :role, password_hash = :password_hash, status = "ACTIVE" WHERE id = :id'
        )->execute([
            'email' => $email,
            'role' => $role,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'id' => $id,
        ]);
    }

    $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)')
        ->execute(['user_id' => $id, 'role_id' => sweep_role_id($pdo, $role)]);

    return $id;
}

function sweep_member_profile(int $userId, string $first, string $last): int
{
    $existing = member_id_for_user($userId);
    if ($existing) {
        return $existing;
    }
    return Member::createFromRegistration($userId, [
        'first_name' => $first,
        'last_name' => $last,
        'email' => '',
    ]);
}

function sweep_staff(PDO $pdo, int $userId, int $memberId, string $number, string $title): int
{
    $existing = $pdo->prepare('SELECT id FROM staff WHERE user_id = :user_id LIMIT 1');
    $existing->execute(['user_id' => $userId]);
    $id = $existing->fetchColumn();
    if ($id !== false) {
        return (int) $id;
    }
    $pdo->prepare(
        'INSERT INTO staff (user_id, member_id, employee_number, position_title, employment_status)
         VALUES (:user_id, :member_id, :employee_number, :position_title, "ACTIVE")'
    )->execute([
        'user_id' => $userId,
        'member_id' => $memberId,
        'employee_number' => $number,
        'position_title' => $title,
    ]);
    return (int) $pdo->lastInsertId();
}

$accounts = [
    'member' => sweep_user($pdo, 'sweep_member', 'sweep-member@example.test', 'MEMBER', $password),
    'admin' => sweep_user($pdo, 'sweep_admin', 'sweep-admin@example.test', 'CHURCH_ADMIN', $password),
    'staff' => sweep_user($pdo, 'sweep_staff', 'sweep-staff@example.test', 'STAFF', $password),
    'pastor' => sweep_user($pdo, 'sweep_pastor', 'sweep-pastor@example.test', 'PASTOR', $password),
    'counselor' => sweep_user($pdo, 'sweep_counselor', 'sweep-counselor@example.test', 'COUNSELOR', $password),
    'leader' => sweep_user($pdo, 'sweep_leader', 'sweep-leader@example.test', 'MINISTRY_LEADER', $password),
];

$memberId = sweep_member_profile($accounts['member'], 'Sweep', 'Member');
database()->prepare('UPDATE members SET membership_status = "ACTIVE", joined_at = CURDATE() WHERE id = :id')
    ->execute(['id' => $memberId]);

foreach (['staff' => 'Secretary', 'pastor' => 'Pastor', 'counselor' => 'Counselor', 'leader' => 'Ministry Leader'] as $key => $title) {
    $profileId = sweep_member_profile($accounts[$key], 'Sweep', $title);
    $staffId = sweep_staff($pdo, $accounts[$key], $profileId, 'SWEEP-' . strtoupper($key), $title);
    if ($key === 'pastor') {
        $pdo->prepare('INSERT IGNORE INTO pastors (staff_id) VALUES (:staff_id)')->execute(['staff_id' => $staffId]);
    }
    if ($key === 'counselor') {
        $pdo->prepare('INSERT IGNORE INTO counselors (staff_id, specialization) VALUES (:staff_id, "General")')
            ->execute(['staff_id' => $staffId]);
    }
    if ($key === 'leader') {
        $ministry = $pdo->query("SELECT id FROM ministries WHERE code = 'SWEEP-LEAD' LIMIT 1")->fetchColumn();
        if ($ministry === false) {
            $pdo->prepare(
                'INSERT INTO ministries (name, code, description, ministry_status, leader_staff_id)
                 VALUES ("Sweep Fellowship", "SWEEP-LEAD", "Access sweep ministry", "ACTIVE", :leader_staff_id)'
            )->execute(['leader_staff_id' => $staffId]);
        } else {
            $pdo->prepare('UPDATE ministries SET leader_staff_id = :leader_staff_id WHERE id = :id')
                ->execute(['leader_staff_id' => $staffId, 'id' => (int) $ministry]);
        }
    }
}

$pages = [
    'public' => [
        '/',
        '/index.php',
        '/member-portal.php',
        '/auth/login.php',
        '/auth/register.php',
        '/auth/forgot-password.php',
        '/health.php',
    ],
    'member' => [
        '/member/dashboard/',
        '/member/events/',
        '/member/registrations/',
        '/member/services/',
        '/member/services/calendar.php',
        '/member/services/request.php',
        '/member/ministries/',
        '/member/announcements/',
        '/member/notifications/',
        '/member/profile/',
    ],
    'admin' => [
        '/admin/dashboard/',
        '/admin/members/',
        '/admin/members/add.php',
        '/admin/members/registrations.php',
        '/admin/staff/',
        '/admin/pastors/',
        '/admin/counselors/',
        '/admin/ministries/',
        '/admin/ministries/add.php',
        '/admin/events/',
        '/admin/events/add.php',
        '/admin/services/',
        '/admin/services/calendar.php',
        '/admin/facilities/',
        '/admin/facilities/add.php',
        '/admin/announcements/',
        '/admin/reports/',
        '/admin/users/',
        '/admin/users/add.php',
        '/admin/staff/notifications.php',
        '/account/profile.php',
    ],
    'staff' => [
        '/admin/dashboard/',
        '/admin/services/',
        '/admin/services/calendar.php',
        '/admin/staff/calendar.php',
        '/admin/staff/assignments.php',
        '/admin/events/',
        '/admin/facilities/',
        '/admin/announcements/',
        '/admin/staff/availability.php',
        '/admin/staff/notifications.php',
        '/account/profile.php',
    ],
    'pastor' => [
        '/pastor/dashboard/',
        '/pastor/services/',
        '/admin/services/calendar.php',
        '/pastor/appointments/',
        '/pastor/schedule/',
        '/pastor/profile/',
        '/admin/staff/notifications.php',
        '/account/profile.php',
    ],
    'counselor' => [
        '/counselor/dashboard/',
        '/counselor/couples/',
        '/counselor/sessions/',
        '/counselor/schedule/',
        '/counselor/profile/',
        '/admin/staff/notifications.php',
        '/account/profile.php',
    ],
    'leader' => [
        '/ministry/dashboard/',
        '/ministry/activities/',
        '/ministry/events/',
        '/ministry/profile/',
        '/admin/staff/notifications.php',
        '/account/profile.php',
    ],
];

function sweep_request(string $base, string $path, array $cookieJar, string $method = 'GET', array $fields = []): array
{
    $command = [
        'curl',
        '-sS',
        '-D', '-',
        '-o', '-',
        '--max-time', '20',
        '-A', 'CMMP-page-access-sweep',
        '-b', $cookieJar['file'],
        '-c', $cookieJar['file'],
    ];
    if ($method === 'POST') {
        $command[] = '-X';
        $command[] = 'POST';
        $command[] = '--data';
        $command[] = http_build_query($fields);
    }
    $command[] = $base . $path;

    $descriptor = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptor, $pipes);
    if (!is_resource($process)) {
        return ['status' => 0, 'body' => '', 'headers' => '', 'error' => 'Unable to start curl'];
    }
    fclose($pipes[0]);
    $raw = stream_get_contents($pipes[1]);
    $error = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    $headerEnd = strpos((string) $raw, "\r\n\r\n");
    if ($headerEnd === false) {
        $headerEnd = strpos((string) $raw, "\n\n");
        $headers = $headerEnd === false ? '' : substr((string) $raw, 0, $headerEnd);
        $body = $headerEnd === false ? (string) $raw : substr((string) $raw, $headerEnd + 2);
    } else {
        $headers = substr((string) $raw, 0, $headerEnd);
        $body = substr((string) $raw, $headerEnd + 4);
    }
    $status = 0;
    if (preg_match('/HTTP\/\S+\s+(\d+)/', $headers, $match)) {
        $status = (int) $match[1];
    }

    return [
        'status' => $status,
        'headers' => $headers,
        'body' => $body,
        'error' => $error,
    ];
}

function sweep_login(string $base, string $username, string $password): array
{
    $cookieFile = tempnam(sys_get_temp_dir(), 'cmmp_sweep_');
    $jar = ['file' => $cookieFile];
    $loginPage = sweep_request($base, '/auth/login.php', $jar);
    if ($loginPage['status'] !== 200 || !preg_match('/name="csrf_token" value="([^"]+)"/', $loginPage['body'], $match)) {
        return ['ok' => false, 'jar' => $jar, 'detail' => 'Could not open login form (' . $loginPage['status'] . ')'];
    }
    $posted = sweep_request($base, '/auth/login.php', $jar, 'POST', [
        'csrf_token' => $match[1],
        'identifier' => $username,
        'password' => $password,
    ]);
    $location = '';
    if (preg_match('/^Location:\s*(.+)$/mi', $posted['headers'], $loc)) {
        $location = trim($loc[1]);
    }
    $ok = in_array($posted['status'], [302, 303], true) && $location !== '' && !str_contains($posted['body'], 'Invalid login');
    return [
        'ok' => $ok,
        'jar' => $jar,
        'status' => $posted['status'],
        'location' => $location,
        'detail' => $ok ? $location : ('Login failed HTTP ' . $posted['status'] . ' ' . trim(strip_tags($posted['body']))),
    ];
}

function assert_page(bool $ok, string $message): void
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

$publicJar = ['file' => tempnam(sys_get_temp_dir(), 'cmmp_public_')];
foreach ($pages['public'] as $path) {
    $result = sweep_request($base, $path, $publicJar);
    $body = $result['body'];
    $redirectOk = in_array($result['status'], [301, 302, 303], true) && preg_match('/^Location:\s*\S+/mi', $result['headers']);
    $healthOk = $path === '/health.php' && $result['status'] === 200 && str_contains($body, '"status"');
    $blank = trim($body) === '' && $result['status'] === 200;
    $errorPage = str_contains($body, 'The page could not be opened') || str_contains($body, 'Fatal error') || str_contains($body, 'Uncaught');
    assert_page(
        $redirectOk || $healthOk || ($result['status'] === 200 && !$blank && !$errorPage && strlen($body) > 200),
        "public {$path} HTTP {$result['status']} len=" . strlen($body)
    );
}

foreach (['member', 'admin', 'staff', 'pastor', 'counselor', 'leader'] as $role) {
    $login = sweep_login($base, 'sweep_' . $role, $password);
    assert_page($login['ok'], "{$role} login redirects to {$login['location']}");
    if (!$login['ok']) {
        continue;
    }
    foreach ($pages[$role] as $path) {
        $result = sweep_request($base, $path, $login['jar']);
        $body = $result['body'];
        $blank = trim($body) === '';
        $errorPage = str_contains($body, 'The page could not be opened')
            || str_contains($body, 'Fatal error')
            || str_contains($body, 'Uncaught')
            || str_contains($body, 'Parse error');
        $redirectOk = in_array($result['status'], [301, 302, 303], true)
            && preg_match('/Location:\s*\S+/i', $result['headers']);
        $ok = $redirectOk || (in_array($result['status'], [200], true) && !$blank && !$errorPage && strlen($body) > 200);
        $snippet = $errorPage ? ' ' . trim(preg_replace('/\s+/', ' ', substr(strip_tags($body), 0, 180))) : '';
        assert_page($ok, "{$role} {$path} HTTP {$result['status']} len=" . strlen($body) . $snippet);
    }
    @unlink($login['jar']['file']);
}

@unlink($publicJar['file']);

fwrite(STDOUT, "{$passed} passed, {$failed} failed\n");
exit($failed === 0 ? 0 : 1);
