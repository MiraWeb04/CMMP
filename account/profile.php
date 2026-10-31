<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/csrf.php';
require_once __DIR__ . '/../helpers/account_profile.php';
require_once __DIR__ . '/../models/Personnel.php';

require_login();

$account = authenticated_user();
if (!$account) {
    header('Location: ' . APP_BASE_URL . '/auth/login.php');
    exit;
}

$userId = (int) $account['id'];
$member = Member::findForUser($userId);
$person = Personnel::findForUser($userId);
$errors = [];
$input = [
    'email' => (string) ($account['email'] ?? ''),
    'phone' => (string) ($member['phone'] ?? ''),
    'address_line_1' => (string) ($member['address_line_1'] ?? ''),
    'address_line_2' => (string) ($member['address_line_2'] ?? ''),
    'city' => (string) ($member['city'] ?? ''),
    'emergency_contact_name' => (string) ($member['emergency_contact_name'] ?? ''),
    'emergency_contact_relationship' => (string) ($member['emergency_contact_relationship'] ?? ''),
    'emergency_contact_phone' => (string) ($member['emergency_contact_phone'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();
    $input = array_merge($input, account_profile_payload($_POST));
    $errors = save_account_profile($userId, $input, $member);
    if ($errors === []) {
        header('Location: profile.php?saved=1');
        exit;
    }
}

$pageTitle = 'My Profile';
$activeNav = 'account-profile';
$breadcrumbItems = [['label' => 'My Profile', 'url' => 'profile.php']];
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/breadcrumb.php';
?>
<div class="cmmp-page-header">
    <div>
        <span class="cmmp-kicker">Account</span>
        <h1>My Profile</h1>
        <p>Update the contact details and password for your church account.</p>
    </div>
</div>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Your profile was updated successfully.</div><?php endif; ?>
<?php if ($errors !== []): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<div class="cmmp-card">
    <dl class="row">
        <dt class="col-sm-4">Username</dt>
        <dd class="col-sm-8"><?= e((string) ($account['username'] ?? '')) ?></dd>
        <?php if ($person): ?>
            <dt class="col-sm-4">Name</dt>
            <dd class="col-sm-8"><?= e((string) ($person['display_name'] ?? '')) ?></dd>
            <dt class="col-sm-4">Employee number</dt>
            <dd class="col-sm-8"><?= e((string) ($person['employee_number'] ?? '')) ?></dd>
            <dt class="col-sm-4">Position</dt>
            <dd class="col-sm-8"><?= e((string) ($person['position_title'] ?? '')) ?></dd>
        <?php elseif ($member): ?>
            <dt class="col-sm-4">Name</dt>
            <dd class="col-sm-8"><?= e(trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''))) ?></dd>
        <?php endif; ?>
        <dt class="col-sm-4">Role</dt>
        <dd class="col-sm-8"><?= e(implode(' / ', current_user_roles())) ?></dd>
    </dl>
    <p class="text-muted small">Username, employee number, and assigned roles are managed by a church administrator.</p>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="email">Email</label>
                <input class="form-control" id="email" name="email" type="email" value="<?= e($input['email']) ?>" required>
            </div>
            <?php if ($member): ?>
                <div class="col-md-6">
                    <label class="form-label" for="phone">Phone</label>
                    <input class="form-control" id="phone" name="phone" value="<?= e($input['phone']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="address_line_1">Address</label>
                    <input class="form-control" id="address_line_1" name="address_line_1" value="<?= e($input['address_line_1']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="address_line_2">Address line 2</label>
                    <input class="form-control" id="address_line_2" name="address_line_2" value="<?= e($input['address_line_2']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="city">City</label>
                    <input class="form-control" id="city" name="city" value="<?= e($input['city']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="emergency_contact_name">Emergency contact</label>
                    <input class="form-control" id="emergency_contact_name" name="emergency_contact_name" value="<?= e($input['emergency_contact_name']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="emergency_contact_relationship">Relationship</label>
                    <input class="form-control" id="emergency_contact_relationship" name="emergency_contact_relationship" value="<?= e($input['emergency_contact_relationship']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="emergency_contact_phone">Emergency phone</label>
                    <input class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" value="<?= e($input['emergency_contact_phone']) ?>">
                </div>
            <?php endif; ?>
        </div>
        <hr class="my-4">
        <h2 class="h5">Change password</h2>
        <p class="text-muted small">Leave these blank to keep your current password.</p>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="current_password">Current password</label>
                <input class="form-control" id="current_password" name="current_password" type="password" autocomplete="current-password">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="password">New password</label>
                <input class="form-control" id="password" name="password" type="password" minlength="8" autocomplete="new-password">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="password_confirmation">Confirm new password</label>
                <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password">
            </div>
        </div>
        <button class="btn btn-primary mt-4" type="submit">Save profile</button>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
