<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../models/Personnel.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$person = $id ? Personnel::find($id) : null;
if (!$person) {
    http_response_code(404);
    exit('Staff record not found.');
}
if (Personnel::isClergy($person)) {
    header('Location: ' . Personnel::clergyDirectoryPath($person));
    exit;
}

$pageTitle = 'View staff';
$activeNav = 'staff';
$breadcrumbItems = [['label' => 'Staff', 'url' => 'index.php'], ['label' => 'View staff', 'url' => 'view.php?id=' . $id]];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header"><div><h1><?= e($person['display_name']) ?></h1><p>Staff personnel details.</p></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="index.php">Back to staff</a><a class="btn btn-primary" href="edit.php?id=<?= (int) $id ?>">Edit</a></div></div>
<div class="cmmp-card"><dl class="row mb-0"><dt class="col-sm-4">Employee number</dt><dd class="col-sm-8"><?= e($person['employee_number']) ?></dd><dt class="col-sm-4">Portal account</dt><dd class="col-sm-8"><?= e($person['username'] ?? 'No linked account') ?></dd><dt class="col-sm-4">Email</dt><dd class="col-sm-8"><?= e($person['email'] ?? 'Not provided') ?></dd><dt class="col-sm-4">Position</dt><dd class="col-sm-8"><?= e($person['position_title']) ?></dd><dt class="col-sm-4">Personnel type</dt><dd class="col-sm-8"><?= e($person['personnel_type']) ?></dd><dt class="col-sm-4">Employment status</dt><dd class="col-sm-8"><?= e($person['employment_status']) ?></dd><dt class="col-sm-4">Hired date</dt><dd class="col-sm-8"><?= e($person['hired_at'] ?? 'Not set') ?></dd></dl></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
