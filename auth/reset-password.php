<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/http.php';
require_once __DIR__ . '/../models/User.php';

start_secure_session();
send_security_headers();

if (is_logged_in()) {
    header('Location: ' . APP_BASE_URL . role_redirect_path(current_user_roles()));
    exit;
}

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$errors = [];
$saved = false;

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    $errors[] = 'This reset link is missing or invalid. Request a new one.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors === []) {
    require_valid_csrf_token();
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if (!hash_equals($password, $confirmation)) {
        $errors[] = 'Password confirmation does not match.';
    }
    if ($errors === []) {
        try {
            $saved = User::consumePasswordReset($token, $password);
            if (!$saved) {
                $errors[] = 'This reset link has expired or was already used. Request a new one.';
            }
        } catch (Throwable $exception) {
            error_log('CMMP password reset failed: ' . $exception->getMessage());
            $errors[] = 'The password could not be updated. Try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password - CMMP</title>
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
                    <h1>Choose a new password</h1>
                    <p>Use at least 8 characters. After you save, sign in with the new password.</p>
                </div>
            </div>
        </aside>
        <section class="cmmp-auth-card">
            <div class="cmmp-auth-card-inner">
                <div class="cmmp-auth-head">
                    <div>
                        <span class="cmmp-auth-small-label">Reset password</span>
                        <h2><?= $saved ? 'Password updated' : 'Create a new password' ?></h2>
                    </div>
                </div>
                <?php if ($saved): ?>
                    <div class="cmmp-auth-alerts">
                        <div class="cmmp-auth-alert cmmp-auth-alert-info">
                            <i class="fa-solid fa-circle-check"></i>
                            <span>Your password was updated. You can sign in now.</span>
                        </div>
                    </div>
                    <a class="btn btn-primary cmmp-auth-submit w-100" href="login.php">Sign in</a>
                <?php else: ?>
                    <?php foreach ($errors as $error): ?>
                        <div class="cmmp-auth-alerts">
                            <div class="cmmp-auth-alert cmmp-auth-alert-danger">
                                <i class="fa-solid fa-circle-exclamation"></i>
                                <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($token !== '' && preg_match('/^[a-f0-9]{64}$/', $token)): ?>
                    <form class="cmmp-auth-form" method="post" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="mb-3">
                            <label class="form-label" for="password">New password</label>
                            <input class="form-control" id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password_confirmation">Confirm password</label>
                            <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password">
                        </div>
                        <button class="btn btn-primary cmmp-auth-submit w-100" type="submit">Save new password</button>
                    </form>
                    <?php endif; ?>
                    <div class="cmmp-auth-login">
                        <span>Need a new link?</span>
                        <a href="forgot-password.php">Request reset</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </section>
</main>
</body>
</html>
