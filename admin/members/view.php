<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/member.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$member = $id ? Member::find($id) : null;
if (!$member) { http_response_code(404); exit('Member not found.'); }
$pageTitle = 'Member profile';
$breadcrumbItems = [['label' => 'Members', 'url' => 'index.php'], ['label' => $member['member_number'], 'url' => 'view.php?id=' . (int) $member['id']]];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
$fullName = trim($member['first_name'] . ' ' . ($member['middle_name'] ?? '') . ' ' . $member['last_name'] . ' ' . ($member['suffix'] ?? ''));
?>
<div class="cmmp-page-header"><div><h1><?= e($fullName) ?></h1><p><?= e($member['member_number']) ?> · Member profile</p></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="index.php">Back</a><a class="btn btn-primary" href="edit.php?id=<?= (int) $member['id'] ?>">Edit</a></div></div>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Member record saved successfully.</div><?php endif; ?>
<?php if (isset($_GET['status_changed'])): ?><div class="alert alert-success">Member status updated.</div><?php endif; ?>
<div class="row g-3">
    <div class="col-lg-8"><div class="cmmp-card"><div class="d-flex justify-content-between align-items-start"><h2 class="h5">Personal information</h2><span class="badge text-bg-<?= $member['membership_status'] === 'ACTIVE' ? 'success' : 'secondary' ?>"><?= e($member['membership_status']) ?></span></div><dl class="row mb-0"><dt class="col-sm-4">Member code</dt><dd class="col-sm-8"><?= e($member['member_number']) ?></dd><dt class="col-sm-4">Date of birth</dt><dd class="col-sm-8"><?= e($member['date_of_birth'] ?? 'Not provided') ?></dd><dt class="col-sm-4">Gender</dt><dd class="col-sm-8"><?= e($member['gender'] ?? 'Not provided') ?></dd><dt class="col-sm-4">Contact</dt><dd class="col-sm-8"><?= e($member['phone'] ?? 'Not provided') ?><br><?= e($member['email'] ?? 'Not provided') ?></dd><dt class="col-sm-4">Address</dt><dd class="col-sm-8"><?= e(implode(', ', array_filter([$member['address_line_1'], $member['address_line_2'], $member['city'], $member['state_province'], $member['postal_code'], $member['country']])) ?: 'Not provided') ?></dd><dt class="col-sm-4">Date joined</dt><dd class="col-sm-8"><?= e($member['joined_at'] ?? 'Not provided') ?></dd></dl></div></div>
    <div class="col-lg-4"><div class="cmmp-card"><h2 class="h5">Account action</h2><p class="text-muted small">Deactivation preserves the member record and prevents it from appearing as active.</p><form method="post" action="toggle.php" onsubmit="return confirm('Change this member status?');"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $member['id'] ?>"><button class="btn btn-outline-<?= $member['membership_status'] === 'ACTIVE' ? 'danger' : 'success' ?>" type="submit"><?= $member['membership_status'] === 'ACTIVE' ? 'Deactivate member' : 'Activate member' ?></button></form></div></div>
    <div class="col-md-6"><div class="cmmp-card"><h2 class="h5">Baptism</h2><p class="mb-1"><?= e($member['baptism_date'] ?? 'No baptism date recorded') ?></p><p class="text-muted mb-0"><?= e($member['baptism_church'] ?? 'No baptism church recorded') ?></p></div></div>
    <div class="col-md-6"><div class="cmmp-card"><h2 class="h5">Emergency contact</h2><p class="mb-1"><?= e($member['emergency_contact_name'] ?? 'Not provided') ?></p><p class="text-muted mb-0"><?= e($member['emergency_contact_relationship'] ?? '') ?> <?= e($member['emergency_contact_phone'] ?? '') ?></p></div></div>
    <div class="col-md-6"><div class="cmmp-card"><h2 class="h5">Family association</h2><?php if ($member['families'] === []): ?><p class="text-muted mb-0">No family association recorded.</p><?php else: ?><?php foreach ($member['families'] as $family): ?><p class="mb-1"><strong><?= e($family['family_name']) ?></strong></p><p class="text-muted small"><?= e($family['relationship_to_head'] ?? 'Member') ?></p><?php endforeach; ?><?php endif; ?></div></div>
    <div class="col-md-6"><div class="cmmp-card"><h2 class="h5">Ministry association</h2><?php if ($member['ministries'] === []): ?><p class="text-muted mb-0">No ministry association recorded.</p><?php else: ?><?php foreach ($member['ministries'] as $ministry): ?><p class="mb-1"><strong><?= e($ministry['name']) ?></strong></p><p class="text-muted small"><?= e($ministry['membership_role'] ?? 'Member') ?></p><?php endforeach; ?><?php endif; ?></div></div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
