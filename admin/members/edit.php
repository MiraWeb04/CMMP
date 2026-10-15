<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/member.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$member = $id ? Member::find($id) : null;
if (!$member) { http_response_code(404); exit('Member not found.'); }
$availableUsers = Member::availableUsers((int) $member['id']);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();
    $member = array_merge($member, member_input($_POST, true));
    $errors = validate_member_data($member, false, true);
    if ($errors === []) {
        try {
            $photo = upload_member_photo($_FILES['profile_photo'] ?? null);
            if ($photo !== null) { $member['profile_photo'] = $photo; }
            Member::update((int) $member['id'], $member);
            header('Location: view.php?id=' . (int) $member['id'] . '&saved=1');
            exit;
        } catch (Throwable $exception) {
            error_log('CMMP member update failed: ' . $exception->getMessage());
            $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : 'Member could not be updated.';
        }
    }
}

$pageTitle = 'Edit member';
$breadcrumbItems = [['label' => 'Members', 'url' => 'index.php'], ['label' => 'Edit member', 'url' => 'edit.php?id=' . (int) $member['id']]];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header"><div><h1>Edit member</h1><p>Update <?= e($member['member_number']) ?> without changing its identity.</p></div></div>
<div class="cmmp-card"><h2 class="h5 mb-4">Member information</h2><?php $formAction = 'edit.php?id=' . (int) $member['id']; $submitLabel = 'Save changes'; $cancelUrl = 'view.php?id=' . (int) $member['id']; require __DIR__ . '/../../includes/member-form.php'; ?></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
