<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/registration.php';
require_once __DIR__ . '/../models/Member.php';
require_once __DIR__ . '/../models/Notification.php';

start_secure_session();
$errors = [];
$fields = [
    'username' => '',
    'email' => '',
    'first_name' => '',
    'last_name' => '',
    'date_of_birth' => '',
    'gender' => '',
];

if (is_logged_in()) {
	header('Location: ' . APP_BASE_URL . '/auth/protected-test.php');
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	require_valid_csrf_token();
	foreach (array_keys($fields) as $key) {
		$fields[$key] = trim((string) ($_POST[$key] ?? ''));
	}
	$password = (string) ($_POST['password'] ?? '');
	$passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

	if (!preg_match('/^[A-Za-z0-9._-]{3,100}$/', $fields['username'])) {
		$errors[] = 'Username must be 3-100 characters using letters, numbers, dots, underscores, or hyphens.';
	}
	if (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
		$errors[] = 'Enter a valid email address.';
	}
	if (strlen($password) < 8) {
		$errors[] = 'Password must be at least 8 characters.';
	}
	if (!hash_equals($password, $passwordConfirmation)) {
		$errors[] = 'Password confirmation does not match.';
	}

	$errors = array_merge($errors, validate_member_registration_profile($fields));

	if ($errors === []) {
		$pdo = database();
		try {
			$pdo->beginTransaction();
			$check = $pdo->prepare('SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1');
			$check->execute(['username' => $fields['username'], 'email' => $fields['email']]);

			if ($check->fetch()) {
				throw new DomainException('Registration could not be completed with those details.');
			}

			$insertUser = $pdo->prepare(
				'INSERT INTO users (username, email, password_hash, status)
				 VALUES (:username, :email, :password_hash, :status)'
			);
			$insertUser->execute([
				'username' => $fields['username'],
				'email' => $fields['email'],
				'password_hash' => password_hash($password, PASSWORD_DEFAULT),
				'status' => 'PENDING',
			]);
			$userId = (int) $pdo->lastInsertId();

			$role = $pdo->prepare('SELECT id FROM roles WHERE code = :code LIMIT 1');
			$role->execute(['code' => 'MEMBER']);
			$roleId = $role->fetchColumn();
			if (!$roleId) {
				throw new RuntimeException('Default role is not configured.');
			}

			$assignRole = $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)');
			$assignRole->execute(['user_id' => $userId, 'role_id' => $roleId]);

			Member::createFromRegistration($userId, [
				'first_name' => $fields['first_name'],
				'last_name' => $fields['last_name'],
				'email' => $fields['email'],
				'date_of_birth' => $fields['date_of_birth'],
				'gender' => $fields['gender'],
			]);
			Notification::notifyAdministrators(
				'New member registration',
				$fields['first_name'] . ' ' . $fields['last_name'] . ' (' . $fields['email'] . ') is waiting for approval.',
				'MEMBER_REGISTRATION_PENDING',
				'user',
				$userId
			);

			$pdo->commit();
			$registered = true;
		} catch (DomainException $exception) {
			if ($pdo->inTransaction()) {
				$pdo->rollBack();
			}
			$errors[] = $exception->getMessage();
		} catch (Throwable $exception) {
			if ($pdo->inTransaction()) {
				$pdo->rollBack();
			}
			error_log('CMMP registration failed: ' . $exception->getMessage());
			$errors[] = 'Registration could not be completed. Please try again.';
		}
	}
}

?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Registration - CMMP</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
	<link href="../assets/css/style.css?v=<?= (int) filemtime(__DIR__ . '/../assets/css/style.css') ?>" rel="stylesheet">
	<link rel="icon" type="image/png" href="../assets/images/uccp-logo.png">
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
					<span class="cmmp-auth-tag">Welcome</span>
					<h1>Join the Church Community</h1>
					<p>Register with your personal details. A church administrator will review and complete your membership.</p>
				</div>

				<div class="cmmp-auth-benefits">
					<div class="cmmp-auth-benefit">
						<span class="cmmp-auth-benefit-icon"><i class="fa-solid fa-hand-holding-heart"></i></span>
						<div>
							<span class="cmmp-auth-benefit-title">Care & Connection</span>
							<span class="cmmp-auth-benefit-text">Stay connected to ministries and events.</span>
						</div>
					</div>
					<div class="cmmp-auth-benefit">
						<span class="cmmp-auth-benefit-icon"><i class="fa-solid fa-calendar-days"></i></span>
						<div>
							<span class="cmmp-auth-benefit-title">Events & Services</span>
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
						<span class="cmmp-auth-small-label">Create account</span>
						<h2>Register with CMMP</h2>
					</div>
					<span class="cmmp-auth-step-badge">01</span>
				</div>

				<?php if (!empty($registered)): ?>
					<div class="alert alert-success cmmp-auth-alert">
						<i class="fa-solid fa-circle-check me-2"></i>
						Your registration was received. A church administrator must approve the account before you can sign in.
					</div>
					<a class="btn btn-primary cmmp-auth-submit" href="login.php">
						<i class="fa-solid fa-arrow-right-to-bracket me-2"></i>
						Continue to login
					</a>
				<?php else: ?>
					<?php foreach ($errors as $error): ?>
						<div class="alert alert-danger cmmp-auth-alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
					<?php endforeach; ?>

					<form method="post" novalidate class="cmmp-auth-form">
						<?= csrf_field() ?>
						<div class="row g-3">
							<div class="col-md-6">
								<label class="form-label" for="first_name">First name</label>
								<input class="form-control" id="first_name" name="first_name" value="<?= htmlspecialchars($fields['first_name'], ENT_QUOTES, 'UTF-8') ?>" required maxlength="100">
							</div>
							<div class="col-md-6">
								<label class="form-label" for="last_name">Last name</label>
								<input class="form-control" id="last_name" name="last_name" value="<?= htmlspecialchars($fields['last_name'], ENT_QUOTES, 'UTF-8') ?>" required maxlength="100">
							</div>
							<div class="col-md-6">
								<label class="form-label" for="date_of_birth">Date of birth</label>
								<input class="form-control" id="date_of_birth" name="date_of_birth" type="date" value="<?= htmlspecialchars($fields['date_of_birth'], ENT_QUOTES, 'UTF-8') ?>" required>
							</div>
							<div class="col-md-6">
								<label class="form-label" for="gender">Gender</label>
								<select class="form-select" id="gender" name="gender" required>
									<option value="">Select gender</option>
									<option value="Male" <?= $fields['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
									<option value="Female" <?= $fields['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
								</select>
							</div>
							<div class="col-md-6">
								<label class="form-label" for="username">Username</label>
								<input class="form-control" id="username" name="username" value="<?= htmlspecialchars($fields['username'], ENT_QUOTES, 'UTF-8') ?>" required autocomplete="username" placeholder="Choose a username">
							</div>
							<div class="col-md-6">
								<label class="form-label" for="email">Email address</label>
								<input class="form-control" id="email" name="email" type="email" value="<?= htmlspecialchars($fields['email'], ENT_QUOTES, 'UTF-8') ?>" required autocomplete="email" placeholder="name@email.com">
							</div>
							<div class="col-md-6">
								<label class="form-label" for="password">Password</label>
								<input class="form-control" id="password" name="password" type="password" required autocomplete="new-password" placeholder="At least 8 characters">
							</div>
							<div class="col-md-6">
								<label class="form-label" for="password_confirmation">Confirm password</label>
								<input class="form-control" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" placeholder="Repeat your password">
							</div>
						</div>
						<button class="btn btn-primary cmmp-auth-submit w-100 mt-4" type="submit">
							<i class="fa-solid fa-user-plus me-2"></i>
							Create account
						</button>
					</form>

					<div class="cmmp-auth-login">
						<span>Already have an account?</span>
						<a href="login.php">Login here</a>
					</div>
				<?php endif; ?>
			</div>
		</section>
	</section>
</main>
</body>
</html>
