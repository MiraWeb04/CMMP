<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/Event.php';

const EVENT_STATUSES = ['DRAFT', 'PUBLISHED', 'CANCELLED', 'COMPLETED'];

function event_input(array $source, bool $creating): array
{
    $data = [];
    foreach (['title', 'description', 'location', 'start_datetime', 'end_datetime', 'registration_deadline'] as $field) {
        $value = trim((string) ($source[$field] ?? ''));
        $data[$field] = $value === '' ? null : $value;
    }
    $data['category_id'] = filter_var($source['category_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
    $data['facility_id'] = filter_var($source['facility_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
    $capacity = trim((string) ($source['capacity'] ?? ''));
    $data['capacity'] = $capacity === '' ? null : (int) $capacity;
    $data['registration_required'] = isset($source['registration_required']) ? 1 : 0;
    $data['event_status'] = (string) ($source['event_status'] ?? 'DRAFT');

    return $data;
}

function validate_event_data(array $data): array
{
    $errors = [];
    if (trim((string) ($data['title'] ?? '')) === '') {
        $errors[] = 'Event title is required.';
    }
    if (empty($data['category_id'])) {
        $errors[] = 'Select an event category.';
    }
    if (empty($data['start_datetime']) || empty($data['end_datetime'])) {
        $errors[] = 'Start and end date/time are required.';
    } elseif (strtotime((string) $data['end_datetime']) <= strtotime((string) $data['start_datetime'])) {
        $errors[] = 'Invalid schedule.';
    }
    if ($data['capacity'] !== null && ($data['capacity'] < 1 || $data['capacity'] > 100000)) {
        $errors[] = 'Capacity must be between 1 and 100,000.';
    }
    if (!in_array($data['event_status'] ?? 'DRAFT', EVENT_STATUSES, true)) {
        $errors[] = 'Select a valid event status.';
    }
    if (!empty($data['registration_deadline']) && !empty($data['start_datetime']) && strtotime((string) $data['registration_deadline']) > strtotime((string) $data['start_datetime'])) {
        $errors[] = 'Registration deadline must be on or before the event start.';
    }
    if ($data['registration_required'] && empty($data['registration_deadline'])) {
        $errors[] = 'Registration deadline is required when registration is enabled.';
    }

    return $errors;
}

function event_image_url(?string $path): ?string
{
    $relative = ltrim(str_replace('\\', '/', (string) $path), '/');
    if ($relative === '' || !str_starts_with($relative, 'uploads/event-images/')) {
        return null;
    }
    if (!is_file(dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative))) {
        return null;
    }

    return APP_BASE_URL . '/event-image.php?file=' . rawurlencode(basename($relative));
}

function upload_event_image(?array $file): ?string
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > APP_UPLOAD_MAX_BYTES) {
        throw new RuntimeException('Event image upload failed or exceeds the size limit.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime]) || @getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('Event image must be a valid JPG, PNG, or WebP image.');
    }
    $directory = APP_UPLOAD_DIRECTORIES['event_images'];
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Event image storage is unavailable.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $directory . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('Event image could not be saved.');
    }

    return 'uploads/event-images/' . $filename;
}

function member_id_for_user(int $userId): ?int
{
    $query = database()->prepare('SELECT id FROM members WHERE user_id = :user_id LIMIT 1');
    $query->execute(['user_id' => $userId]);
    $memberId = $query->fetchColumn();

    return $memberId === false ? null : (int) $memberId;
}
