<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/Facility.php';

const FACILITY_STATUSES = ['AVAILABLE', 'UNAVAILABLE', 'MAINTENANCE'];
const FACILITY_TYPES = ['SANCTUARY', 'FELLOWSHIP_HALL', 'CHAPEL', 'CONFERENCE_ROOM', 'OUTDOOR_AREA', 'OTHER'];

function facility_input(array $source, bool $creating): array
{
    $data = [];
    foreach (['name', 'code', 'description', 'location', 'facility_type', 'facility_status'] as $field) {
        $value = trim((string) ($source[$field] ?? ''));
        if ($field === 'code' && !$creating) {
            continue;
        }
        $data[$field] = $value === ''
            ? ($field === 'facility_status' && $creating ? 'AVAILABLE' : null)
            : $value;
    }
    $capacity = trim((string) ($source['capacity'] ?? ''));
    $data['capacity'] = $capacity === '' ? null : (int) $capacity;

    return $data;
}

function validate_facility_data(array $data, bool $creating): array
{
    $errors = [];
    if (trim((string) ($data['name'] ?? '')) === '') {
        $errors[] = 'Facility name is required.';
    }
    if ($creating && (!isset($data['code']) || !preg_match('/^[A-Za-z0-9._-]{3,50}$/', (string) $data['code']))) {
        $errors[] = 'Facility code must be 3-50 characters using letters, numbers, dots, dashes, or underscores.';
    }
    if ($data['capacity'] !== null && ($data['capacity'] < 0 || $data['capacity'] > 100000)) {
        $errors[] = 'Capacity must be between 0 and 100,000.';
    }
    if (!in_array($data['facility_type'] ?? '', FACILITY_TYPES, true)) {
        $errors[] = 'Select a valid facility type.';
    }
    if (($data['facility_status'] ?? null) !== null && !in_array($data['facility_status'], FACILITY_STATUSES, true)) {
        $errors[] = 'Select a valid facility status.';
    }

    return $errors;
}
