<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/Member.php';
require_once __DIR__ . '/fellowship.php';
require_once __DIR__ . '/auth.php';

const MEMBER_STATUSES = ['ACTIVE', 'INACTIVE', 'TRANSFERRED', 'DECEASED'];
const MEMBER_PROFILE_FIELDS = [
    'first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'gender', 'phone', 'email',
    'address_line_1', 'address_line_2', 'city', 'state_province', 'postal_code', 'country',
    'baptism_date', 'baptism_church', 'baptism_notes', 'emergency_contact_name',
    'emergency_contact_relationship', 'emergency_contact_phone',
];
const MEMBER_ADMIN_FIELDS = [
    'user_id', 'member_number', 'membership_status', 'joined_at',
];

function member_input(array $source, bool $admin): array
{
    $fields = array_merge(MEMBER_PROFILE_FIELDS, $admin ? MEMBER_ADMIN_FIELDS : []);
    $data = [];
    foreach ($fields as $field) {
        $value = trim((string) ($source[$field] ?? ''));
        $data[$field] = $value === '' ? null : $value;
    }

    return $data;
}

function validate_member_data(array $data, bool $creating, bool $admin): array
{
    $errors = [];
    if ($creating && (!isset($data['member_number']) || !preg_match('/^[A-Za-z0-9._-]{3,50}$/', (string) $data['member_number']))) {
        $errors[] = 'Member code must be 3-50 characters using letters, numbers, dots, dashes, or underscores.';
    }
    if ($admin && !empty($data['user_id']) && filter_var($data['user_id'], FILTER_VALIDATE_INT) === false) {
        $errors[] = 'Select a valid linked account.';
    }
    foreach (['first_name' => 'First name', 'last_name' => 'Last name'] as $field => $label) {
        if (trim((string) ($data[$field] ?? '')) === '') {
            $errors[] = $label . ' is required.';
        }
    }
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    foreach (['date_of_birth', 'baptism_date', 'joined_at'] as $field) {
        if (!empty($data[$field])) {
            $date = DateTimeImmutable::createFromFormat('Y-m-d', (string) $data[$field]);
            if (!$date || $date->format('Y-m-d') !== $data[$field]) {
                $errors[] = 'Enter a valid date for ' . str_replace('_', ' ', $field) . '.';
            }
        }
    }
    if ($admin && !in_array($data['membership_status'] ?? 'ACTIVE', MEMBER_STATUSES, true)) {
        $errors[] = 'Select a valid membership status.';
    }

    $errors = array_merge($errors, fellowship_validate_member_profile($data, $admin));

    return $errors;
}

function profile_photo_url(?string $path): ?string
{
    $relative = ltrim(str_replace('\\', '/', (string) $path), '/');
    if ($relative === '' || !str_starts_with($relative, 'uploads/profiles/')) {
        return null;
    }
    $full = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($full)) {
        return null;
    }

    return APP_BASE_URL . '/profile-image.php?file=' . rawurlencode(basename($relative));
}

function current_profile_photo_url(?int $userId = null): ?string
{
    $userId = $userId ?? current_user_id();
    if (!$userId) {
        return null;
    }

    $query = database()->prepare('SELECT profile_photo FROM members WHERE user_id = :user_id LIMIT 1');
    $query->execute(['user_id' => $userId]);
    $path = $query->fetchColumn();

    return $path === false ? null : profile_photo_url((string) $path);
}

function delete_profile_photo(?string $path): void
{
    $relative = ltrim(str_replace('\\', '/', (string) $path), '/');
    if ($relative === '' || !str_starts_with($relative, 'uploads/profiles/')) {
        return;
    }

    $full = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (is_file($full)) {
        @unlink($full);
    }
}

function store_profile_photo(string $tmpPath, int $size, bool $uploaded = false): string
{
    if ($size < 1 || $size > APP_UPLOAD_MAX_BYTES || !is_file($tmpPath)) {
        throw new RuntimeException('Profile photo upload failed or exceeds the size limit.');
    }
    if ($uploaded && !is_uploaded_file($tmpPath)) {
        throw new RuntimeException('Profile photo upload failed or exceeds the size limit.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmpPath);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $info = @getimagesize($tmpPath);
    $imageMime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
    if (!isset($allowed[$mime]) || $info === false || !isset($allowed[$imageMime]) || $imageMime !== $mime) {
        throw new RuntimeException('Profile photo must be a valid JPG, PNG, or WebP image.');
    }

    $directory = APP_UPLOAD_DIRECTORIES['profiles'];
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Profile photo storage is unavailable.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $destination = $directory . DIRECTORY_SEPARATOR . $filename;
    $moved = $uploaded
        ? move_uploaded_file($tmpPath, $destination)
        : copy($tmpPath, $destination);
    if (!$moved) {
        throw new RuntimeException('Profile photo could not be saved.');
    }
    @chmod($destination, 0640);

    return 'uploads/profiles/' . $filename;
}

function upload_member_photo(?array $file): ?string
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > APP_UPLOAD_MAX_BYTES) {
        throw new RuntimeException('Profile photo upload failed or exceeds the size limit.');
    }

    $name = (string) ($file['name'] ?? '');
    if (preg_match('/\.(php|phtml|phar|exe|js|shtml|cgi|pl|asp|aspx)(\.|$)/i', $name)) {
        throw new RuntimeException('Profile photo must be a valid JPG, PNG, or WebP image.');
    }

    return store_profile_photo((string) ($file['tmp_name'] ?? ''), (int) ($file['size'] ?? 0), true);
}
