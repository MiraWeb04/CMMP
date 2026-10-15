<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Authentication';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> - CMMP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="cmmp-shell cmmp-glass">
    <main class="cmmp-main">
        <section class="container py-5">
        <section class="cmmp-card mx-auto text-center" style="max-width: 560px;">
            <h1 class="h3"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="text-secondary">This CMMP feature will be implemented in a later step.</p>
            <a class="btn btn-primary" href="../index.php">Return to CMMP</a>
        </section>
        </section>
    </main>
</body>
</html>
