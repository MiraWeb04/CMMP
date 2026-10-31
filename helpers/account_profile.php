<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Member.php';
require_once __DIR__ . '/member.php';

function account_profile_payload(array $source): array
{
    return [
        'email' => trim((string) ($source['email'] ?? '')),
        'current_password' => (string) ($source['current_password'] ?? ''),
        'password' => (string) ($source['password'] ?? ''),
        'password_confirmation' => (string) ($source['password_confirmation'] ?? ''),
        'phone' => trim((string) ($source['phone'] ?? '')),
        'address_line_1' => trim((string) ($source['address_line_1'] ?? '')),
        'address_line_2' => trim((string) ($source['address_line_2'] ?? '')),
        'city' => trim((string) ($source['city'] ?? '')),
        'emergency_contact_name' => trim((string) ($source['emergency_contact_name'] ?? '')),
        'emergency_contact_relationship' => trim((string) ($source['emergency_contact_relationship'] ?? '')),
        'emergency_contact_phone' => trim((string) ($source['emergency_contact_phone'] ?? '')),
    ];
}

function save_account_profile(int $userId, array $input, ?array $member): array
{
    $errors = [];
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    } elseif (User::emailTaken($input['email'], $userId)) {
        $errors[] = 'That email is already used by another account.';
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

    if ($member) {
        foreach (['emergency_contact_phone' => 'Emergency contact phone', 'phone' => 'Phone'] as $field => $label) {
            if ($input[$field] !== '' && !preg_match('/^[0-9+\-().\s]{7,30}$/', $input[$field])) {
                $errors[] = $label . ' must be a valid phone number.';
            }
        }
    }

    if ($errors !== []) {
        return $errors;
    }

    User::updateEmail($userId, $input['email']);
    if ($changingPassword) {
        User::updatePassword($userId, $input['password']);
    }

    if ($member) {
        $updates = [
            'email' => $input['email'],
            'phone' => $input['phone'] !== '' ? $input['phone'] : null,
            'address_line_1' => $input['address_line_1'] !== '' ? $input['address_line_1'] : null,
            'address_line_2' => $input['address_line_2'] !== '' ? $input['address_line_2'] : null,
            'city' => $input['city'] !== '' ? $input['city'] : null,
            'emergency_contact_name' => $input['emergency_contact_name'] !== '' ? $input['emergency_contact_name'] : null,
            'emergency_contact_relationship' => $input['emergency_contact_relationship'] !== '' ? $input['emergency_contact_relationship'] : null,
            'emergency_contact_phone' => $input['emergency_contact_phone'] !== '' ? $input['emergency_contact_phone'] : null,
        ];
        Member::update((int) $member['id'], $updates);
    }

    return [];
}

function account_profile_url(): string
{
    $roles = current_user_roles();
    if (in_array('MEMBER', $roles, true) && !array_intersect($roles, ['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF', 'PASTOR', 'COUNSELOR', 'MINISTRY_LEADER'])) {
        return APP_BASE_URL . '/member/profile/';
    }

    return APP_BASE_URL . '/account/profile.php';
}
