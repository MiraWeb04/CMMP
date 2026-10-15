<?php
declare(strict_types=1);

$member = $member ?? [];
$formAction = $formAction ?? '';
$submitLabel = $submitLabel ?? 'Save member';
$showStatus = $showStatus ?? true;
$errors = $errors ?? [];

$memberValue = static function (string $field) use ($member): string {
    return htmlspecialchars((string) ($member[$field] ?? ''), ENT_QUOTES, 'UTF-8');
};
?>
<?php if ($errors !== []): ?>
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<form method="post" action="<?= e($formAction) ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
        <?php if ($showStatus): ?>
            <div class="col-md-6">
                <label class="form-label" for="user_id">Portal account</label>
                <select class="form-select" id="user_id" name="user_id">
                    <option value="">No linked account</option>
                    <?php foreach (($availableUsers ?? []) as $user): ?>
                        <option value="<?= (int) $user['id'] ?>" <?= (string) ($member['user_id'] ?? '') === (string) $user['id'] ? 'selected' : '' ?>><?= e($user['username'] . ' - ' . $user['email']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Link an existing active portal account to enable self-service profile access.</div>
            </div>
        <?php endif; ?>
        <div class="col-md-4">
            <label class="form-label" for="member_number">Member code</label>
            <input class="form-control" id="member_number" name="member_number" value="<?= $memberValue('member_number') ?>" <?= isset($member['id']) ? 'readonly' : 'required' ?> maxlength="50">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="first_name">First name</label>
            <input class="form-control" id="first_name" name="first_name" value="<?= $memberValue('first_name') ?>" required maxlength="100">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="last_name">Last name</label>
            <input class="form-control" id="last_name" name="last_name" value="<?= $memberValue('last_name') ?>" required maxlength="100">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="middle_name">Middle name</label>
            <input class="form-control" id="middle_name" name="middle_name" value="<?= $memberValue('middle_name') ?>" maxlength="100">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="suffix">Suffix</label>
            <input class="form-control" id="suffix" name="suffix" value="<?= $memberValue('suffix') ?>" maxlength="20">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="date_of_birth">Date of birth</label>
            <input class="form-control" id="date_of_birth" name="date_of_birth" type="date" value="<?= $memberValue('date_of_birth') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="gender">Gender</label>
            <select class="form-select" id="gender" name="gender">
                <option value="">Select</option>
                <?php foreach (['Female', 'Male', 'Non-binary', 'Prefer not to say'] as $gender): ?>
                    <option value="<?= e($gender) ?>" <?= ($member['gender'] ?? '') === $gender ? 'selected' : '' ?>><?= e($gender) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="phone">Contact number</label>
            <input class="form-control" id="phone" name="phone" value="<?= $memberValue('phone') ?>" maxlength="30">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" id="email" name="email" type="email" value="<?= $memberValue('email') ?>" maxlength="255">
        </div>
        <div class="col-12"><hr><h2 class="h6">Address</h2></div>
        <div class="col-md-6">
            <label class="form-label" for="address_line_1">Address line 1</label>
            <input class="form-control" id="address_line_1" name="address_line_1" value="<?= $memberValue('address_line_1') ?>" maxlength="255">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="address_line_2">Address line 2</label>
            <input class="form-control" id="address_line_2" name="address_line_2" value="<?= $memberValue('address_line_2') ?>" maxlength="255">
        </div>
        <div class="col-md-4"><label class="form-label" for="city">City</label><input class="form-control" id="city" name="city" value="<?= $memberValue('city') ?>" maxlength="100"></div>
        <div class="col-md-4"><label class="form-label" for="state_province">State / province</label><input class="form-control" id="state_province" name="state_province" value="<?= $memberValue('state_province') ?>" maxlength="100"></div>
        <div class="col-md-4"><label class="form-label" for="postal_code">Postal code</label><input class="form-control" id="postal_code" name="postal_code" value="<?= $memberValue('postal_code') ?>" maxlength="20"></div>
        <div class="col-md-4"><label class="form-label" for="country">Country</label><input class="form-control" id="country" name="country" value="<?= $memberValue('country') ?>" maxlength="100"></div>
        <div class="col-12"><hr><h2 class="h6">Membership information</h2></div>
        <?php if ($showStatus): ?>
            <div class="col-md-4">
                <label class="form-label" for="membership_status">Membership status</label>
                <select class="form-select" id="membership_status" name="membership_status">
                    <?php foreach (['ACTIVE', 'INACTIVE', 'TRANSFERRED', 'DECEASED'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= ($member['membership_status'] ?? 'ACTIVE') === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="col-md-4"><label class="form-label" for="joined_at">Date joined</label><input class="form-control" id="joined_at" name="joined_at" type="date" value="<?= $memberValue('joined_at') ?>"></div>
        <div class="col-12"><hr><h2 class="h6">Baptism information</h2></div>
        <div class="col-md-4"><label class="form-label" for="baptism_date">Baptism date</label><input class="form-control" id="baptism_date" name="baptism_date" type="date" value="<?= $memberValue('baptism_date') ?>"></div>
        <div class="col-md-8"><label class="form-label" for="baptism_church">Baptism church</label><input class="form-control" id="baptism_church" name="baptism_church" value="<?= $memberValue('baptism_church') ?>" maxlength="200"></div>
        <div class="col-12"><label class="form-label" for="baptism_notes">Baptism notes</label><textarea class="form-control" id="baptism_notes" name="baptism_notes" rows="2"><?= $memberValue('baptism_notes') ?></textarea></div>
        <div class="col-12"><hr><h2 class="h6">Emergency contact</h2></div>
        <div class="col-md-4"><label class="form-label" for="emergency_contact_name">Name</label><input class="form-control" id="emergency_contact_name" name="emergency_contact_name" value="<?= $memberValue('emergency_contact_name') ?>" maxlength="200"></div>
        <div class="col-md-4"><label class="form-label" for="emergency_contact_relationship">Relationship</label><input class="form-control" id="emergency_contact_relationship" name="emergency_contact_relationship" value="<?= $memberValue('emergency_contact_relationship') ?>" maxlength="100"></div>
        <div class="col-md-4"><label class="form-label" for="emergency_contact_phone">Phone</label><input class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" value="<?= $memberValue('emergency_contact_phone') ?>" maxlength="30"></div>
        <div class="col-12"><hr><h2 class="h6">Profile photo</h2></div>
        <div class="col-md-8"><label class="form-label" for="profile_photo">Upload photo</label><input class="form-control" id="profile_photo" name="profile_photo" type="file" accept="image/jpeg,image/png,image/webp"><div class="form-text">JPG, PNG, or WebP. Maximum 10 MB.</div></div>
    </div>
    <div class="d-flex gap-2 mt-4">
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-check me-2"></i><?= e($submitLabel) ?></button>
        <a class="btn btn-outline-secondary" href="<?= e($cancelUrl ?? 'index.php') ?>">Cancel</a>
    </div>
</form>
