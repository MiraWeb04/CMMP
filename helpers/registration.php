<?php
declare(strict_types=1);

require_once __DIR__ . '/fellowship.php';

function validate_member_registration_profile(array $data): array
{
    $errors = [];
    $firstName = trim((string) ($data['first_name'] ?? ''));
    $lastName = trim((string) ($data['last_name'] ?? ''));
    $dateOfBirth = trim((string) ($data['date_of_birth'] ?? ''));
    $gender = trim((string) ($data['gender'] ?? ''));

    if ($firstName === '') {
        $errors[] = 'First name is required.';
    }
    if ($lastName === '') {
        $errors[] = 'Last name is required.';
    }
    if ($dateOfBirth === '') {
        $errors[] = 'Date of birth is required for fellowship organization assignment.';
    } else {
        $birthDate = DateTimeImmutable::createFromFormat('Y-m-d', $dateOfBirth);
        if (!$birthDate || $birthDate->format('Y-m-d') !== $dateOfBirth) {
            $errors[] = 'Enter a valid date of birth.';
        } elseif ($birthDate > new DateTimeImmutable('today')) {
            $errors[] = 'Date of birth cannot be in the future.';
        }
    }

    if ($gender === '') {
        $errors[] = 'Gender is required for fellowship organization assignment.';
    } elseif (!in_array($gender, ['Male', 'Female'], true)) {
        $errors[] = 'Select Male or Female so the system can assign CYF, CYAF, UCM, or CWA.';
    }

    if ($errors === [] && $dateOfBirth !== '' && $gender !== '') {
        $age = fellowship_member_age($dateOfBirth);
        if ($age !== null && $age >= 50 && fellowship_code_for_profile($dateOfBirth, $gender) === null) {
            $errors[] = 'Select Male or Female for members aged 50 and above.';
        }
    }

    return $errors;
}
