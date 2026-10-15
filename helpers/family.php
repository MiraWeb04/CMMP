<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/Family.php';

function family_input(array $source): array
{
    $fields = ['family_code', 'family_name', 'address_line_1', 'address_line_2', 'city', 'state_province', 'postal_code', 'country'];
    $data = [];
    foreach ($fields as $field) {
        $value = trim((string) ($source[$field] ?? ''));
        $data[$field] = $value === '' ? null : $value;
    }

    return $data;
}

function validate_family_data(array $data, bool $creating): array
{
    $errors = [];
    if ($creating && (!isset($data['family_code']) || !preg_match('/^[A-Za-z0-9._-]{3,50}$/', (string) $data['family_code']))) {
        $errors[] = 'Family code must be 3-50 characters using letters, numbers, dots, dashes, or underscores.';
    }
    if (trim((string) ($data['family_name'] ?? '')) === '') {
        $errors[] = 'Family name is required.';
    }

    return $errors;
}
