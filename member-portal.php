<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/helpers/auth.php';
require_once __DIR__ . '/helpers/csrf.php';
require_once __DIR__ . '/helpers/audit.php';
require_once __DIR__ . '/models/Event.php';
require_once __DIR__ . '/helpers/event.php';
require_once __DIR__ . '/models/Announcement.php';
require_once __DIR__ . '/models/Ministry.php';

start_secure_session();

$errors = [];
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['identifier'], $_POST['password'])) {
    require_valid_csrf_token();

    $identifier = trim((string) ($_POST['identifier'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($identifier === '' || $password === '') {
        $errors[] = 'Enter your username or email and password.';
    } else {
        $user = authenticate_user($identifier, $password);

        if ($user === null) {
            $errors[] = 'Invalid login credentials.';
        } elseif (($user['status'] ?? '') === 'LOCKED_TEMPORARY') {
            $errors[] = 'Too many failed sign-in attempts. Wait 15 minutes and try again, or use Forgot password.';
        } elseif (($user['status'] ?? '') === 'PENDING') {
            $errors[] = 'Your registration is waiting for a church administrator to approve it.';
        } elseif (($user['status'] ?? 'INACTIVE') !== 'ACTIVE') {
            $errors[] = 'This account is not available for login.';
        } else {
            $roles = $user['roles'] ?? [];

            if ($roles === []) {
                $errors[] = 'This account is not fully configured for portal access.';
            } else {
                login_user((int) $user['id'], $roles);
                audit_log('LOGIN', 'AUTHENTICATION', (int) $user['id']);
                $update = database()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
                $update->execute(['id' => (int) $user['id']]);

                header('Location: ' . APP_BASE_URL . role_redirect_path($roles));
                exit;
            }
        }
    }
}

$currentUser = authenticated_user();
$loggedIn = $currentUser !== null;

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$events = array_slice(Event::upcoming(0), 0, 3);
$announcements = array_slice(Announcement::listForUser(['MEMBER']), 0, 1);
$ministries = array_slice(Ministry::listForUser(), 0, 6);
$featured = $events[0] ?? null;
$featuredImage = $featured ? event_image_url(isset($featured['event_image']) ? (string) $featured['event_image'] : null) : null;
$dashboardUrl = $loggedIn ? APP_BASE_URL . role_redirect_path(current_user_roles()) : APP_BASE_URL . '/auth/login.php';
$joinUrl = APP_BASE_URL . '/auth/register.php';
$loginUrl = APP_BASE_URL . '/auth/login.php';
$tileIcons = ['fa-church', 'fa-child', 'fa-children', 'fa-star', 'fa-comments', 'fa-hand-holding-heart'];
$tileFallbacks = [
    ['Weekly Services', 'Worship together and stay connected to the life of the church.'],
    ['Youth', 'A place for students to grow in faith and friendship.'],
    ['Kids', 'Safe, joyful ministry for children and their families.'],
    ['Special Events', 'Gatherings, outreach, and celebrations through the year.'],
    ['Counseling', 'Pastoral care and guidance when you need someone to walk with you.'],
    ['Benevolence', 'Practical help for people in our church and community.'],
];
$tiles = [];
foreach ($ministries as $index => $ministry) {
    $tiles[] = [
        'icon' => $tileIcons[$index % count($tileIcons)],
        'title' => (string) ($ministry['name'] ?? 'Ministry'),
        'text' => trim((string) ($ministry['description'] ?? '')) !== ''
            ? mb_strimwidth((string) $ministry['description'], 0, 90, '...')
            : 'A ministry of our church community.',
    ];
}
foreach ($tileFallbacks as $fallback) {
    if (count($tiles) >= 6) {
        break;
    }
    foreach ($tiles as $existing) {
        if (stripos($existing['title'], $fallback[0]) !== false || stripos($fallback[0], $existing['title']) !== false) {
            continue 2;
        }
    }
    $tiles[] = [
        'icon' => $tileIcons[count($tiles) % count($tileIcons)],
        'title' => $fallback[0],
        'text' => $fallback[1],
    ];
}
$spotlight = $announcements[0] ?? null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_TITLE) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <link href="<?= e(APP_BASE_URL) ?>/assets/css/style.css?v=<?= (int) filemtime(__DIR__ . '/assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="church-home">
    <header class="church-hero">
        <nav class="church-nav">
            <a class="church-brand" href="<?= e(APP_BASE_URL) ?>/member-portal.php">
                <img class="church-brand-mark cmmp-brand-logo" src="<?= e(APP_BASE_URL) ?>/assets/images/uccp-logo.png" alt="UCCP logo" width="44" height="44" decoding="async">
                <span><strong>CMMP</strong><small>UCCP Church portal</small></span>
            </a>
            <ul class="church-menu">
                <li><a class="is-current" href="<?= e(APP_BASE_URL) ?>/member-portal.php">Home</a></li>
                <li><a href="#gatherings">Events</a></li>
                <li><a href="#serve">Ministries</a></li>
                <li><a href="<?= e($loginUrl) ?>">Login</a></li>
            </ul>
            <?php if ($loggedIn): ?>
                <a class="btn btn-primary" href="<?= e($dashboardUrl) ?>">Open portal</a>
            <?php else: ?>
                <a class="btn btn-primary" href="<?= e($joinUrl) ?>">Join</a>
            <?php endif; ?>
        </nav>
        <div class="church-hero-copy">
            <div class="church-kicker">Church love, faith love</div>
            <h1>Welcome To Our Church</h1>
            <p>Stay close to worship, ministries, events, and the care of this church family. One place for members, pastors, and church staff.</p>
            <?php if ($errors !== []): ?>
                <div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div>
            <?php endif; ?>
            <?php if ($loggedIn): ?>
                <a class="btn btn-primary" href="<?= e($dashboardUrl) ?>">Go to my portal</a>
            <?php else: ?>
                <a class="btn btn-primary" href="<?= e($joinUrl) ?>">I'm New Here</a>
            <?php endif; ?>
        </div>
    </header>

    <section class="church-float">
        <article class="church-live">
            <?php if ($featuredImage): ?>
                <img src="<?= e($featuredImage) ?>" alt="<?= e((string) ($featured['title'] ?? 'Upcoming gathering')) ?>">
            <?php else: ?>
                <div class="church-live-fallback" aria-hidden="true"><i class="fa-solid fa-church"></i></div>
            <?php endif; ?>
            <div>
                <h2><?= e($featured ? (string) $featured['title'] : 'Join us this Sunday') ?></h2>
                <p><?= e($featured ? date('l, F j, Y · g:i A', strtotime((string) $featured['start_datetime'])) : 'Worship, fellowship, and a place to belong.') ?></p>
                <div class="church-live-actions">
                    <?php if ($featured): ?>
                        <?php $featuredPath = '/CMMP/member/events/view.php?id=' . (int) $featured['id']; ?>
                        <a class="btn btn-primary btn-sm" href="<?= e($loggedIn ? APP_BASE_URL . $featuredPath : $loginUrl . '?redirect=' . rawurlencode($featuredPath)) ?>">Join now</a>
                    <?php else: ?>
                        <a class="btn btn-primary btn-sm" href="<?= e($loggedIn ? $dashboardUrl : $joinUrl) ?>">Join now</a>
                    <?php endif; ?>
                    <a class="church-text-link" href="#gatherings">View events</a>
                </div>
            </div>
        </article>
    </section>

    <section class="church-section" id="serve">
        <div class="church-services">
            <div>
                <div class="church-kicker is-muted">Our services</div>
                <h2>We Love Serving Our Local Community</h2>
                <p><?= e($spotlight ? mb_strimwidth((string) ($spotlight['body'] ?? $spotlight['title'] ?? ''), 0, 180, '...') : 'From weekly worship to youth, kids, counseling, and care, there is a place to serve and a place to be known.') ?></p>
                <a class="btn btn-primary" href="<?= e($loggedIn ? $dashboardUrl : $joinUrl) ?>">Learn more</a>
            </div>
            <div class="church-grid">
                <?php foreach ($tiles as $tile): ?>
                    <article class="church-tile">
                        <i class="fa-solid <?= e($tile['icon']) ?>"></i>
                        <h3><?= e($tile['title']) ?></h3>
                        <p><?= e($tile['text']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="church-section church-series" id="gatherings">
        <div class="church-kicker is-muted">Upcoming gatherings</div>
        <h2>Life of the Church</h2>
        <p>See what is happening next, then open an event for the details, location, and registration.</p>
        <?php if ($events === []): ?>
            <p class="church-empty">New gatherings will appear here once they are published.</p>
        <?php else: ?>
            <div class="church-events">
                <?php foreach ($events as $event): ?>
                    <?php $eventPath = '/CMMP/member/events/view.php?id=' . (int) $event['id']; ?>
                    <article class="church-event-card">
                        <?php $eventCoverClass = ''; require __DIR__ . '/includes/event-cover.php'; ?>
                        <div class="church-event-meta"><?= e((string) ($event['category_name'] ?? 'Church event')) ?></div>
                        <h3><?= e((string) $event['title']) ?></h3>
                        <time datetime="<?= e((string) $event['start_datetime']) ?>"><?= e(date('F j, Y', strtotime((string) $event['start_datetime']))) ?></time>
                        <a class="church-more" href="<?= e($loggedIn ? APP_BASE_URL . $eventPath : $loginUrl . '?redirect=' . rawurlencode($eventPath)) ?>">View the event</a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <footer class="church-footer">
        <div class="church-footer-inner">
            <span>&copy; <?= date('Y') ?> <?= e(APP_TITLE) ?></span>
            <a href="<?= e($loggedIn ? $dashboardUrl : $loginUrl) ?>"><?= $loggedIn ? 'Open portal' : 'Member login' ?></a>
        </div>
    </footer>
</body>
</html>
