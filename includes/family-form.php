<?php
declare(strict_types=1);

$family = $family ?? [];
$formAction = $formAction ?? '';
$submitLabel = $submitLabel ?? 'Save family';
$errors = $errors ?? [];
?>
<?php if ($errors !== []): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<form method="post" action="<?= e($formAction) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label" for="family_code">Family code</label><input class="form-control" id="family_code" name="family_code" value="<?= e($family['family_code'] ?? '') ?>" <?= isset($family['id']) ? 'readonly' : 'required' ?> maxlength="50"></div>
        <div class="col-md-8"><label class="form-label" for="family_name">Family name</label><input class="form-control" id="family_name" name="family_name" value="<?= e($family['family_name'] ?? '') ?>" required maxlength="150"></div>
        <div class="col-12"><hr><h2 class="h6">Family address</h2></div>
        <div class="col-md-6"><label class="form-label" for="address_line_1">Address line 1</label><input class="form-control" id="address_line_1" name="address_line_1" value="<?= e($family['address_line_1'] ?? '') ?>" maxlength="255"></div>
        <div class="col-md-6"><label class="form-label" for="address_line_2">Address line 2</label><input class="form-control" id="address_line_2" name="address_line_2" value="<?= e($family['address_line_2'] ?? '') ?>" maxlength="255"></div>
        <div class="col-md-4"><label class="form-label" for="city">City</label><input class="form-control" id="city" name="city" value="<?= e($family['city'] ?? '') ?>" maxlength="100"></div>
        <div class="col-md-4"><label class="form-label" for="state_province">State / province</label><input class="form-control" id="state_province" name="state_province" value="<?= e($family['state_province'] ?? '') ?>" maxlength="100"></div>
        <div class="col-md-4"><label class="form-label" for="postal_code">Postal code</label><input class="form-control" id="postal_code" name="postal_code" value="<?= e($family['postal_code'] ?? '') ?>" maxlength="20"></div>
        <div class="col-md-4"><label class="form-label" for="country">Country</label><input class="form-control" id="country" name="country" value="<?= e($family['country'] ?? '') ?>" maxlength="100"></div>
    </div>
    <div class="d-flex gap-2 mt-4"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-check me-2"></i><?= e($submitLabel) ?></button><a class="btn btn-outline-secondary" href="<?= e($cancelUrl ?? 'index.php') ?>">Cancel</a></div>
</form>
