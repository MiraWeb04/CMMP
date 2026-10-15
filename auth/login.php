<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/audit.php';

start_secure_session();
$errors = [];
$identifier = '';
$redirect = safe_redirect_target($_GET['redirect'] ?? null);
$message = '';

if (isset($_GET['logged_out']) && $_GET['logged_out'] === '1') {
    $message = 'You have been logged out successfully.';
}

if (is_logged_in()) {
    header('Location: ' . $redirect);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();
    $identifier = trim((string) ($_POST['identifier'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($identifier === '' || $password === '') {
        $errors[] = 'Enter your username or email and password.';
    } else {
        $user = authenticate_user($identifier, $password);

        if ($user === null) {
            $errors[] = 'Invalid login credentials.';
        } elseif (($user['status'] ?? '') === 'PENDING') {
            $errors[] = 'Your registration is waiting for a church administrator to approve it.';
        } elseif (!isset($user['status']) || $user['status'] !== 'ACTIVE') {
            $errors[] = 'This account is not available for login.';
        } else {
            $roles = $user['roles'];

            if ($roles === []) {
                $errors[] = 'This account is not fully configured for portal access.';
            } else {
                login_user((int) $user['id'], $roles);
                audit_log('LOGIN', 'AUTHENTICATION', (int) $user['id']);
                $update = database()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
                $update->execute(['id' => $user['id']]);

                $target = $_GET['redirect'] ?? null;
                $redirect = safe_redirect_target($target);
                if ($target !== null && $target !== '' && str_starts_with($target, '/')) {
                    header('Location: ' . $redirect);
                } else {
                    header('Location: ' . APP_BASE_URL . role_redirect_path($roles));
                }
                exit;
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - CMMP</title>
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
                        <i class="fa-solid fa-church"></i>
                    </span>
                    <div>
                        <span class="cmmp-auth-kicker">CMMP</span>
                        <span class="cmmp-auth-subtitle">Church Portal</span>
                    </div>
                </a>

                <div class="cmmp-auth-heading">
                    <span class="cmmp-auth-tag">Welcome back</span>
                    <h1>Welcome to the Church Community</h1>
                    <p>Connect with your church, stay informed, manage your activities, and access church services in one place.</p>
                </div>

                <div class="cmmp-auth-benefits">
                    <div class="cmmp-auth-benefit">
                        <span class="cmmp-auth-benefit-icon"><i class="fa-solid fa-hand-holding-heart"></i></span>
                        <div>
                            <span class="cmmp-auth-benefit-title">Care &amp; Connection</span>
                            <span class="cmmp-auth-benefit-text">Stay connected to ministries and events.</span>
                        </div>
                    </div>
                    <div class="cmmp-auth-benefit">
                        <span class="cmmp-auth-benefit-icon"><i class="fa-solid fa-calendar-days"></i></span>
                        <div>
                            <span class="cmmp-auth-benefit-title">Events &amp; Services</span>
                            <span class="cmmp-auth-benefit-text">Discover opportunities to gather and serve.</span>
                        </div>
                    </div>
                    <div class="cmmp-auth-benefit">
                        <span class="cmmp-auth-benefit-icon"><i class="fa-solid fa-users"></i></span>
                        <div>
                            <span class="cmmp-auth-benefit-title">Family Fellowship</span>
                            <span class="cmmp-auth-benefit-text">Grow with our church family.</span>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <section class="cmmp-auth-card">
            <div class="cmmp-auth-card-inner">
                <div class="cmmp-auth-head">
                    <div>
                        <span class="cmmp-auth-small-label">Member access</span>
                        <h2>Login to CMMP</h2>
                    </div>
                    <span class="cmmp-auth-step-badge">02</span>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="cmmp-auth-alerts">
                        <?php foreach ($errors as $error): ?>
                            <div class="cmmp-auth-alert cmmp-auth-alert-danger">
                                <i class="fa-solid fa-circle-exclamation"></i>
                                <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($message !== ''): ?>
                    <div class="cmmp-auth-alerts">
                        <div class="cmmp-auth-alert cmmp-auth-alert-info">
                            <i class="fa-solid fa-circle-info"></i>
                            <span><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <form class="cmmp-auth-form" id="cmmpLoginForm" method="post" novalidate autocomplete="on">
                    <?= csrf_field() ?>
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="mb-3">
                        <label class="form-label" for="identifier">Email / Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                            <input class="form-control" id="identifier" name="identifier" type="text" value="<?= htmlspecialchars($identifier, ENT_QUOTES, 'UTF-8') ?>" required autofocus autocomplete="username" placeholder="Enter your email or username">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="password">Password</label>
                        <div class="input-group cmmp-auth-password-wrap">
                            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                            <input class="form-control" id="password" name="password" type="password" required autocomplete="current-password" placeholder="Enter your password">
                            <button class="cmmp-auth-password-toggle" id="passwordToggle" type="button" aria-label="Show password" aria-pressed="false" data-bs-toggle="tooltip" title="Show password">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="cmmp-auth-row">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>

                        <?php if (file_exists(__DIR__ . '/forgot-password.php')): ?>
                            <a class="cmmp-auth-link" href="forgot-password.php">Forgot password?</a>
                        <?php else: ?>
                            <a class="cmmp-auth-link" href="#">Forgot password?</a>
                        <?php endif; ?>
                    </div>

                    <button class="btn btn-primary cmmp-auth-submit w-100 mt-3" type="submit" id="loginSubmit">
                        <i class="fa-solid fa-right-to-bracket me-2"></i>Sign In
                    </button>
                </form>

                <?php if (file_exists(__DIR__ . '/register.php')): ?>
                    <div class="cmmp-auth-login">
                        <span>Don’t have an account?</span>
                        <a href="/CMMP/auth/register.php">Create account</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/auth.js"></script>
</body>
</html>

