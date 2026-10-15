<?php
declare(strict_types=1);

$event = $event ?? [];
$errors = $errors ?? [];
$formAction = $formAction ?? '';
$submitLabel = $submitLabel ?? 'Save event';
$pastors = $pastors ?? event_pastors();
$value = static fn (string $field): string => e((string) ($event[$field] ?? ''));
?>
<?php if ($errors !== []): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="<?= e($formAction) ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-md-8"><label class="form-label" for="title">Event title</label><input class="form-control" id="title" name="title" value="<?= $value('title') ?>" required maxlength="200"></div>
        <div class="col-md-4"><label class="form-label" for="category_id">Category</label><select class="form-select" id="category_id" name="category_id" required><option value="">Select category</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (string) ($event['category_id'] ?? '') === (string) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-12"><label class="form-label" for="description">Description</label><textarea class="form-control" id="description" name="description" rows="4"><?= $value('description') ?></textarea></div>
        <div class="col-md-6"><label class="form-label" for="start_datetime">Start date/time</label><input class="form-control" id="start_datetime" name="start_datetime" type="datetime-local" value="<?= $value('start_datetime') ?>" required></div>
        <div class="col-md-6"><label class="form-label" for="end_datetime">End date/time</label><input class="form-control" id="end_datetime" name="end_datetime" type="datetime-local" value="<?= $value('end_datetime') ?>" required></div>
        <div class="col-md-6"><label class="form-label" for="pastor_staff_id">Assigned pastor</label><select class="form-select" id="pastor_staff_id" name="pastor_staff_id" required><option value="">Select pastor</option><?php foreach ($pastors as $pastor): ?><option value="<?= (int) $pastor['id'] ?>" <?= (string) ($event['pastor_staff_id'] ?? '') === (string) $pastor['id'] ? 'selected' : '' ?>><?= e($pastor['display_name']) ?></option><?php endforeach; ?></select><div class="form-text">Every church event must include a pastor.</div></div>
        <div class="col-md-6"><label class="form-label" for="facility_id">Facility</label><select class="form-select" id="facility_id" name="facility_id"><option value="">No facility assigned</option><?php foreach ($facilities as $facility): ?><option value="<?= (int) $facility['id'] ?>" <?= (string) ($event['facility_id'] ?? '') === (string) $facility['id'] ? 'selected' : '' ?>><?= e($facility['name'] . ' (' . $facility['code'] . ')') ?></option><?php endforeach; ?></select><div class="form-text">Uses the centralized facility relationship for future scheduling.</div></div>
        <div class="col-md-3"><label class="form-label" for="capacity">Capacity</label><input class="form-control" id="capacity" name="capacity" type="number" min="1" max="100000" value="<?= $value('capacity') ?>"></div>
        <div class="col-md-3"><label class="form-label" for="event_status">Status</label><select class="form-select" id="event_status" name="event_status"><?php foreach (EVENT_STATUSES as $status): ?><option value="<?= e($status) ?>" <?= ($event['event_status'] ?? 'DRAFT') === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><div class="form-check mt-4"><input class="form-check-input" id="registration_required" name="registration_required" type="checkbox" value="1" <?= !empty($event['registration_required']) ? 'checked' : '' ?>><label class="form-check-label" for="registration_required">Registration required</label></div></div>
        <div class="col-md-6"><label class="form-label" for="registration_deadline">Registration deadline</label><input class="form-control" id="registration_deadline" name="registration_deadline" type="datetime-local" value="<?= $value('registration_deadline') ?>"></div>
        <div class="col-md-8"><label class="form-label" for="event_image">Event image</label><input class="form-control" id="event_image" name="event_image" type="file" accept="image/jpeg,image/png,image/webp"><div class="form-text">JPG, PNG, or WebP. Maximum 10 MB.</div></div>
    </div>
    <div class="d-flex gap-2 mt-4"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-check me-2"></i><?= e($submitLabel) ?></button><a class="btn btn-outline-secondary" href="<?= e($cancelUrl ?? 'index.php') ?>">Cancel</a></div>
</form>
