<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/datetime.php';
require_once __DIR__ . '/../models/Announcement.php';
require_once __DIR__ . '/../models/AuditLog.php';

$passed = 0;
$failed = 0;

function tz_ok(string $name, bool $ok, string $detail = ''): void
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

function tz_feed_ids(): array
{
    return array_map(static fn (array $row): int => (int) $row['id'], Announcement::listForUser(['MEMBER']));
}

$pdo = database();
$suffix = strtoupper(bin2hex(random_bytes(3)));
$userId = null;
$announcementIds = [];

try {
    $pdo->prepare(
        'INSERT INTO users (username, email, password_hash, status)
         VALUES (:username, :email, :password_hash, "ACTIVE")'
    )->execute([
        'username' => 'ANN-TZ-' . $suffix,
        'email' => 'ann-tz-' . $suffix . '@test',
        'password_hash' => password_hash('Password123!', PASSWORD_DEFAULT),
    ]);
    $userId = (int) $pdo->lastInsertId();

    $phpNow = app_now_sql();
    $utcNow = utc_now_sql();
    $mysqlNow = (string) $pdo->query('SELECT NOW()')->fetchColumn();
    $mysqlSessionTz = (string) $pdo->query('SELECT @@session.time_zone')->fetchColumn();
    $phpAheadOfMysql = strtotime($phpNow) > strtotime($mysqlNow);

    tz_ok(
        'PHP application clock is Asia/Manila',
        app_timezone()->getName() === 'Asia/Manila',
        'PHP=' . $phpNow . ' UTC=' . $utcNow . ' MySQL NOW()=' . $mysqlNow . ' session_tz=' . $mysqlSessionTz
    );

    $currentId = Announcement::save([
        'user_id' => $userId,
        'title' => 'TZ current ' . $suffix,
        'body' => 'Visible when PHP and MySQL clocks disagree',
        'audience_role' => 'ALL',
        'published_at' => $phpNow,
        'expires_at' => null,
        'status' => 'PUBLISHED',
    ]);
    $announcementIds[] = $currentId;

    tz_ok(
        'Timestamp comparisons work when PHP and MySQL use different timezones',
        in_array($currentId, tz_feed_ids(), true),
        $phpAheadOfMysql
            ? 'PHP is ahead of MySQL NOW(); announcement still visible'
            : 'clocks aligned; announcement still visible'
    );
    tz_ok(
        'Published announcement appears in the member feed',
        in_array($currentId, tz_feed_ids(), true)
    );

    if ($phpAheadOfMysql) {
        $skewedPublishedAt = date('Y-m-d H:i:s', strtotime($mysqlNow) + 60);
        $skewedId = Announcement::save([
            'user_id' => $userId,
            'title' => 'TZ skewed ' . $suffix,
            'body' => 'After MySQL NOW but at or before PHP now',
            'audience_role' => 'ALL',
            'published_at' => $skewedPublishedAt,
            'expires_at' => null,
            'status' => 'PUBLISHED',
        ]);
        $announcementIds[] = $skewedId;
        $mysqlHidesSkewed = (bool) (function () use ($pdo, $skewedPublishedAt): int {
            $query = $pdo->prepare('SELECT :published_at > NOW()');
            $query->execute(['published_at' => $skewedPublishedAt]);
            return (int) $query->fetchColumn();
        })();
        tz_ok(
            'Announcement after MySQL NOW() but due on the PHP clock is visible',
            $mysqlHidesSkewed && in_array($skewedId, tz_feed_ids(), true),
            'published_at=' . $skewedPublishedAt
        );
    } else {
        tz_ok(
            'Announcement after MySQL NOW() but due on the PHP clock is visible',
            true,
            'MySQL NOW() is not behind PHP; skew case skipped'
        );
    }

    Announcement::archive($currentId, $userId);
    tz_ok('Archiving an announcement removes it from the member feed', !in_array($currentId, tz_feed_ids(), true));

    Announcement::restore($currentId, $userId);
    $restoredStatus = (string) $pdo->query(
        'SELECT announcement_status FROM announcements WHERE id = ' . $currentId
    )->fetchColumn();
    tz_ok('Restored announcement is PUBLISHED', $restoredStatus === 'PUBLISHED');
    tz_ok(
        'Restoring an eligible published announcement makes it visible again',
        in_array($currentId, tz_feed_ids(), true)
    );

    $scheduledId = Announcement::save([
        'user_id' => $userId,
        'title' => 'TZ scheduled ' . $suffix,
        'body' => 'Do not show yet',
        'audience_role' => 'ALL',
        'published_at' => app_now()->modify('+2 hours')->format('Y-m-d H:i:s'),
        'expires_at' => null,
        'status' => 'PUBLISHED',
    ]);
    $announcementIds[] = $scheduledId;
    tz_ok(
        'Future-scheduled announcements remain hidden until their publication time',
        !in_array($scheduledId, tz_feed_ids(), true)
    );

    Announcement::archive($scheduledId, $userId);
    Announcement::restore($scheduledId, $userId);
    tz_ok(
        'Restoring a future-scheduled announcement does not publish it early',
        !in_array($scheduledId, tz_feed_ids(), true)
    );

    $expiredId = Announcement::save([
        'user_id' => $userId,
        'title' => 'TZ expired ' . $suffix,
        'body' => 'Already expired',
        'audience_role' => 'ALL',
        'published_at' => app_now()->modify('-2 hours')->format('Y-m-d H:i:s'),
        'expires_at' => app_now()->modify('-1 minute')->format('Y-m-d H:i:s'),
        'status' => 'PUBLISHED',
    ]);
    $announcementIds[] = $expiredId;
    tz_ok('Expired announcements stay hidden from the member feed', !in_array($expiredId, tz_feed_ids(), true));

    $precisePublishedAt = app_now()->modify('-10 minutes')->format('Y-m-d H:i') . ':37';
    $editId = Announcement::save([
        'user_id' => $userId,
        'title' => 'TZ edit ' . $suffix,
        'body' => 'Keep the original publication second',
        'audience_role' => 'ALL',
        'published_at' => $precisePublishedAt,
        'expires_at' => null,
        'status' => 'PUBLISHED',
    ]);
    $announcementIds[] = $editId;
    $storedBefore = (string) $pdo->query('SELECT published_at FROM announcements WHERE id = ' . $editId)->fetchColumn();
    Announcement::save([
        'user_id' => $userId,
        'title' => 'TZ edit ' . $suffix,
        'body' => 'Edited title only',
        'audience_role' => 'ALL',
        'published_at' => datetime_local_value($storedBefore),
        'expires_at' => null,
        'status' => 'PUBLISHED',
    ], $editId);
    $storedAfter = (string) $pdo->query('SELECT published_at FROM announcements WHERE id = ' . $editId)->fetchColumn();
    tz_ok(
        'Editing an announcement does not shift its publication time',
        $storedBefore === $precisePublishedAt && $storedAfter === $precisePublishedAt,
        'before=' . $storedBefore . ' after=' . $storedAfter
    );
    tz_ok(
        'Edited eligible announcement remains visible in the member feed',
        in_array($editId, tz_feed_ids(), true)
    );

    $auditActions = $pdo->prepare(
        'SELECT action FROM activity_logs WHERE entity_type = "ANNOUNCEMENT" AND entity_id = :id ORDER BY id'
    );
    $auditActions->execute(['id' => $currentId]);
    $actions = $auditActions->fetchAll(PDO::FETCH_COLUMN);
    tz_ok(
        'Archive and restore audit logs are preserved',
        in_array('ANNOUNCEMENT_ARCHIVED', $actions, true) && in_array('ANNOUNCEMENT_RESTORED', $actions, true)
    );
} catch (Throwable $exception) {
    tz_ok('Announcement timezone suite ran without exceptions', false, $exception->getMessage());
} finally {
    if ($announcementIds !== []) {
        $idList = implode(',', array_map('intval', $announcementIds));
        $pdo->exec('DELETE FROM notifications WHERE reference_type = "announcement" AND reference_id IN (' . $idList . ')');
        $pdo->exec('DELETE FROM activity_logs WHERE entity_type = "ANNOUNCEMENT" AND entity_id IN (' . $idList . ')');
        $pdo->exec('DELETE FROM announcements WHERE id IN (' . $idList . ')');
    }
    if ($userId) {
        $pdo->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
    }
}

echo "Passed: {$passed}; Failed: {$failed}\n";
exit($failed === 0 ? 0 : 1);
