<?php
declare(strict_types=1);

$passed = 0;
$failed = 0;

function cal_ok(string $name, bool $ok, string $detail = ''): void
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

$member = (string) file_get_contents(__DIR__ . '/../member/services/calendar.php');
$admin = (string) file_get_contents(__DIR__ . '/../admin/services/calendar.php');
$js = (string) file_get_contents(__DIR__ . '/../assets/js/church-calendar.js');
$csp = (string) file_get_contents(__DIR__ . '/../helpers/http.php');

cal_ok(
    'CSP does not allow inline scripts',
    str_contains($csp, "script-src 'self' https://cdn.jsdelivr.net")
    && !str_contains($csp, "script-src 'self' 'unsafe-inline'")
);

cal_ok(
    'Member calendar has no inline calendar init script',
    !str_contains($member, 'cmmpInitChurchCalendar')
    && str_contains($member, 'church-calendar.js')
    && str_contains($member, 'data-initial-date')
);

cal_ok(
    'Admin calendar has no inline calendar init script',
    !str_contains($admin, 'cmmpInitChurchCalendar')
    && str_contains($admin, 'church-calendar.js')
    && str_contains($admin, 'data-initial-date')
);

cal_ok(
    'Calendar JS auto-starts FullCalendar from the page markup',
    str_contains($js, 'cmmpInitChurchCalendar')
    && str_contains($js, "document.getElementById('church-calendar')")
    && str_contains($js, 'dataset.calendarReady')
);

cal_ok(
    'Calendar JS still opens the request form when a member picks a date',
    str_contains($js, 'preferred_date')
    && str_contains($js, 'dataset.requestUrl')
);

echo "Passed: {$passed}; Failed: {$failed}\n";
exit($failed === 0 ? 0 : 1);
