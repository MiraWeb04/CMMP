<?php
declare(strict_types=1);

/**
 * HTTP authorization and CSRF checks for FR-07 archive, hide, and
 * notification-delete endpoints. Uses the local PHP server at 127.0.0.1:8080
 * and the dashboard fixture accounts. Does not change APP_BASE_URL.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Notification.php';
require_once __DIR__ . '/../models/ServiceRequest.php';
require_once __DIR__ . '/../models/Member.php';

$passed = 0;
$failed = 0;
$blocked = 0;
$base = 'http://127.0.0.1:8080';
$password = 'Password123!';

function http_ok(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "PASS  {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
        return;
    }
    $failed++;
    echo "FAIL  {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
}

function http_blocked(string $name, string $detail): void
{
    global $blocked;
    $blocked++;
    echo "BLOCKED  {$name} — {$detail}\n";
}

function fr07_http(string $method, string $url, array $headers = [], ?string $body = null, ?string $cookie = null): array
{
    $cmd = ['curl', '-sS', '-D', '-', '-o', '/tmp/fr07-sec-body.txt', '-w', "\n__CODE__:%{http_code}\n__REDIR__:%{redirect_url}", '--max-redirs', '0'];
    if ($cookie) {
        $cmd[] = '-b';
        $cmd[] = $cookie;
        $cmd[] = '-c';
        $cmd[] = $cookie;
    }
    foreach ($headers as $header) {
        $cmd[] = '-H';
        $cmd[] = $header;
    }
    if ($method !== 'GET') {
        $cmd[] = '-X';
        $cmd[] = $method;
    }
    if ($body !== null) {
        $cmd[] = '--data';
        $cmd[] = $body;
    }
    $cmd[] = $url;
    $spec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open($cmd, $spec, $pipes);
    if (!is_resource($proc)) {
        throw new RuntimeException('Unable to start curl.');
    }
    fclose($pipes[0]);
    $raw = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    preg_match('/__CODE__:(\d+)/', $raw, $codeMatch);
    preg_match('/__REDIR__:(.*)$/', $raw, $redirMatch);
    return [
        'code' => (int) ($codeMatch[1] ?? 0),
        'redirect' => trim((string) ($redirMatch[1] ?? '')),
        'body' => (string) file_get_contents('/tmp/fr07-sec-body.txt'),
    ];
}

function fr07_csrf(string $html): ?string
{
    return preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $html, $match) ? $match[1] : null;
}

function fr07_login(string $base, string $email, string $password): string
{
    $cookie = tempnam(sys_get_temp_dir(), 'fr07sec');
    $loginPage = fr07_http('GET', $base . '/auth/login.php', [], null, $cookie);
    $token = fr07_csrf($loginPage['body']);
    if (!$token) {
        throw new RuntimeException('Login CSRF missing for ' . $email);
    }
    $posted = fr07_http(
        'POST',
        $base . '/auth/login.php',
        ['Content-Type: application/x-www-form-urlencoded'],
        http_build_query([
            'csrf_token' => $token,
            'identifier' => $email,
            'password' => $password,
        ]),
        $cookie
    );
    if (!in_array($posted['code'], [200, 302, 303], true)) {
        throw new RuntimeException('Login HTTP ' . $posted['code'] . ' for ' . $email);
    }
    return $cookie;
}

$probe = @file_get_contents($base . '/auth/login.php');
if ($probe === false) {
    echo "BLOCKED  PHP server is not reachable at {$base}\n";
    echo "HTTP security: 0 passed, 0 failed, 1 blocked.\n";
    exit(0);
}

$roles = [
    'SUPER_ADMIN' => 'superadmin@example.test',
    'CHURCH_ADMIN' => 'churchadmin@example.test',
    'STAFF' => 'staff@example.test',
    'PASTOR' => 'pastor@example.test',
    'COUNSELOR' => 'counselor@example.test',
    'MINISTRY_LEADER' => 'ministryleader@example.test',
    'MEMBER' => 'member@example.test',
];
$cookies = [];
foreach ($roles as $role => $email) {
    try {
        $cookies[$role] = fr07_login($base, $email, $password);
        http_ok('HTTP login as ' . $role, true);
    } catch (Throwable $exception) {
        http_ok('HTTP login as ' . $role, false, $exception->getMessage());
    }
}

$denied = [
    ['Member cannot archive announcements', 'MEMBER', 'POST', '/admin/announcements/index.php', 403],
    ['Pastor cannot archive announcements', 'PASTOR', 'POST', '/admin/announcements/index.php', 403],
    ['Counselor cannot archive announcements', 'COUNSELOR', 'POST', '/admin/announcements/index.php', 403],
    ['Ministry leader cannot archive announcements', 'MINISTRY_LEADER', 'POST', '/admin/announcements/index.php', 403],
    ['Member cannot delete service requests', 'MEMBER', 'POST', '/admin/services/delete.php', 403],
    ['Pastor cannot delete service requests', 'PASTOR', 'POST', '/admin/services/delete.php', 403],
    ['Counselor cannot delete service requests', 'COUNSELOR', 'POST', '/admin/services/delete.php', 403],
    ['Staff cannot permanently delete service requests', 'STAFF', 'POST', '/admin/services/delete.php', 403],
    ['Member cannot deactivate personnel', 'MEMBER', 'POST', '/admin/staff/deactivate.php', 403],
    ['Pastor cannot deactivate personnel', 'PASTOR', 'POST', '/admin/staff/deactivate.php', 403],
    ['Counselor cannot deactivate personnel', 'COUNSELOR', 'POST', '/admin/staff/deactivate.php', 403],
    ['Ministry leader cannot change ministry status', 'MINISTRY_LEADER', 'POST', '/admin/ministries/status.php', 403],
    ['Member cannot deactivate users', 'MEMBER', 'POST', '/admin/users/index.php', 403],
    ['Pastor cannot open users directory', 'PASTOR', 'GET', '/admin/users/index.php', 403],
    ['Counselor cannot open users directory', 'COUNSELOR', 'GET', '/admin/users/index.php', 403],
    ['Ministry leader cannot open users directory', 'MINISTRY_LEADER', 'GET', '/admin/users/index.php', 403],
    ['Pastor cannot hide a member request', 'PASTOR', 'POST', '/member/services/hide.php', 403],
    ['Staff cannot hide a member request', 'STAFF', 'POST', '/member/services/hide.php', 403],
    ['Counselor cannot hide a member request', 'COUNSELOR', 'POST', '/member/services/hide.php', 403],
];

foreach ($denied as [$name, $role, $method, $path, $expect]) {
    if (!isset($cookies[$role])) {
        http_blocked($name, 'No session for ' . $role);
        continue;
    }
    $headers = [];
    $body = null;
    if ($method === 'POST') {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $body = http_build_query(['csrf_token' => 'invalid', 'id' => 1, 'action' => 'archive', 'user_action' => 'deactivate', 'user_id' => 1]);
    }
    $result = fr07_http($method, $base . $path, $headers, $body, $cookies[$role]);
    http_ok($name, $result['code'] === $expect, 'HTTP ' . $result['code'] . ' expected ' . $expect);
}

if (isset($cookies['STAFF'])) {
    $token = fr07_csrf(fr07_http('GET', $base . '/admin/users/index.php', [], null, $cookies['STAFF'])['body'] ?? '');
    $staffDelete = fr07_http(
        'POST',
        $base . '/admin/users/index.php',
        ['Content-Type: application/x-www-form-urlencoded'],
        http_build_query([
            'csrf_token' => $token ?: 'invalid',
            'user_action' => 'deactivate',
            'user_id' => 1,
        ]),
        $cookies['STAFF']
    );
    $deniedStaff = $staffDelete['code'] === 302 && str_contains($staffDelete['redirect'], 'error=');
    http_ok('Staff POST cannot deactivate a user', $deniedStaff, 'HTTP ' . $staffDelete['code'] . ' ' . $staffDelete['redirect']);

    $pdo = database();
    $adminRow = $pdo->query("SELECT id, username, email, status FROM users WHERE email = 'churchadmin@example.test' LIMIT 1")->fetch();
    $adminId = (int) ($adminRow['id'] ?? 0);
    $adminRoleIds = array_map('intval', $pdo->query('SELECT role_id FROM user_roles WHERE user_id = ' . $adminId)->fetchAll(PDO::FETCH_COLUMN));
    if ($adminRoleIds === []) {
        $adminRoleIds = [(int) $pdo->query("SELECT id FROM roles WHERE code = 'CHURCH_ADMIN' LIMIT 1")->fetchColumn()];
    }
    $beforeStatus = (string) ($adminRow['status'] ?? '');
    $editPage = fr07_http('GET', $base . '/admin/users/index.php', [], null, $cookies['STAFF']);
    $editToken = fr07_csrf($editPage['body'] ?? '');
    $staffEdit = fr07_http(
        'POST',
        $base . '/admin/users/edit.php?id=' . $adminId,
        ['Content-Type: application/x-www-form-urlencoded'],
        http_build_query([
            'csrf_token' => $editToken ?: 'invalid',
            'username' => (string) ($adminRow['username'] ?? 'churchadmin'),
            'email' => (string) ($adminRow['email'] ?? 'churchadmin@example.test'),
            'status' => 'INACTIVE',
            'roles' => $adminRoleIds,
            'password' => '',
        ]),
        $cookies['STAFF']
    );
    $afterStatus = (string) $pdo->query('SELECT status FROM users WHERE id = ' . $adminId)->fetchColumn();
    if ($afterStatus !== 'ACTIVE') {
        $pdo->prepare('UPDATE users SET status = :status WHERE id = :id')->execute([
            'status' => $beforeStatus ?: 'ACTIVE',
            'id' => $adminId,
        ]);
    }
    http_ok(
        'Staff edit form cannot change another user to INACTIVE',
        $afterStatus === 'ACTIVE' && $beforeStatus === 'ACTIVE' && in_array($staffEdit['code'], [200, 302, 303], true),
        'status=' . $afterStatus . ' HTTP ' . $staffEdit['code']
    );
}

if (isset($cookies['MEMBER'])) {
    $hideGet = fr07_http('GET', $base . '/member/services/hide.php', [], null, $cookies['MEMBER']);
    http_ok('Member hide endpoint rejects GET', $hideGet['code'] === 405, 'HTTP ' . $hideGet['code']);

    $pdo = database();
    $memberUserId = (int) $pdo->query("SELECT id FROM users WHERE email = 'member@example.test' LIMIT 1")->fetchColumn();
    $memberId = $memberUserId ? ServiceRequest::memberIdForUser($memberUserId) : null;
    $serviceTypeId = (int) $pdo->query('SELECT id FROM service_types WHERE is_active = 1 LIMIT 1')->fetchColumn();
    $createdRequestId = null;
    $ownReadId = null;
    $ownUnreadId = null;
    $otherNoticeId = null;
    $otherUserId = null;

    try {
        if ($memberUserId) {
            $referenceId = null;
            if ($memberId && $serviceTypeId) {
                $pdo->prepare(
                    'INSERT INTO service_requests (service_type_id, requested_by_member_id, request_number, status, subject)
                     VALUES (:type_id, :member_id, :number, "COMPLETED", :subject)'
                )->execute([
                    'type_id' => $serviceTypeId,
                    'member_id' => $memberId,
                    'number' => 'HTTP-SEC-' . strtoupper(bin2hex(random_bytes(3))),
                    'subject' => 'HTTP security linked request',
                ]);
                $createdRequestId = (int) $pdo->lastInsertId();
                $referenceId = $createdRequestId;
            }
            Notification::create($memberUserId, 'HTTP read notice', 'Delete me', 'TEST', $referenceId ? 'service_request' : null, $referenceId);
            Notification::create($memberUserId, 'HTTP unread notice', 'Keep me', 'TEST', $referenceId ? 'service_request' : null, $referenceId);
            foreach (Notification::forUser($memberUserId) as $row) {
                if ((string) $row['title'] === 'HTTP read notice') {
                    $ownReadId = (int) $row['id'];
                    Notification::markRead($ownReadId, $memberUserId);
                }
                if ((string) $row['title'] === 'HTTP unread notice') {
                    $ownUnreadId = (int) $row['id'];
                }
            }
        }

        $pdo->prepare(
            'INSERT INTO users (username, email, password_hash, status)
             VALUES (:u, :e, :p, "ACTIVE")'
        )->execute([
            'u' => 'HTTP-SEC-' . strtoupper(bin2hex(random_bytes(2))),
            'e' => 'http-sec-' . bin2hex(random_bytes(2)) . '@test',
            'p' => password_hash('Password123!', PASSWORD_DEFAULT),
        ]);
        $otherUserId = (int) $pdo->lastInsertId();
        Notification::create($otherUserId, 'Other HTTP notice', 'Must stay', 'TEST');
        $otherNoticeId = (int) $pdo->query('SELECT id FROM notifications WHERE user_id=' . $otherUserId . ' LIMIT 1')->fetchColumn();
        Notification::markRead($otherNoticeId, $otherUserId);

        $inbox = fr07_http('GET', $base . '/member/notifications/', [], null, $cookies['MEMBER']);
        $csrf = fr07_csrf($inbox['body'] ?? '');
        http_ok('Member notification inbox renders CSRF field', is_string($csrf) && $csrf !== '');

        $noCsrf = fr07_http(
            'POST',
            $base . '/member/notifications/',
            ['Content-Type: application/x-www-form-urlencoded'],
            http_build_query(['action' => 'delete', 'id' => (int) $ownReadId]),
            $cookies['MEMBER']
        );
        http_ok('Notification delete without CSRF is rejected', $noCsrf['code'] === 419, 'HTTP ' . $noCsrf['code']);

        if ($ownUnreadId && $csrf) {
            $unread = fr07_http(
                'POST',
                $base . '/member/notifications/',
                ['Content-Type: application/x-www-form-urlencoded'],
                http_build_query([
                    'csrf_token' => $csrf,
                    'action' => 'delete',
                    'id' => $ownUnreadId,
                ]),
                $cookies['MEMBER']
            );
            $unreadDenied = $unread['code'] === 302 && str_contains($unread['redirect'], 'delete_error=');
            $stillThere = (int) $pdo->query('SELECT COUNT(*) FROM notifications WHERE id=' . $ownUnreadId)->fetchColumn() === 1;
            http_ok('HTTP delete of owned unread notification is rejected', $unreadDenied && $stillThere, $unread['redirect']);
        } else {
            http_blocked('HTTP delete of owned unread notification is rejected', 'Unread fixture missing');
        }

        if ($otherNoticeId && $csrf) {
            $foreign = fr07_http(
                'POST',
                $base . '/member/notifications/',
                ['Content-Type: application/x-www-form-urlencoded'],
                http_build_query([
                    'csrf_token' => $csrf,
                    'action' => 'delete',
                    'id' => $otherNoticeId,
                ]),
                $cookies['MEMBER']
            );
            $foreignDenied = $foreign['code'] === 302 && str_contains($foreign['redirect'], 'delete_error=');
            $foreignRemains = (int) $pdo->query('SELECT COUNT(*) FROM notifications WHERE id=' . $otherNoticeId)->fetchColumn() === 1;
            http_ok('HTTP delete of another user notification is rejected', $foreignDenied && $foreignRemains);
        }

        if ($csrf) {
            $forged = fr07_http(
                'POST',
                $base . '/member/notifications/',
                ['Content-Type: application/x-www-form-urlencoded'],
                http_build_query([
                    'csrf_token' => $csrf,
                    'action' => 'delete',
                    'id' => 999999999,
                ]),
                $cookies['MEMBER']
            );
            http_ok(
                'HTTP delete of a forged notification ID is rejected',
                $forged['code'] === 302 && str_contains($forged['redirect'], 'delete_error=')
            );
        }

        if ($ownReadId && $csrf) {
            $inbox = fr07_http('GET', $base . '/member/notifications/', [], null, $cookies['MEMBER']);
            $csrf = fr07_csrf($inbox['body'] ?? '') ?: $csrf;
            $deleted = fr07_http(
                'POST',
                $base . '/member/notifications/',
                ['Content-Type: application/x-www-form-urlencoded'],
                http_build_query([
                    'csrf_token' => $csrf,
                    'action' => 'delete',
                    'id' => $ownReadId,
                ]),
                $cookies['MEMBER']
            );
            $gone = (int) $pdo->query('SELECT COUNT(*) FROM notifications WHERE id=' . $ownReadId)->fetchColumn() === 0;
            http_ok('HTTP delete of owned read notification succeeds', $deleted['code'] === 302 && $gone);
            if ($createdRequestId) {
                $requestRemains = (int) $pdo->query('SELECT COUNT(*) FROM service_requests WHERE id=' . $createdRequestId)->fetchColumn() === 1;
                http_ok('Deleting a notification leaves the linked service request intact', $requestRemains);
            }
        }
    } catch (Throwable $exception) {
        http_ok('Notification HTTP scenarios', false, $exception->getMessage());
    } finally {
        if ($ownReadId) {
            $pdo->prepare('DELETE FROM notifications WHERE id = :id')->execute(['id' => $ownReadId]);
        }
        if ($ownUnreadId) {
            $pdo->prepare('DELETE FROM notifications WHERE id = :id')->execute(['id' => $ownUnreadId]);
        }
        if ($createdRequestId) {
            $pdo->prepare('DELETE FROM notifications WHERE reference_type = "service_request" AND reference_id = :id')->execute(['id' => $createdRequestId]);
            $pdo->prepare('DELETE FROM service_requests WHERE id = :id')->execute(['id' => $createdRequestId]);
        }
        if ($otherNoticeId) {
            $pdo->prepare('DELETE FROM notifications WHERE id = :id')->execute(['id' => $otherNoticeId]);
        }
        if ($otherUserId) {
            $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $otherUserId]);
        }
    }
}

foreach ($cookies as $cookie) {
    @unlink($cookie);
}

echo "\nHTTP security: {$passed} passed, {$failed} failed, {$blocked} blocked.\n";
exit($failed === 0 ? 0 : 1);
