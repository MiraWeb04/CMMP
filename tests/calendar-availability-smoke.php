<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/ChurchCalendar.php';
require_once __DIR__ . '/../models/Event.php';
require_once __DIR__ . '/../helpers/scheduling_rules.php';

$connection = database();
$suffix = strtoupper(bin2hex(random_bytes(4)));
$eventId = null;
$createdCategoryId = null;
$facilityIds = [];
$passed = 0;
$failed = 0;

function avail_ok(string $name, bool $ok, string $detail = ''): void
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

function avail_http(string $method, string $url, array $headers = [], ?string $body = null, ?string $cookie = null): array
{
    $cmd = ['curl', '-sS', '-D', '-', '-o', '/tmp/cmmp-avail-body.txt', '-w', "\n__CODE__:%{http_code}", '--max-redirs', '0'];
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

    return [
        'code' => (int) ($codeMatch[1] ?? 0),
        'body' => (string) file_get_contents('/tmp/cmmp-avail-body.txt'),
    ];
}

function avail_csrf(string $html): string
{
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $match) !== 1
        && preg_match('/value="([^"]+)"\s+name="csrf_token"/', $html, $match) !== 1) {
        return '';
    }

    return html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
}

function avail_ensure_member_profile(PDO $connection): void
{
    $userId = (int) $connection->query("SELECT id FROM users WHERE email = 'member@example.test' LIMIT 1")->fetchColumn();
    if ($userId < 1) {
        return;
    }
    $existing = $connection->prepare('SELECT id FROM members WHERE user_id = :user_id LIMIT 1');
    $existing->execute(['user_id' => $userId]);
    if ($existing->fetchColumn() !== false) {
        return;
    }
    $connection->prepare(
        'INSERT INTO members (user_id, member_number, first_name, last_name, email, membership_status)
         VALUES (:user_id, :number, :first, :last, :email, "ACTIVE")'
    )->execute([
        'user_id' => $userId,
        'number' => 'MEM-AVAIL-001',
        'first' => 'Member',
        'last' => 'Tester',
        'email' => 'member@example.test',
    ]);
}

$requestPhp = (string) file_get_contents(__DIR__ . '/../member/services/request.php');
avail_ok(
    'Request form only explains unavailable services when some types are actually blocked',
    str_contains($requestPhp, '$hasUnavailableServices')
    && str_contains($requestPhp, 'This date is open on the church calendar. You can request any service.')
    && str_contains($requestPhp, 'Services marked unavailable conflict with a church event or activity for that service type only.')
);

$openDate = '2031-03-17';
$blockedDate = '2031-03-16';
$rangeStart = '2031-03-01 00:00:00';
$rangeEnd = '2031-04-01 00:00:00';

try {
    $categoryId = $connection->query("SELECT id FROM event_categories WHERE name = 'Worship' AND is_active = 1 LIMIT 1")->fetchColumn();
    if (!$categoryId) {
        $connection->prepare(
            'INSERT INTO event_categories (name, description, is_active)
             VALUES (:name, :description, 1)'
        )->execute([
            'name' => 'Worship',
            'description' => 'Availability smoke category ' . $suffix,
        ]);
        $categoryId = (int) $connection->lastInsertId();
        $createdCategoryId = $categoryId;
    }

    $facilityInsert = $connection->prepare(
        'INSERT INTO facilities (name, code, facility_type, facility_status)
         VALUES (:name, :code, :type, :status)'
    );
    foreach (['A', 'B'] as $label) {
        $facilityInsert->execute([
            'name' => 'Avail Smoke ' . $label . ' ' . $suffix,
            'code' => 'AV' . $label . '-' . $suffix,
            'type' => 'CHAPEL',
            'status' => 'AVAILABLE',
        ]);
        $facilityIds[] = (int) $connection->lastInsertId();
    }

    $eventId = Event::create([
        'created_by_user_id' => null,
        'category_id' => (int) $categoryId,
        'facility_id' => $facilityIds[0],
        'title' => 'Availability smoke worship ' . $suffix,
        'description' => 'Temporary worship event for calendar availability.',
        'start_datetime' => $blockedDate . ' 09:00:00',
        'end_datetime' => $blockedDate . ' 11:00:00',
        'location' => null,
        'capacity' => null,
        'registration_required' => 0,
        'registration_deadline' => null,
        'event_image' => null,
        'event_status' => 'PUBLISHED',
    ]);

    avail_ok(
        'Open calendar date stays requestable for every common service type',
        ChurchCalendar::serviceApplicableOnDate('WEDDING', $openDate)
        && ChurchCalendar::serviceApplicableOnDate('BAPTISM', $openDate)
        && ChurchCalendar::serviceApplicableOnDate('FUNERAL_SERVICE', $openDate)
        && ChurchCalendar::serviceApplicableOnDate('FACILITY_RESERVATION', $openDate)
        && ChurchCalendar::serviceApplicableOnDate('MANANITA', $openDate)
        && ChurchCalendar::serviceApplicableOnDate('PRE_MARRIAGE_COUNSELING', $openDate)
        && ChurchCalendar::serviceApplicableOnDate('PASTORAL_COUNSELING', $openDate)
        && ChurchCalendar::serviceApplicableOnDate('HOUSE_BLESSING', $openDate)
    );

    avail_ok(
        'Worship event blocks facility reservation and mañanita only',
        ChurchCalendar::serviceApplicableOnDate('FACILITY_RESERVATION', $blockedDate) === false
        && ChurchCalendar::serviceApplicableOnDate('MANANITA', $blockedDate) === false
        && ChurchCalendar::serviceApplicableOnDate('WEDDING', $blockedDate) === true
        && ChurchCalendar::serviceApplicableOnDate('HOUSE_BLESSING', $blockedDate) === true
        && scheduling_service_blocked_by_events_on_date('FACILITY_RESERVATION', $blockedDate) === true
        && scheduling_service_blocked_by_events_on_date('WEDDING', $blockedDate) === false
    );

    $unfiltered = ChurchCalendar::bookedDates($rangeStart, $rangeEnd);
    $facilityDates = ChurchCalendar::bookedDates($rangeStart, $rangeEnd, null, 'FACILITY_RESERVATION');
    $weddingDates = ChurchCalendar::bookedDates($rangeStart, $rangeEnd, null, 'WEDDING');
    $openUnfiltered = ChurchCalendar::bookedDates($openDate . ' 00:00:00', '2031-03-18 00:00:00');

    avail_ok(
        'Unfiltered calendar marks the worship day and leaves a truly open day unmarked',
        in_array($blockedDate, $unfiltered, true)
        && !in_array($openDate, $openUnfiltered, true)
    );

    avail_ok(
        'Facility reservation calendar marks the worship day as booked for that service type',
        in_array($blockedDate, $facilityDates, true)
    );

    avail_ok(
        'Wedding calendar does not treat the worship day as a whole-day booking',
        !in_array($blockedDate, $weddingDates, true)
    );

    $httpBase = 'http://127.0.0.1:8080';
    $loginProbe = @file_get_contents($httpBase . '/auth/login.php');
    if ($loginProbe === false) {
        echo "SKIP  HTTP form checks — PHP server is not reachable at {$httpBase}\n";
    } else {
        avail_ensure_member_profile($connection);
        $cookie = tempnam(sys_get_temp_dir(), 'availhttp');
        $loginPage = avail_http('GET', $httpBase . '/auth/login.php', [], null, $cookie);
        $token = avail_csrf($loginPage['body']);
        $posted = avail_http(
            'POST',
            $httpBase . '/auth/login.php?redirect=/member/services/request.php',
            ['Content-Type: application/x-www-form-urlencoded'],
            http_build_query([
                'csrf_token' => $token,
                'identifier' => 'member@example.test',
                'password' => 'Password123!',
            ]),
            $cookie
        );
        avail_ok('HTTP member login for request form', in_array($posted['code'], [200, 302, 303], true) && $token !== '');

        $openPage = avail_http('GET', $httpBase . '/member/services/request.php?preferred_date=' . $openDate, [], null, $cookie);
        avail_ok(
            'Open calendar date tells the member the date is available',
            $openPage['code'] === 200
            && str_contains($openPage['body'], 'This date is open on the church calendar. You can request any service.')
            && !str_contains($openPage['body'], 'Services marked unavailable conflict with a church event or activity for that service type only.')
            && !str_contains($openPage['body'], '(Unavailable on this date)'),
            'HTTP ' . $openPage['code']
        );

        $blockedPage = avail_http('GET', $httpBase . '/member/services/request.php?preferred_date=' . $blockedDate, [], null, $cookie);
        avail_ok(
            'Worship date explains unavailable services without blocking every type',
            $blockedPage['code'] === 200
            && str_contains($blockedPage['body'], 'Services marked unavailable conflict with a church event or activity for that service type only.')
            && str_contains($blockedPage['body'], '(Unavailable on this date)')
            && !str_contains($blockedPage['body'], 'This date is open on the church calendar. You can request any service.'),
            'HTTP ' . $blockedPage['code']
        );
        @unlink($cookie);
    }
} catch (Throwable $exception) {
    avail_ok('Availability smoke setup', false, $exception->getMessage());
} finally {
    if ($eventId !== null) {
        $connection->prepare('DELETE FROM event_assignments WHERE event_id = :id')->execute(['id' => $eventId]);
        $connection->prepare('DELETE FROM events WHERE id = :id')->execute(['id' => $eventId]);
    }
    if ($createdCategoryId !== null) {
        $connection->prepare('DELETE FROM event_categories WHERE id = :id')->execute(['id' => $createdCategoryId]);
    }
    foreach ($facilityIds as $facilityId) {
        $connection->prepare('DELETE FROM facilities WHERE id = :id')->execute(['id' => $facilityId]);
    }
}

echo "Passed: {$passed}; Failed: {$failed}\n";
exit($failed === 0 ? 0 : 1);
