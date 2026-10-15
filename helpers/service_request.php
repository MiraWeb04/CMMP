<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/ServiceRequest.php';

const SERVICE_REQUEST_STATUSES = [
    'PENDING',
    'PENDING_ASSIGNMENT',
    'UNDER_REVIEW',
    'DOCUMENTS_REQUIRED',
    'ASSIGNED',
    'APPROVED',
    'SCHEDULED',
    'CONFIRMED',
    'RESCHEDULE_REQUIRED',
    'COMPLETED',
    'REJECTED',
    'CANCELLED',
];
const SERVICE_REVIEW_ROLES = ['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF', 'PASTOR'];
const SERVICE_ASSIGN_ROLES = ['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF'];

function service_status_label(string $status): string
{
    return match ($status) {
        'PENDING' => 'Submitted',
        'PENDING_ASSIGNMENT' => 'Pending Assignment',
        'UNDER_REVIEW' => 'Pending Review',
        'DOCUMENTS_REQUIRED' => 'Documents Required',
        'ASSIGNED' => 'Assigned',
        'APPROVED', 'SCHEDULED', 'CONFIRMED' => 'Confirmed',
        'RESCHEDULE_REQUIRED' => 'Reschedule Required',
        'COMPLETED' => 'Completed',
        'REJECTED' => 'Declined',
        'CANCELLED' => 'Cancelled',
        default => $status,
    };
}

function service_assignment_roles(string $code): array
{
    return match ($code) {
        'PRE_MARRIAGE_COUNSELING' => ['COUNSELOR'],
        'PASTORAL_COUNSELING' => ['PASTOR', 'COUNSELOR'],
        'FACILITY_RESERVATION' => [],
        default => ['PASTOR'],
    };
}

function service_assigned_personnel(array $request): ?array
{
    foreach ($request['assignments'] ?? [] as $assignment) {
        if (in_array(strtoupper((string) ($assignment['assignment_role'] ?? '')), ['PASTOR', 'COUNSELOR'], true)) {
            return $assignment;
        }
    }
    return $request['assignments'][0] ?? null;
}

function service_personnel_label(string $code): string
{
    $roles = service_assignment_roles($code);
    if ($roles === ['COUNSELOR']) {
        return 'Counselor';
    }
    if ($roles === ['PASTOR', 'COUNSELOR']) {
        return 'Pastor/Counselor';
    }
    if ($roles === []) {
        return 'Personnel';
    }
    return 'Pastor';
}

function service_request_input(array $source): array
{
    $fields = [
        'applicant_name', 'contact_number', 'contact_email', 'contact_address',
        'groom_name', 'bride_name', 'counseling_status', 'expected_guests',
        'special_requests', 'additional_information',
        'candidate_name', 'date_of_birth', 'place_of_birth', 'gender', 'age',
        'father_name', 'mother_name', 'guardian_name', 'sponsor_count', 'sponsor_names',
        'parent_guardian_name', 'parent_guardian_contact',
        'deceased_name', 'deceased_date_of_birth', 'date_of_death',
        'family_contact_name', 'family_contact_relationship', 'funeral_location',
        'funeral_location_type',
        'house_family_name', 'house_address', 'house_barangay', 'house_city', 'house_landmark', 'residence_type',
        'event_purpose', 'event_type', 'expected_attendees', 'setup_requirements',
        'partner_1_name', 'partner_2_name', 'first_counseling_session', 'expected_wedding_date', 'previous_sessions',
        'counseling_topics', 'counseling_type', 'counseling_reason',
    ];
    $data = [];
    foreach ($fields as $field) {
        $value = trim((string) ($source[$field] ?? ''));
        if ($value !== '') {
            $data[$field] = $value;
        }
    }
    return $data;
}

function service_request_participants(string $code, array $data, int $memberId): array
{
    $participants = [];
    if ($code === 'BAPTISM') {
        if (!empty($data['candidate_name']) || !empty($data['applicant_name'])) {
            $participants[] = ['member_id' => $memberId, 'role' => 'APPLICANT', 'name' => $data['candidate_name'] ?? $data['applicant_name']];
        }
        if (!empty($data['father_name'])) {
            $participants[] = ['role' => 'FATHER', 'name' => $data['father_name'], 'phone' => $data['contact_number'] ?? null];
        }
        if (!empty($data['mother_name'])) {
            $participants[] = ['role' => 'MOTHER', 'name' => $data['mother_name']];
        }
        if (!empty($data['guardian_name']) || !empty($data['parent_guardian_name'])) {
            $participants[] = ['role' => 'PARENT_GUARDIAN', 'name' => $data['guardian_name'] ?? $data['parent_guardian_name'], 'phone' => $data['contact_number'] ?? $data['parent_guardian_contact'] ?? null];
        }
    } elseif ($code === 'FACILITY_RESERVATION') {
        $participants[] = ['member_id' => $memberId, 'role' => 'APPLICANT', 'name' => $data['applicant_name'] ?? $data['event_purpose'] ?? 'Requesting member'];
    } elseif ($code === 'WEDDING') {
        foreach ([['groom_name', 'GROOM'], ['bride_name', 'BRIDE']] as [$field, $role]) {
            if (!empty($data[$field])) {
                $participants[] = ['role' => $role, 'name' => $data[$field]];
            }
        }
    } elseif ($code === 'PRE_MARRIAGE_COUNSELING') {
        foreach ([['partner_1_name', 'PARTNER'], ['partner_2_name', 'PARTNER'], ['groom_name', 'GROOM'], ['bride_name', 'BRIDE']] as [$field, $role]) {
            if (!empty($data[$field])) {
                $participants[] = ['role' => $role, 'name' => $data[$field]];
            }
        }
    } elseif ($code === 'FUNERAL_SERVICE') {
        if (!empty($data['deceased_name'])) {
            $participants[] = ['role' => 'DECEASED', 'name' => $data['deceased_name']];
        }
        if (!empty($data['family_contact_name'])) {
            $participants[] = ['member_id' => $memberId, 'role' => 'FAMILY_CONTACT', 'name' => $data['family_contact_name'], 'phone' => $data['contact_number'] ?? null];
        }
    } elseif ($code === 'HOUSE_BLESSING') {
        if (!empty($data['house_family_name']) || !empty($data['applicant_name'])) {
            $participants[] = ['member_id' => $memberId, 'role' => 'FAMILY_CONTACT', 'name' => $data['house_family_name'] ?? $data['applicant_name']];
        }
    } else {
        $participants[] = ['member_id' => $memberId, 'role' => 'APPLICANT', 'name' => $data['applicant_name'] ?? 'Requesting member'];
    }
    return $participants;
}

function validate_service_request(string $code, array $data, string $preferredStart, string $preferredEnd, ?int $facilityId = null): array
{
    $errors = [];
    if ($preferredStart === '' || $preferredEnd === '') {
        $errors[] = 'Preferred date and time are required.';
    } elseif (strtotime($preferredEnd) <= strtotime($preferredStart)) {
        $errors[] = 'Preferred end time must be after the preferred start time.';
    } elseif (strtotime($preferredStart) < strtotime('today')) {
        $errors[] = 'The preferred schedule must be today or a future date.';
    }

    if ($code === 'FACILITY_RESERVATION' && ($facilityId === null || $facilityId < 1)) {
        $errors[] = 'Select the church facility you want to reserve.';
    }

    if ($code === 'HOUSE_BLESSING') {
        $startTimestamp = strtotime($preferredStart);
        $endTimestamp = strtotime($preferredEnd);
        if ($startTimestamp === false || $endTimestamp === false) {
            $errors[] = 'A valid preferred date and time are required for a house blessing.';
        } else {
            $startMinutes = ((int) date('H', $startTimestamp) * 60) + (int) date('i', $startTimestamp);
            $endMinutes = ((int) date('H', $endTimestamp) * 60) + (int) date('i', $endTimestamp);
            if ($startMinutes < 8 * 60 || $endMinutes > 16 * 60) {
                $errors[] = 'House blessing services must be booked between 8:00 AM and 4:00 PM.';
            }
        }
    }

    $required = match ($code) {
        'WEDDING' => ['groom_name' => 'Groom full name', 'bride_name' => 'Bride full name', 'contact_number' => 'Contact number'],
        'FUNERAL_SERVICE' => ['deceased_name' => 'Deceased person', 'date_of_death' => 'Date of death', 'family_contact_name' => 'Contact person'],
        'BAPTISM' => ['candidate_name' => 'Full name of the child or candidate', 'contact_number' => 'Contact number'],
        'FACILITY_RESERVATION' => ['event_purpose' => 'Event name'],
        'HOUSE_BLESSING' => ['applicant_name' => 'Full name', 'house_address' => 'Address', 'house_landmark' => 'Landmark', 'contact_number' => 'Contact number'],
        'PRE_MARRIAGE_COUNSELING' => ['partner_1_name' => 'Partner 1 full name', 'partner_2_name' => 'Partner 2 full name', 'contact_number' => 'Contact number'],
        'PASTORAL_COUNSELING' => ['applicant_name' => 'Full name', 'counseling_type' => 'Counseling type'],
        default => [],
    };
    foreach ($required as $field => $label) {
        if (empty($data[$field])) {
            $errors[] = $label . ' is required.';
        }
    }
    return $errors;
}

function upload_service_documents(?array $files, int $userId): array
{
    if (!$files || !isset($files['name']) || !is_array($files['name'])) {
        return [];
    }
    $documents = [];
    $directory = APP_UPLOAD_DIRECTORIES['service_documents'];
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Document storage is unavailable.');
    }
    foreach ($files['name'] as $index => $originalName) {
        if (($files['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if (($files['error'][$index] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($files['size'][$index] ?? 0) > APP_UPLOAD_MAX_BYTES) {
            throw new RuntimeException('A service document is invalid or exceeds the size limit.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($files['tmp_name'][$index]);
        $allowed = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
        if (!isset($allowed[$mime])) {
            throw new RuntimeException('Service documents must be PDF, JPG, or PNG files.');
        }
        $stored = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($files['tmp_name'][$index], $directory . DIRECTORY_SEPARATOR . $stored)) {
            throw new RuntimeException('A service document could not be saved.');
        }
        $documents[] = [
            'user_id' => $userId,
            'document_type' => 'SUPPORTING_DOCUMENT',
            'original_filename' => basename((string) $originalName),
            'stored_filename' => $stored,
            'storage_path' => 'uploads/service-documents/' . $stored,
            'mime_type' => $mime,
            'file_size_bytes' => (int) $files['size'][$index],
        ];
    }
    return $documents;
}
