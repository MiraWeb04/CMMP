<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/member.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);
$member = ['membership_status' => 'ACTIVE'];
$availableUsers = Member::availableUsers();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();
    $member = array_merge($member, member_input($_POST, true));
    $errors = validate_member_data($member, true, true);
    if ($errors === []) {
        try {
            $photo = upload_member_photo($_FILES['profile_photo'] ?? null);
            $member['profile_photo'] = $photo;
            Member::create($member);
            header('Location: index.php?saved=1');
            exit;
        } catch (Throwable $exception) {
            error_log('CMMP member creation failed: ' . $exception->getMessage());
            $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : 'Member could not be created.';
        }
    }
}

$pageTitle = 'Add member';
$breadcrumbItems = [['label' => 'Members', 'url' => 'index.php'], ['label' => 'Add member', 'url' => 'add.php']];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header"><div><h1>Add member</h1><p>Create a church member record.</p></div></div>
<div class="cmmp-card"><h2 class="h5 mb-4">Member information</h2><?php $formAction = 'add.php'; $submitLabel = 'Create member'; $cancelUrl = 'index.php'; require __DIR__ . '/../../includes/member-form.php'; ?></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
