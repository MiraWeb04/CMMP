<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/http.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../services/MailService.php';

start_secure_session();
send_security_headers();

if (is_logged_in()) {
    header('Location: ' . APP_BASE_URL . role_redirect_path(current_user_roles()));
    exit;
}

$errors = [];
$sent = isset($_GET['sent']);
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();
    $email = trim((string) ($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter the email address on your CMMP account.';
    } else {
        $user = User::findByEmail($email);
        if ($user && in_array((string) $user['status'], ['ACTIVE', 'LOCKED'], true)) {
            try {
                $token = User::createPasswordReset((int) $user['id']);
                $resetUrl = rtrim((string) APP_BASE_URL, '/') . '/auth/reset-password.php?token=' . urlencode($token);
                $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $html = '<p>A password reset was requested for your CMMP account.</p>'
                    . '<p><a href="' . $safeUrl . '">Choose a new password</a></p>'
                    . '<p>This link expires in ' . User::RESET_TTL_MINUTES . ' minutes. If you did not request it, you can ignore this email.</p>';
                MailService::trySend((string) $user['email'], 'CMMP password reset', $html, true);
            } catch (Throwable $exception) {
                error_log('CMMP password reset request failed: ' . $exception->getMessage());
            }
        }
        header('Location: forgot-password.php?sent=1');
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password - CMMP</title>
    <link rel="icon" type="image/png" href="../assets/images/uccp-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="cmmp-auth-body">
<main class="cmmp-auth-page">
    <section class="cmmp-auth-wrap">
        <aside class="cmmp-auth-side">
            <div class="cmmp-auth-side-inner">
                <a class="cmmp-auth-brand-row" href="<?= htmlspecialchars(APP_BASE_URL, ENT_QUOTES, 'UTF-8') ?>/member-portal.php">
                    <span class="cmmp-auth-logo">
                        <img src="../assets/images/uccp-logo.png" alt="UCCP logo" width="56" height="56" decoding="async">
                    </span>
                    <div>
                        <span class="cmmp-auth-kicker">CMMP</span>
                        <span class="cmmp-auth-subtitle">Church Portal</span>
                    </div>
                </a>
                <div class="cmmp-auth-heading">
                    <span class="cmmp-auth-tag">Account recovery</span>
                    <h1>Reset your password</h1>
                    <p>Enter the email on your church account. If it matches an active user, we send a one-time reset link.</p>
                </div>
            </div>
        </aside>
        <section class="cmmp-auth-card">
            <div class="cmmp-auth-card-inner">
                <div class="cmmp-auth-head">
                    <div>
                        <span class="cmmp-auth-small-label">Forgot password</span>
                        <h2>Request a reset link</h2>
                    </div>
                </div>
                <?php if ($sent): ?>
                    <div class="cmmp-auth-alerts">
                        <div class="cmmp-auth-alert cmmp-auth-alert-info">
                            <i class="fa-solid fa-circle-info"></i>
                            <span>If that email belongs to a CMMP account, a reset link is on its way. Check your inbox and spam folder.</span>
                        </div>
                    </div>
                    <a class="btn btn-primary cmmp-auth-submit w-100" href="login.php">Return to login</a>
                <?php else: ?>
                    <?php foreach ($errors as $error): ?>
                        <div class="cmmp-auth-alerts">
                            <div class="cmmp-auth-alert cmmp-auth-alert-danger">
                                <i class="fa-solid fa-circle-exclamation"></i>
                                <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <form class="cmmp-auth-form" method="post" novalidate>
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label" for="email">Email address</label>
                            <input class="form-control" id="email" name="email" type="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required autocomplete="email" placeholder="name@email.com">
                        </div>
                        <button class="btn btn-primary cmmp-auth-submit w-100" type="submit">Send reset link</button>
                    </form>
                    <div class="cmmp-auth-login">
                        <span>Remembered your password?</span>
                        <a href="login.php">Login here</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </section>
</main>
</body>
</html>
