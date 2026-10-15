<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/Member.php';

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

    return $errors;
}

function upload_member_photo(?array $file): ?string
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > APP_UPLOAD_MAX_BYTES) {
        throw new RuntimeException('Profile photo upload failed or exceeds the size limit.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime]) || @getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('Profile photo must be a valid JPG, PNG, or WebP image.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $directory = APP_UPLOAD_DIRECTORIES['profiles'];
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Profile photo storage is unavailable.');
    }
    if (!move_uploaded_file($file['tmp_name'], $directory . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('Profile photo could not be saved.');
    }

    return 'uploads/profiles/' . $filename;
}
