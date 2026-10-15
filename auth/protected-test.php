<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/csrf.php';

require_login();
$user = authenticated_user();
$roles = current_user_roles();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Protected Test - CMMP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="cmmp-shell cmmp-glass">
<main class="cmmp-main"><div class="container py-5"><div class="cmmp-card">
    <h1 class="h3">Protected page test</h1>
    <p>Signed in as <?= htmlspecialchars((string) ($user['username'] ?: $user['email']), ENT_QUOTES, 'UTF-8') ?>.</p>
    <p>Roles: <?= htmlspecialchars(implode(', ', $roles), ENT_QUOTES, 'UTF-8') ?></p>
    <form method="post" action="logout.php">
        <?= csrf_field() ?>
        <button class="btn btn-primary" type="submit">Log out</button>
    </form>
</div></div></main>
</body>
</html>