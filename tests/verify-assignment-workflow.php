<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

$base = 'http://localhost/CMMP';
$cookie = tempnam(sys_get_temp_dir(), 'cmmp');

function http_request(string $url, string $cookie, bool $post = false, array $fields = []): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_HEADER => false,
    ]);
    if ($post) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code >= 400) {
        throw new RuntimeException('HTTP ' . $code . ' for ' . $url);
    }
    return (string) $body;
}

function login_as(string $identifier, string $password, string $base, string $cookie): void
{
    $login = http_request($base . '/auth/login.php', $cookie);
    if (!preg_match('/name="csrf_token" value="([^"]+)"/', $login, $match)) {
        throw new RuntimeException('CSRF token missing for ' . $identifier);
    }
    http_request($base . '/auth/login.php', $cookie, true, [
        'csrf_token' => $match[1],
        'identifier' => $identifier,
        'password' => $password,
    ]);
}

$banned = [
    'Preferred Pastor',
    'Preferred Counselor',
    'Select Pastor',
    'Select Counselor',
    'Assign me an available pastor',
    'Assign me an available counselor',
    'name="staff_id"',
];

login_as('verify_member', 'Password123!', $base, $cookie);
foreach (['WEDDING', 'BAPTISM', 'FUNERAL_SERVICE', 'HOUSE_BLESSING', 'PRE_MARRIAGE_COUNSELING', 'PASTORAL_COUNSELING', 'FACILITY_RESERVATION'] as $code) {
    $html = http_request($base . '/member/services/request.php?service_code=' . $code, $cookie);
    foreach ($banned as $needle) {
        if (stripos($html, $needle) !== false) {
            throw new RuntimeException($code . ' member form still contains: ' . $needle);
        }
    }
    if (stripos($html, 'name="preferred_date"') === false) {
        throw new RuntimeException($code . ' member form is missing the preferred date field. Page title snippet: ' . substr(strip_tags($html), 0, 180));
    }
}
echo "Member forms hide personnel selection.\n";

$cookieAdmin = tempnam(sys_get_temp_dir(), 'cmmpa');
login_as('staff@example.test', 'Password123!', $base, $cookieAdmin);
$adminIndex = http_request($base . '/admin/services/', $cookieAdmin);
if (stripos($adminIndex, 'assign pastors or counselors') === false && stripos($adminIndex, 'Service requests') === false) {
    throw new RuntimeException('Admin service list did not load.');
}

$pdo = new PDO('mysql:host=127.0.0.1;dbname=cmmp_db;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$requestId = (int) $pdo->query("SELECT id FROM service_requests WHERE status IN ('PENDING_ASSIGNMENT','PENDING','ASSIGNED','APPROVED') ORDER BY id DESC LIMIT 1")->fetchColumn();
if ($requestId > 0) {
    $adminView = http_request($base . '/admin/services/view.php?id=' . $requestId, $cookieAdmin);
    if (stripos($adminView, 'Personnel assignment') === false) {
        throw new RuntimeException('Admin assignment interface is missing.');
    }
    echo "Admin assignment interface is visible for request {$requestId}.\n";
}

@unlink($cookie);
@unlink($cookieAdmin);
echo "Assignment workflow page checks passed.\n";
