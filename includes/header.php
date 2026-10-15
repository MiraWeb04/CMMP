<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/auth.php';

$pageTitle = $pageTitle ?? APP_TITLE;
$pageDescription = $pageDescription ?? '';
$currentUser = $currentUser ?? authenticated_user();
$currentRoles = $currentRoles ?? current_user_roles();

function e(?string $value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="description" content="<?= e($pageDescription ?: APP_TITLE) ?>">
	<title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
	<link rel="preconnect" href="https://cdn.jsdelivr.net">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
	<link rel="stylesheet" href="<?= e(APP_BASE_URL) ?>/assets/css/style.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/style.css') ?>">
	<?php if (isset($extraStyles)): ?><?= $extraStyles ?><?php endif; ?>
</head>
<body class="cmmp-shell cmmp-glass<?= isset($bodyClass) && $bodyClass !== '' ? ' ' . e($bodyClass) : '' ?>">
<?php require __DIR__ . '/navbar.php'; ?>
<div class="cmmp-layout">
	<?php require __DIR__ . '/sidebar.php'; ?>
	<main class="cmmp-main">
		<div class="container-fluid px-3 px-lg-4 py-4">
