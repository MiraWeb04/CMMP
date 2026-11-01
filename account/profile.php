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

$userId = (int) current_user_id();
$member = Member::findForUser($userId);
$person = Personnel::findForUser($userId);
$errors = [];
$input = [
    'first_name' => (string) ($member['first_name'] ?? ''),
    'middle_name' => (string) ($member['middle_name'] ?? ''),
    'last_name' => (string) ($member['last_name'] ?? ''),
    'suffix' => (string) ($member['suffix'] ?? ''),
    'email' => (string) ($account['email'] ?? ''),
    'phone' => (string) ($member['phone'] ?? ''),
    'address_line_1' => (string) ($member['address_line_1'] ?? ''),
    'address_line_2' => (string) ($member['address_line_2'] ?? ''),
    'city' => (string) ($member['city'] ?? ''),
    'state_province' => (string) ($member['state_province'] ?? ''),
    'postal_code' => (string) ($member['postal_code'] ?? ''),
    'country' => (string) ($member['country'] ?? 'Philippines'),
    'emergency_contact_name' => (string) ($member['emergency_contact_name'] ?? ''),
    'emergency_contact_relationship' => (string) ($member['emergency_contact_relationship'] ?? ''),
    'emergency_contact_phone' => (string) ($member['emergency_contact_phone'] ?? ''),
    'remove_profile_photo' => false,
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = array_merge($input, account_profile_payload($_POST));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();
    $errors = save_account_profile($userId, $input, $member, $_FILES['profile_photo'] ?? null);
    if ($errors === []) {
        header('Location: profile.php?saved=1');
        exit;
    }
    $member = Member::findForUser($userId) ?? $member;
}

$photoUrl = profile_photo_url(isset($member['profile_photo']) ? (string) $member['profile_photo'] : null);
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
        <p>View and update your own name, contact details, password, and profile photo.</p>
    </div>
</div>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Your profile was updated successfully.</div><?php endif; ?>
<?php if ($errors !== []): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<form class="cmmp-profile-form" method="post" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="cmmp-card h-100">
                <h2 class="h5 mb-3">Profile photo</h2>
                <div class="cmmp-profile-photo-preview mb-3">
                    <?php if ($photoUrl): ?>
                        <img id="profilePhotoPreview" src="<?= e($photoUrl) ?>" alt="Your profile photo">
                    <?php else: ?>
                        <span id="profilePhotoPreview" class="cmmp-profile-photo-fallback" aria-hidden="true"><i class="fa-solid fa-user"></i></span>
                    <?php endif; ?>
                </div>
                <p class="text-muted small"><?= $photoUrl ? 'This photo is shown in the portal header wherever your account appears.' : 'A default avatar is shown until you upload a photo.' ?></p>
                <label class="form-label" for="profile_photo">Upload or replace photo</label>
                <input class="form-control" id="profile_photo" name="profile_photo" type="file" accept="image/jpeg,image/png,image/webp">
                <div class="form-text">JPG, PNG, or WebP. Maximum 10 MB. Executable files are rejected.</div>
                <?php if ($photoUrl): ?>
                    <div class="form-check mt-3">
                        <input class="form-check-input" id="remove_profile_photo" name="remove_profile_photo" type="checkbox" value="1">
                        <label class="form-check-label" for="remove_profile_photo">Remove current photo</label>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="cmmp-card mb-4">
                <h2 class="h5">Account</h2>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Username</dt>
                    <dd class="col-sm-8"><?= e((string) ($account['username'] ?? '')) ?></dd>
                    <?php if ($person): ?>
                        <dt class="col-sm-4">Employee number</dt>
                        <dd class="col-sm-8"><?= e((string) ($person['employee_number'] ?? '')) ?></dd>
                        <dt class="col-sm-4">Position</dt>
                        <dd class="col-sm-8"><?= e((string) ($person['position_title'] ?? '')) ?></dd>
                    <?php endif; ?>
                    <dt class="col-sm-4">Role</dt>
                    <dd class="col-sm-8"><?= e(implode(' / ', current_user_roles())) ?></dd>
                </dl>
                <p class="text-muted small mb-0 mt-3">Username, employee number, and assigned roles are managed by a church administrator. Saving this page does not change your permissions.</p>
            </div>
            <div class="cmmp-card mb-4">
                <h2 class="h5">Personal information</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="first_name">First name</label>
                        <input class="form-control" id="first_name" name="first_name" value="<?= e($input['first_name']) ?>" required maxlength="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="last_name">Last name</label>
                        <input class="form-control" id="last_name" name="last_name" value="<?= e($input['last_name']) ?>" required maxlength="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="middle_name">Middle name</label>
                        <input class="form-control" id="middle_name" name="middle_name" value="<?= e($input['middle_name']) ?>" maxlength="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="suffix">Suffix</label>
                        <input class="form-control" id="suffix" name="suffix" value="<?= e($input['suffix']) ?>" maxlength="20">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" id="email" name="email" type="email" value="<?= e($input['email']) ?>" required maxlength="255">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="phone">Contact number</label>
                        <input class="form-control" id="phone" name="phone" value="<?= e($input['phone']) ?>" maxlength="30">
                    </div>
                </div>
            </div>
            <div class="cmmp-card mb-4">
                <h2 class="h5">Address</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="address_line_1">Address line 1</label>
                        <input class="form-control" id="address_line_1" name="address_line_1" value="<?= e($input['address_line_1']) ?>" maxlength="255">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="address_line_2">Address line 2</label>
                        <input class="form-control" id="address_line_2" name="address_line_2" value="<?= e($input['address_line_2']) ?>" maxlength="255">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="city">City</label>
                        <input class="form-control" id="city" name="city" value="<?= e($input['city']) ?>" maxlength="100">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="state_province">State / province</label>
                        <input class="form-control" id="state_province" name="state_province" value="<?= e($input['state_province']) ?>" maxlength="100">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="postal_code">Postal code</label>
                        <input class="form-control" id="postal_code" name="postal_code" value="<?= e($input['postal_code']) ?>" maxlength="20">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="country">Country</label>
                        <input class="form-control" id="country" name="country" value="<?= e($input['country']) ?>" maxlength="100">
                    </div>
                </div>
            </div>
            <div class="cmmp-card mb-4">
                <h2 class="h5">Emergency contact</h2>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="emergency_contact_name">Name</label>
                        <input class="form-control" id="emergency_contact_name" name="emergency_contact_name" value="<?= e($input['emergency_contact_name']) ?>" maxlength="200">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="emergency_contact_relationship">Relationship</label>
                        <input class="form-control" id="emergency_contact_relationship" name="emergency_contact_relationship" value="<?= e($input['emergency_contact_relationship']) ?>" maxlength="100">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="emergency_contact_phone">Phone</label>
                        <input class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" value="<?= e($input['emergency_contact_phone']) ?>" maxlength="30">
                    </div>
                </div>
            </div>
            <div class="cmmp-card">
                <h2 class="h5">Change password</h2>
                <p class="text-muted small">Leave these blank to keep your current password. New passwords are stored as a secure hash.</p>
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
            </div>
        </div>
    </div>
</form>
<?php
$extraScripts = <<<'HTML'
<script>
(() => {
    const input = document.getElementById('profile_photo');
    const preview = document.getElementById('profilePhotoPreview');
    if (!input || !preview) {
        return;
    }
    input.addEventListener('change', () => {
        const file = input.files && input.files[0];
        if (!file || !file.type.startsWith('image/')) {
            return;
        }
        const url = URL.createObjectURL(file);
        if (preview.tagName === 'IMG') {
            preview.src = url;
            return;
        }
        const image = document.createElement('img');
        image.id = 'profilePhotoPreview';
        image.alt = 'Your profile photo';
        image.src = url;
        preview.replaceWith(image);
    });
})();
</script>
HTML;
require __DIR__ . '/../includes/footer.php';
