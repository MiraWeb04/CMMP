<?php
declare(strict_types=1);

$facility = $facility ?? [];
$errors = $errors ?? [];
$formAction = $formAction ?? '';
$submitLabel = $submitLabel ?? 'Save facility';
$value = static fn (string $field): string => e((string) ($facility[$field] ?? ''));
?>
<?php if ($errors !== []): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="<?= e($formAction) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-8"><label class="form-label" for="name">Facility name</label><input class="form-control" id="name" name="name" value="<?= $value('name') ?>" required maxlength="150" placeholder="Main Sanctuary"></div>
        <div class="col-md-4"><label class="form-label" for="code">Facility code</label><input class="form-control" id="code" name="code" value="<?= $value('code') ?>" <?= isset($facility['id']) ? 'readonly' : 'required' ?> maxlength="50" placeholder="MAIN-SANCTUARY"></div>
        <div class="col-md-6"><label class="form-label" for="facility_type">Facility type</label><select class="form-select" id="facility_type" name="facility_type" required><option value="">Select type</option><?php foreach (FACILITY_TYPES as $type): ?><option value="<?= e($type) ?>" <?= ($facility['facility_type'] ?? '') === $type ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $type)) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label" for="capacity">Capacity</label><input class="form-control" id="capacity" name="capacity" type="number" min="0" max="100000" value="<?= $value('capacity') ?>"></div>
        <div class="col-12"><label class="form-label" for="location">Location</label><input class="form-control" id="location" name="location" value="<?= $value('location') ?>" maxlength="255" placeholder="Ground floor, east wing"></div>
        <div class="col-12"><label class="form-label" for="description">Description</label><textarea class="form-control" id="description" name="description" rows="4"><?= $value('description') ?></textarea></div>
        <?php if (isset($facility['id'])): ?><div class="col-md-6"><label class="form-label" for="facility_status">Status</label><select class="form-select" id="facility_status" name="facility_status"><?php foreach (FACILITY_STATUSES as $status): ?><option value="<?= e($status) ?>" <?= ($facility['facility_status'] ?? '') === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    </div>
    <div class="d-flex gap-2 mt-4"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-check me-2"></i><?= e($submitLabel) ?></button><a class="btn btn-outline-secondary" href="<?= e($cancelUrl ?? 'index.php') ?>">Cancel</a></div>
</form>
