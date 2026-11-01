<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Member.php';
require_once __DIR__ . '/member.php';

function account_profile_payload(array $source): array
{
    $fields = [
        'first_name', 'middle_name', 'last_name', 'suffix', 'email', 'phone',
        'address_line_1', 'address_line_2', 'city', 'state_province', 'postal_code', 'country',
        'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_phone',
    ];
    $data = [];
    foreach ($fields as $field) {
        $data[$field] = trim((string) ($source[$field] ?? ''));
    }
    $data['current_password'] = (string) ($source['current_password'] ?? '');
    $data['password'] = (string) ($source['password'] ?? '');
    $data['password_confirmation'] = (string) ($source['password_confirmation'] ?? '');
    $data['remove_profile_photo'] = !empty($source['remove_profile_photo']);

    return $data;
}

function save_account_profile(int $userId, array $input, ?array $member, ?array $photoFile = null): array
{
    $errors = [];
    if ($userId !== (int) (current_user_id() ?? 0) && PHP_SAPI !== 'cli') {
        return ['You can only update your own profile.'];
    }
    if ($userId < 1) {
        return ['You can only update your own profile.'];
    }

    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    } elseif (User::emailTaken($input['email'], $userId)) {
        $errors[] = 'That email is already used by another account.';
    }

    if (trim($input['first_name']) === '') {
        $errors[] = 'First name is required.';
    }
    if (trim($input['last_name']) === '') {
        $errors[] = 'Last name is required.';
    }

    $changingPassword = $input['password'] !== '' || $input['password_confirmation'] !== '' || $input['current_password'] !== '';
    if ($changingPassword) {
        if (!User::verifyPassword($userId, $input['current_password'])) {
            $errors[] = 'Current password is incorrect.';
        }
        if (strlen($input['password']) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        }
        if (!hash_equals($input['password'], $input['password_confirmation'])) {
            $errors[] = 'New password confirmation does not match.';
        }
    }

    foreach (['emergency_contact_phone' => 'Emergency contact phone', 'phone' => 'Contact number'] as $field => $label) {
        if ($input[$field] !== '' && !preg_match('/^[0-9+\-().\s]{7,30}$/', $input[$field])) {
            $errors[] = $label . ' must be a valid phone number.';
        }
    }

    $newPhoto = null;
    if ($photoFile && ($photoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try {
            $newPhoto = upload_member_photo($photoFile);
        } catch (Throwable $exception) {
            $errors[] = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'The profile photo could not be saved.';
        }
    }

    if ($errors !== []) {
        return $errors;
    }

    User::updateEmail($userId, $input['email']);
    if ($changingPassword) {
        User::updatePassword($userId, $input['password']);
    }

    if (!$member) {
        $memberId = Member::createFromRegistration($userId, [
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'email' => $input['email'],
        ]);
        $member = Member::find($memberId);
    }

    if ($member) {
        $updates = [
            'first_name' => $input['first_name'],
            'middle_name' => $input['middle_name'] !== '' ? $input['middle_name'] : null,
            'last_name' => $input['last_name'],
            'suffix' => $input['suffix'] !== '' ? $input['suffix'] : null,
            'email' => $input['email'],
            'phone' => $input['phone'] !== '' ? $input['phone'] : null,
            'address_line_1' => $input['address_line_1'] !== '' ? $input['address_line_1'] : null,
            'address_line_2' => $input['address_line_2'] !== '' ? $input['address_line_2'] : null,
            'city' => $input['city'] !== '' ? $input['city'] : null,
            'state_province' => $input['state_province'] !== '' ? $input['state_province'] : null,
            'postal_code' => $input['postal_code'] !== '' ? $input['postal_code'] : null,
            'country' => $input['country'] !== '' ? $input['country'] : null,
            'emergency_contact_name' => $input['emergency_contact_name'] !== '' ? $input['emergency_contact_name'] : null,
            'emergency_contact_relationship' => $input['emergency_contact_relationship'] !== '' ? $input['emergency_contact_relationship'] : null,
            'emergency_contact_phone' => $input['emergency_contact_phone'] !== '' ? $input['emergency_contact_phone'] : null,
        ];
        if ($newPhoto !== null) {
            delete_profile_photo(isset($member['profile_photo']) ? (string) $member['profile_photo'] : null);
            $updates['profile_photo'] = $newPhoto;
        } elseif ($input['remove_profile_photo']) {
            delete_profile_photo(isset($member['profile_photo']) ? (string) $member['profile_photo'] : null);
            $updates['profile_photo'] = null;
        }
        Member::update((int) $member['id'], $updates);
    }

    return [];
}

function account_profile_url(): string
{
    return APP_BASE_URL . '/account/profile.php';
}
