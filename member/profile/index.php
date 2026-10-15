<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/member.php';

require_role('MEMBER');
$member = Member::findForUser((int) current_user_id());
if (!$member) {
    http_response_code(404);
    exit('Your member profile has not been created yet.');
}
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();
    $updates = member_input($_POST, false);
    $errors = validate_member_data($updates, false, false);
    if ($errors === []) {
        try {
            $photo = upload_member_photo($_FILES['profile_photo'] ?? null);
            if ($photo !== null) {
                $updates['profile_photo'] = $photo;
            }
            Member::update((int) $member['id'], $updates);
            header('Location: index.php?saved=1');
            exit;
        } catch (Throwable $exception) {
            error_log('CMMP member profile update failed: ' . $exception->getMessage());
            $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : 'Your profile could not be updated.';
        }
    }
    $member = array_merge($member, $updates);
}

$pageTitle = 'My profile';
$activeNav = 'profile';
$breadcrumbItems = [['label' => 'Profile', 'url' => 'index.php']];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header"><div><h1>My profile</h1><p>Keep your permitted contact and personal details current.</p></div></div>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Your profile was updated successfully.</div><?php endif; ?>
<div class="cmmp-card">
    <div class="alert alert-light border"><i class="fa-solid fa-lock text-primary me-2"></i>Membership status, member code, family associations, and ministry associations are managed by authorized church personnel.</div>
    <?php $formAction = 'index.php'; $submitLabel = 'Save profile'; $cancelUrl = 'index.php'; $showStatus = false; require __DIR__ . '/../../includes/member-form.php'; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
