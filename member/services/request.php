<?php
declare(strict_types=1);
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/service_request.php';
require_once __DIR__ . '/../../models/Member.php';
require_once __DIR__ . '/../../models/Facility.php';

require_role('MEMBER');

$memberId = ServiceRequest::memberIdForUser((int) current_user_id());
if (!$memberId) {
    http_response_code(404);
    exit('Your member profile has not been created yet.');
}

$member = Member::find($memberId) ?? [];
$types = ServiceRequest::types();
$facilities = ServiceRequest::facilities();
$errors = [];
$selectedType = (int) ($_POST['service_type_id'] ?? $_GET['service_type_id'] ?? 0);
$selectedCode = strtoupper(trim((string) ($_POST['service_code'] ?? $_GET['service_code'] ?? '')));
$data = service_request_input($_POST);

$memberName = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $data['applicant_name'] = $data['applicant_name'] ?? $memberName;
    $data['contact_number'] = $data['contact_number'] ?? (string) ($member['phone'] ?? '');
    $data['contact_email'] = $data['contact_email'] ?? (string) ($member['email'] ?? '');
    $data['contact_address'] = $data['contact_address'] ?? trim((string) (($member['address_line_1'] ?? '') . ' ' . ($member['address_line_2'] ?? '')));
}

$preferredDate = trim((string) ($_POST['preferred_date'] ?? $_GET['preferred_date'] ?? ''));
$preferredTime = trim((string) ($_POST['preferred_time'] ?? $_GET['preferred_time'] ?? ''));
$preferredEndTime = trim((string) ($_POST['preferred_end_time'] ?? $_GET['preferred_end_time'] ?? ''));
$preferredStart = trim((string) ($_POST['preferred_start_datetime'] ?? $_GET['preferred_start_datetime'] ?? ''));
$preferredEnd = trim((string) ($_POST['preferred_end_datetime'] ?? $_GET['preferred_end_datetime'] ?? ''));
$facilityId = filter_var($_POST['preferred_facility_id'] ?? $_GET['preferred_facility_id'] ?? null, FILTER_VALIDATE_INT) ?: null;

if ($preferredDate === '' && preg_match('/^(\d{4}-\d{2}-\d{2})/', $preferredStart, $dateMatch) === 1) {
    $preferredDate = $dateMatch[1];
} elseif ($preferredDate === '' && $preferredStart !== '' && strtotime($preferredStart) !== false) {
    $preferredDate = date('Y-m-d', strtotime($preferredStart));
}
if ($preferredTime === '' && $preferredStart !== '' && strtotime($preferredStart) !== false && str_contains($preferredStart, 'T')) {
    $preferredTime = date('H:i', strtotime($preferredStart));
}
if ($preferredEndTime === '' && $preferredEnd !== '' && strtotime($preferredEnd) !== false) {
    $preferredEndTime = date('H:i', strtotime($preferredEnd));
}

$selected = null;
foreach ($types as $type) {
    if ((int) $type['id'] === $selectedType || ($selectedType === 0 && $selectedCode !== '' && $type['code'] === $selectedCode)) {
        $selected = $type;
        $selectedType = (int) $type['id'];
        break;
    }
}

$code = $selected['code'] ?? '';
$counselingOffice = null;
if (in_array($code, ['PRE_MARRIAGE_COUNSELING', 'PASTORAL_COUNSELING'], true)) {
    $counselingOffice = Facility::counselingOffice();
    $facilityId = (int) $counselingOffice['id'];
    $data['counseling_type'] = $code === 'PRE_MARRIAGE_COUNSELING' ? 'Pre-Marriage Counseling' : 'Pastoral Counseling';
}
$durationHours = $code === 'FUNERAL_SERVICE' ? 2 : 1;
if ($preferredDate !== '' && $preferredTime !== '') {
    $preferredStart = $preferredDate . 'T' . $preferredTime;
    if (in_array($code, ['FACILITY_RESERVATION', 'MANANITA'], true) && $preferredEndTime !== '') {
        $preferredEnd = $preferredDate . 'T' . $preferredEndTime;
    } else {
        $startTimestamp = strtotime($preferredStart);
        $preferredEnd = $startTimestamp !== false
            ? date('Y-m-d\TH:i', $startTimestamp + ($durationHours * 3600))
            : '';
        if ($preferredEndTime === '' && $preferredEnd !== '') {
            $preferredEndTime = date('H:i', strtotime($preferredEnd));
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();
    if (!$selected) {
        $errors[] = 'Select a valid service type.';
    } else {
        $errors = validate_service_request($selected['code'], $data, $preferredStart, $preferredEnd, $facilityId);
    }

    if ($errors === []) {
        try {
            ServiceRequest::create([
                'service_type_id' => (int) $selected['id'],
                'requested_by_member_id' => $memberId,
                'request_number' => '',
                'subject' => $selected['name'],
                'description' => $data['additional_information'] ?? $data['special_requests'] ?? null,
                'request_data' => json_encode($data, JSON_THROW_ON_ERROR),
                'preferred_start_datetime' => date('Y-m-d H:i:s', strtotime($preferredStart)),
                'preferred_end_datetime' => date('Y-m-d H:i:s', strtotime($preferredEnd)),
                'preferred_facility_id' => $facilityId,
                'requested_by_member_id_user' => (int) current_user_id(),
            ], service_request_participants($selected['code'], $data, $memberId), upload_service_documents($_FILES['documents'] ?? null, (int) current_user_id()));
            header('Location: index.php?submitted=1');
            exit;
        } catch (CalendarConflictException $exception) {
            $errors[] = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('CMMP service request failed: ' . $exception->getMessage());
            $errors[] = 'Your request could not be submitted. Please try again.';
        }
    }
}

$pageTitle = $selected ? $selected['name'] . ' booking' : 'New church service request';
$activeNav = 'services';
$breadcrumbItems = [
    ['label' => 'Church services', 'url' => 'index.php'],
    ['label' => 'New request', 'url' => 'request.php'],
];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';

$field = static function (array $data, string $name): string {
    return e((string) ($data[$name] ?? ''));
};
?>

<div class="cmmp-page-header">
    <div>
        <h1><?= $selected ? e(strtoupper($selected['name']) . ' BOOKING') : 'New service request' ?></h1>
        <p>Select a service, provide the required information, and request your preferred date and time. Church staff will assign a pastor or counselor after review.</p>
    </div>
    <?php
    $calendarQuery = [];
    if ($selectedType) {
        $calendarQuery['service_type_id'] = (string) $selectedType;
    }
    if ($preferredDate !== '') {
        $calendarQuery['preferred_date'] = $preferredDate;
    }
    if ($facilityId) {
        $calendarQuery['facility_id'] = (string) $facilityId;
    }
    ?>
    <a class="btn btn-outline-primary" href="calendar.php<?= $calendarQuery !== [] ? '?' . http_build_query($calendarQuery) : '' ?>">
        <i class="fa-solid fa-calendar-days me-2"></i>Choose from calendar
    </a>
</div>

<div class="cmmp-card mb-3">
    <form method="get" class="row g-3 align-items-end">
        <?php if ($preferredDate !== ''): ?><input type="hidden" name="preferred_date" value="<?= e($preferredDate) ?>"><?php endif; ?>
        <?php if ($preferredTime !== ''): ?><input type="hidden" name="preferred_time" value="<?= e($preferredTime) ?>"><?php endif; ?>
        <?php if ($preferredEndTime !== ''): ?><input type="hidden" name="preferred_end_time" value="<?= e($preferredEndTime) ?>"><?php endif; ?>
        <?php if ($facilityId): ?><input type="hidden" name="preferred_facility_id" value="<?= (int) $facilityId ?>"><?php endif; ?>
        <div class="col-md-8">
            <label class="form-label" for="service_type_id">Step 1 · Select a service</label>
            <select class="form-select" id="service_type_id" name="service_type_id" required onchange="this.form.submit()">
                <option value="">Select service</option>
                <?php foreach ($types as $type): ?>
                    <option value="<?= (int) $type['id'] ?>" <?= $selectedType === (int) $type['id'] ? 'selected' : '' ?>><?= e($type['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($preferredDate !== ''): ?>
            <div class="col-md-4">
                <p class="form-label mb-1">Selected date</p>
                <p class="mb-0 fw-semibold"><?= e(date('F j, Y', strtotime($preferredDate))) ?></p>
            </div>
        <?php endif; ?>
    </form>
</div>

<?php if ($selected): ?>
<div class="cmmp-card">
    <p class="text-muted"><?= e($selected['description'] ?? 'Complete the service information and your preferred schedule.') ?></p>

    <?php if ($errors !== []): ?>
        <div class="alert alert-danger">
            <ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="service_type_id" value="<?= (int) $selectedType ?>">

        <?php if ($code === 'WEDDING'): ?>
            <h2 class="h5 mt-2">Couple Information</h2>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label" for="groom_name">Groom Full Name</label><input class="form-control" id="groom_name" name="groom_name" value="<?= $field($data, 'groom_name') ?>" required></div>
                <div class="col-md-6"><label class="form-label" for="bride_name">Bride Full Name</label><input class="form-control" id="bride_name" name="bride_name" value="<?= $field($data, 'bride_name') ?>" required></div>
                <div class="col-md-4"><label class="form-label" for="contact_number">Contact Number</label><input class="form-control" id="contact_number" name="contact_number" value="<?= $field($data, 'contact_number') ?>" required></div>
                <div class="col-md-4"><label class="form-label" for="contact_email">Email</label><input class="form-control" id="contact_email" name="contact_email" type="email" value="<?= $field($data, 'contact_email') ?>"></div>
                <div class="col-md-4"><label class="form-label" for="contact_address">Address</label><input class="form-control" id="contact_address" name="contact_address" value="<?= $field($data, 'contact_address') ?>"></div>
            </div>
            <h2 class="h5">Wedding Details</h2>
            <div class="row g-3 mb-3">
                <div class="col-md-4"><label class="form-label" for="preferred_date">Preferred Wedding Date</label><input class="form-control" id="preferred_date" name="preferred_date" type="date" value="<?= e($preferredDate) ?>" required></div>
                <div class="col-md-4"><label class="form-label" for="preferred_time">Preferred Time</label><input class="form-control" id="preferred_time" name="preferred_time" type="time" value="<?= e($preferredTime) ?>" required></div>
                <div class="col-md-4"><label class="form-label" for="expected_guests">Expected Number of Guests</label><input class="form-control" id="expected_guests" name="expected_guests" value="<?= $field($data, 'expected_guests') ?>"></div>
                <div class="col-md-6"><label class="form-label" for="preferred_facility_id">Preferred Venue/Facility</label>
                    <select class="form-select" id="preferred_facility_id" name="preferred_facility_id">
                        <option value="">No preference</option>
                        <?php foreach ($facilities as $facility): ?><option value="<?= (int) $facility['id'] ?>" <?= $facilityId === (int) $facility['id'] ? 'selected' : '' ?>><?= e($facility['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <h2 class="h5">Pre-Marriage Counseling</h2>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label" for="counseling_status">Counseling Status</label>
                    <select class="form-select" id="counseling_status" name="counseling_status">
                        <?php foreach (['Not yet started', 'Currently attending', 'Completed'] as $statusOption): ?>
                            <option value="<?= e($statusOption) ?>" <?= ($data['counseling_status'] ?? '') === $statusOption ? 'selected' : '' ?>><?= e($statusOption) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        <?php elseif ($code === 'BAPTISM'): ?>
            <h2 class="h5 mt-2">Child/Candidate</h2>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label" for="candidate_name">Full Name</label><input class="form-control" id="candidate_name" name="candidate_name" value="<?= $field($data, 'candidate_name') ?>" required></div>
                <div class="col-md-3"><label class="form-label" for="date_of_birth">Date of Birth</label><input class="form-control" id="date_of_birth" name="date_of_birth" type="date" value="<?= $field($data, 'date_of_birth') ?>"></div>
                <div class="col-md-3"><label class="form-label" for="place_of_birth">Place of Birth</label><input class="form-control" id="place_of_birth" name="place_of_birth" value="<?= $field($data, 'place_of_birth') ?>"></div>
                <div class="col-md-3"><label class="form-label" for="gender">Gender</label>
                    <select class="form-select" id="gender" name="gender">
                        <option value="">Select</option>
                        <?php foreach (['Male', 'Female'] as $gender): ?><option value="<?= e($gender) ?>" <?= ($data['gender'] ?? '') === $gender ? 'selected' : '' ?>><?= e($gender) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3"><label class="form-label" for="age">Age</label><input class="form-control" id="age" name="age" value="<?= $field($data, 'age') ?>"></div>
            </div>
            <h2 class="h5">Parent/Guardian</h2>
            <div class="row g-3 mb-3">
                <div class="col-md-4"><label class="form-label" for="father_name">Father's Name</label><input class="form-control" id="father_name" name="father_name" value="<?= $field($data, 'father_name') ?>"></div>
                <div class="col-md-4"><label class="form-label" for="mother_name">Mother's Name</label><input class="form-control" id="mother_name" name="mother_name" value="<?= $field($data, 'mother_name') ?>"></div>
                <div class="col-md-4"><label class="form-label" for="guardian_name">Guardian's Name</label><input class="form-control" id="guardian_name" name="guardian_name" value="<?= $field($data, 'guardian_name') ?>"></div>
                <div class="col-md-4"><label class="form-label" for="contact_number">Contact Number</label><input class="form-control" id="contact_number" name="contact_number" value="<?= $field($data, 'contact_number') ?>" required></div>
                <div class="col-md-4"><label class="form-label" for="contact_email">Email</label><input class="form-control" id="contact_email" name="contact_email" type="email" value="<?= $field($data, 'contact_email') ?>"></div>
                <div class="col-md-4"><label class="form-label" for="contact_address">Address</label><input class="form-control" id="contact_address" name="contact_address" value="<?= $field($data, 'contact_address') ?>"></div>
            </div>
            <h2 class="h5">Baptism Details</h2>
            <div class="row g-3 mb-3">
                <div class="col-md-4"><label class="form-label" for="preferred_date">Preferred Date</label><input class="form-control" id="preferred_date" name="preferred_date" type="date" value="<?= e($preferredDate) ?>" required></div>
                <div class="col-md-4"><label class="form-label" for="preferred_time">Preferred Time</label><input class="form-control" id="preferred_time" name="preferred_time" type="time" value="<?= e($preferredTime) ?>" required></div>
                <div class="col-md-4"><label class="form-label" for="expected_guests">Expected Number of Guests</label><input class="form-control" id="expected_guests" name="expected_guests" value="<?= $field($data, 'expected_guests') ?>"></div>
                <div class="col-md-6"><label class="form-label" for="preferred_facility_id">Preferred Facility</label>
                    <select class="form-select" id="preferred_facility_id" name="preferred_facility_id">
                        <option value="">No preference</option>
                        <?php foreach ($facilities as $facility): ?><option value="<?= (int) $facility['id'] ?>" <?= $facilityId === (int) $facility['id'] ? 'selected' : '' ?>><?= e($facility['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <h2 class="h5">Sponsors</h2>
            <div class="row g-3 mb-3">
                <div class="col-md-4"><label class="form-label" for="sponsor_count">Number of Sponsors</label><input class="form-control" id="sponsor_count" name="sponsor_count" value="<?= $field($data, 'sponsor_count') ?>"></div>
                <div class="col-md-8"><label class="form-label" for="sponsor_names">Sponsor Names</label><input class="form-control" id="sponsor_names" name="sponsor_names" value="<?= $field($data, 'sponsor_names') ?>"></div>
            </div>
        <?php elseif ($code === 'FUNERAL_SERVICE'): ?>
            <div class="alert alert-warning">For urgent funeral arrangements, please contact the church office directly.</div>
            <h2 class="h5">Deceased Information</h2>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label" for="deceased_name">Full Name</label><input class="form-control" id="deceased_name" name="deceased_name" value="<?= $field($data, 'deceased_name') ?>" required></div>
                <div class="col-md-2"><label class="form-label" for="deceased_date_of_birth">Date of Birth</label><input class="form-control" id="deceased_date_of_birth" name="deceased_date_of_birth" type="date" value="<?= $field($data, 'deceased_date_of_birth') ?>"></div>
                <div class="col-md-2"><label class="form-label" for="date_of_death">Date of Death</label><input class="form-control" id="date_of_death" name="date_of_death" type="date" value="<?= $field($data, 'date_of_death') ?>" required></div>
                <div class="col-md-2"><label class="form-label" for="age">Age</label><input class="form-control" id="age" name="age" value="<?= $field($data, 'age') ?>"></div>
            </div>
            <h2 class="h5">Contact Person</h2>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label" for="family_contact_name">Full Name</label><input class="form-control" id="family_contact_name" name="family_contact_name" value="<?= $field($data, 'family_contact_name') ?>" required></div>
                <div class="col-md-6"><label class="form-label" for="family_contact_relationship">Relationship to Deceased</label><input class="form-control" id="family_contact_relationship" name="family_contact_relationship" value="<?= $field($data, 'family_contact_relationship') ?>"></div>
                <div class="col-md-4"><label class="form-label" for="contact_number">Contact Number</label><input class="form-control" id="contact_number" name="contact_number" value="<?= $field($data, 'contact_number') ?>"></div>
                <div class="col-md-4"><label class="form-label" for="contact_email">Email</label><input class="form-control" id="contact_email" name="contact_email" type="email" value="<?= $field($data, 'contact_email') ?>"></div>
                <div class="col-md-4"><label class="form-label" for="contact_address">Address</label><input class="form-control" id="contact_address" name="contact_address" value="<?= $field($data, 'contact_address') ?>"></div>
            </div>
            <h2 class="h5">Funeral Details</h2>
            <div class="row g-3 mb-3">
                <div class="col-md-4"><label class="form-label" for="preferred_date">Preferred Date</label><input class="form-control" id="preferred_date" name="preferred_date" type="date" value="<?= e($preferredDate) ?>" required></div>
                <div class="col-md-4"><label class="form-label" for="preferred_time">Preferred Time</label><input class="form-control" id="preferred_time" name="preferred_time" type="time" value="<?= e($preferredTime) ?>" required></div>
                <div class="col-md-4"><label class="form-label" for="expected_attendees">Expected Number of Attendees</label><input class="form-control" id="expected_attendees" name="expected_attendees" value="<?= $field($data, 'expected_attendees') ?>"></div>
                <div class="col-md-6"><label class="form-label" for="preferred_facility_id">Preferred Venue</label>
                    <select class="form-select" id="preferred_facility_id" name="preferred_facility_id">
                        <option value="">No preference</option>
                        <?php foreach ($facilities as $facility): ?><option value="<?= (int) $facility['id'] ?>" <?= $facilityId === (int) $facility['id'] ? 'selected' : '' ?>><?= e($facility['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label" for="funeral_location_type">Funeral Location</label>
                    <select class="form-select" id="funeral_location_type" name="funeral_location_type">
                        <option value="">Select location type</option>
                        <?php foreach (['Funeral Home', 'Residence', 'Church', 'Cemetery', 'Other'] as $location): ?>
                            <option value="<?= e($location) ?>" <?= ($data['funeral_location_type'] ?? '') === $location ? 'selected' : '' ?>><?= e($location) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-12"><label class="form-label" for="funeral_location">Location details</label><input class="form-control" id="funeral_location" name="funeral_location" value="<?= $field($data, 'funeral_location') ?>"></div>
            </div>
        <?php elseif ($code === 'MANANITA'): ?>
            <h2 class="h5 mt-2">Mañanita service</h2>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label" for="honoree_name">Birthday honoree</label><input class="form-control" id="honoree_name" name="honoree_name" value="<?= $field($data, 'honoree_name') ?>" required></div>
                <div class="col-md-3"><label class="form-label" for="contact_number">Contact number</label><input class="form-control" id="contact_number" name="contact_number" value="<?= $field($data, 'contact_number') ?>" required></div>
                <div class="col-md-3"><label class="form-label" for="contact_email">Email</label><input class="form-control" id="contact_email" name="contact_email" type="email" value="<?= $field($data, 'contact_email') ?>"></div>
                <div class="col-md-6"><label class="form-label" for="preferred_facility_id">Facility</label>
                    <select class="form-select" id="preferred_facility_id" name="preferred_facility_id" required>
                        <option value="">Select a facility</option>
                        <?php foreach ($facilities as $facility): ?><option value="<?= (int) $facility['id'] ?>" <?= $facilityId === (int) $facility['id'] ? 'selected' : '' ?>><?= e($facility['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label" for="event_purpose">Occasion details</label><input class="form-control" id="event_purpose" name="event_purpose" value="<?= $field($data, 'event_purpose') ?>" placeholder="e.g. 70th birthday mañanita"></div>
                <div class="col-md-4"><label class="form-label" for="preferred_date">Preferred date</label><input class="form-control" id="preferred_date" name="preferred_date" type="date" value="<?= e($preferredDate) ?>" required></div>
                <div class="col-md-4"><label class="form-label" for="preferred_time">Start time</label><input class="form-control" id="preferred_time" name="preferred_time" type="time" value="<?= e($preferredTime) ?>" required></div>
                <div class="col-md-4"><label class="form-label" for="preferred_end_time">End time</label><input class="form-control" id="preferred_end_time" name="preferred_end_time" type="time" value="<?= e($preferredEndTime) ?>" required></div>
                <div class="col-12"><label class="form-label" for="additional_information">Additional notes</label><textarea class="form-control" id="additional_information" name="additional_information" rows="3"><?= $field($data, 'additional_information') ?></textarea></div>
            </div>
        <?php elseif ($code === 'FACILITY_RESERVATION'): ?>
            <h2 class="h5 mt-2">Facility Reservation</h2>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label" for="preferred_facility_id">Facility</label>
                    <select class="form-select" id="preferred_facility_id" name="preferred_facility_id" required>
                        <option value="">Select a facility</option>
                        <?php foreach ($facilities as $facility): ?><option value="<?= (int) $facility['id'] ?>" <?= $facilityId === (int) $facility['id'] ? 'selected' : '' ?>><?= e($facility['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label" for="event_purpose">Event Name</label><input class="form-control" id="event_purpose" name="event_purpose" value="<?= $field($data, 'event_purpose') ?>" required></div>
                <div class="col-md-4"><label class="form-label" for="event_type">Event Type</label><input class="form-control" id="event_type" name="event_type" value="<?= $field($data, 'event_type') ?>"></div>
                <div class="col-md-4"><label class="form-label" for="preferred_date">Date</label><input class="form-control" id="preferred_date" name="preferred_date" type="date" value="<?= e($preferredDate) ?>" required></div>
                <div class="col-md-2"><label class="form-label" for="preferred_time">Start Time</label><input class="form-control" id="preferred_time" name="preferred_time" type="time" value="<?= e($preferredTime) ?>" required></div>
                <div class="col-md-2"><label class="form-label" for="preferred_end_time">End Time</label><input class="form-control" id="preferred_end_time" name="preferred_end_time" type="time" value="<?= e($preferredEndTime) ?>" required></div>
                <div class="col-md-4"><label class="form-label" for="expected_attendees">Expected Attendees</label><input class="form-control" id="expected_attendees" name="expected_attendees" value="<?= $field($data, 'expected_attendees') ?>"></div>
                <div class="col-md-8"><label class="form-label" for="setup_requirements">Equipment/Setup Requirements</label><input class="form-control" id="setup_requirements" name="setup_requirements" value="<?= $field($data, 'setup_requirements') ?>"></div>
            </div>
        <?php elseif ($code === 'HOUSE_BLESSING'): ?>
            <h2 class="h5 mt-2">Requester</h2>
            <div class="row g-3 mb-3">
                <div class="col-md-4"><label class="form-label" for="applicant_name">Full Name</label><input class="form-control" id="applicant_name" name="applicant_name" value="<?= $field($data, 'applicant_name') ?>" required></div>
                <div class="col-md-4"><label class="form-label" for="contact_number">Contact Number</label><input class="form-control" id="contact_number" name="contact_number" value="<?= $field($data, 'contact_number') ?>" required></div>
                <div class="col-md-4"><label class="form-label" for="contact_email">Email</label><input class="form-control" id="contact_email" name="contact_email" type="email" value="<?= $field($data, 'contact_email') ?>"></div>
            </div>
            <h2 class="h5">House Information</h2>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label" for="house_address">Address</label><input class="form-control" id="house_address" name="house_address" value="<?= $field($data, 'house_address') ?>" required></div>
                <div class="col-md-3"><label class="form-label" for="house_barangay">Barangay</label><input class="form-control" id="house_barangay" name="house_barangay" value="<?= $field($data, 'house_barangay') ?>"></div>
                <div class="col-md-3"><label class="form-label" for="house_city">Municipality/City</label><input class="form-control" id="house_city" name="house_city" value="<?= $field($data, 'house_city') ?>"></div>
                <div class="col-md-6"><label class="form-label" for="house_landmark">Landmark</label><input class="form-control" id="house_landmark" name="house_landmark" value="<?= $field($data, 'house_landmark') ?>" required></div>
                <div class="col-md-6"><label class="form-label" for="residence_type">Residence Type</label><input class="form-control" id="residence_type" name="residence_type" value="<?= $field($data, 'residence_type') ?>"></div>
            </div>
            <h2 class="h5">Schedule</h2>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label" for="preferred_date">Preferred Date</label><input class="form-control" id="preferred_date" name="preferred_date" type="date" value="<?= e($preferredDate) ?>" required></div>
                <div class="col-md-6"><label class="form-label" for="preferred_time">Preferred Time</label><input class="form-control" id="preferred_time" name="preferred_time" type="time" min="08:00" max="16:00" value="<?= e($preferredTime) ?>" required><small class="text-muted">Service hours: 8:00 AM - 4:00 PM</small></div>
            </div>
        <?php elseif ($code === 'PRE_MARRIAGE_COUNSELING'): ?>
            <h2 class="h5 mt-2">Couple Information</h2>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label" for="partner_1_name">Partner 1 Full Name</label><input class="form-control" id="partner_1_name" name="partner_1_name" value="<?= $field($data, 'partner_1_name') ?>" required></div>
                <div class="col-md-6"><label class="form-label" for="partner_2_name">Partner 2 Full Name</label><input class="form-control" id="partner_2_name" name="partner_2_name" value="<?= $field($data, 'partner_2_name') ?>" required></div>
                <div class="col-md-6"><label class="form-label" for="contact_number">Contact Number</label><input class="form-control" id="contact_number" name="contact_number" value="<?= $field($data, 'contact_number') ?>" required></div>
                <div class="col-md-6"><label class="form-label" for="contact_email">Email</label><input class="form-control" id="contact_email" name="contact_email" type="email" value="<?= $field($data, 'contact_email') ?>"></div>
            </div>
            <h2 class="h5">Schedule</h2>
            <div class="row g-3 mb-3">
                <div class="col-md-4"><label class="form-label" for="preferred_date">Preferred Date</label><input class="form-control" id="preferred_date" name="preferred_date" type="date" value="<?= e($preferredDate) ?>" required></div>
                <div class="col-md-4"><label class="form-label" for="preferred_time">Preferred Time</label><input class="form-control" id="preferred_time" name="preferred_time" type="time" value="<?= e($preferredTime) ?>" required></div>
                <div class="col-md-4"><label class="form-label" for="counseling_area">Counseling area</label><input class="form-control" id="counseling_area" value="<?= e(($counselingOffice['name'] ?? 'Counseling Office') . (!empty($counselingOffice['location']) ? ' · ' . $counselingOffice['location'] : '')) ?>" readonly><input type="hidden" name="preferred_facility_id" value="<?= (int) ($counselingOffice['id'] ?? 0) ?>"></div>
            </div>
            <h2 class="h5">Counseling Information</h2>
            <div class="row g-3 mb-3">
                <div class="col-md-4"><label class="form-label" for="premarriage_type">Counseling type</label><input class="form-control" id="premarriage_type" name="counseling_type" value="<?= e($data['counseling_type'] ?? 'Pre-Marriage Counseling') ?>" readonly></div>
                <div class="col-md-4"><label class="form-label" for="first_counseling_session">First counseling session?</label>
                    <select class="form-select" id="first_counseling_session" name="first_counseling_session">
                        <option value="">Select</option>
                        <?php foreach (['Yes', 'No'] as $option): ?><option value="<?= e($option) ?>" <?= ($data['first_counseling_session'] ?? '') === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label" for="expected_wedding_date">Expected Wedding Date</label><input class="form-control" id="expected_wedding_date" name="expected_wedding_date" type="date" value="<?= $field($data, 'expected_wedding_date') ?>"></div>
                <div class="col-md-4"><label class="form-label" for="previous_sessions">Number of Previous Sessions</label><input class="form-control" id="previous_sessions" name="previous_sessions" value="<?= $field($data, 'previous_sessions') ?>"></div>
                <div class="col-12"><label class="form-label" for="counseling_topics">Topics/Concerns to Discuss</label><textarea class="form-control" id="counseling_topics" name="counseling_topics" rows="3"><?= $field($data, 'counseling_topics') ?></textarea></div>
            </div>
        <?php elseif ($code === 'PASTORAL_COUNSELING'): ?>
            <h2 class="h5 mt-2">Requester</h2>
            <div class="row g-3 mb-3">
                <div class="col-md-4"><label class="form-label" for="applicant_name">Full Name</label><input class="form-control" id="applicant_name" name="applicant_name" value="<?= $field($data, 'applicant_name') ?>" required></div>
                <div class="col-md-4"><label class="form-label" for="contact_number">Contact Number</label><input class="form-control" id="contact_number" name="contact_number" value="<?= $field($data, 'contact_number') ?>"></div>
                <div class="col-md-4"><label class="form-label" for="contact_email">Email</label><input class="form-control" id="contact_email" name="contact_email" type="email" value="<?= $field($data, 'contact_email') ?>"></div>
            </div>
            <h2 class="h5">Counseling Type</h2>
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label" for="counseling_type">Counseling Type</label><input class="form-control" id="counseling_type" name="counseling_type" value="<?= e($data['counseling_type'] ?? 'Pastoral Counseling') ?>" readonly></div>
            </div>
            <h2 class="h5">Schedule</h2>
            <div class="row g-3 mb-3">
                <div class="col-md-4"><label class="form-label" for="pastoral_date">Preferred Date</label><input class="form-control" id="pastoral_date" name="preferred_date" type="date" value="<?= e($preferredDate) ?>" required></div>
                <div class="col-md-4"><label class="form-label" for="pastoral_time">Preferred Time</label><input class="form-control" id="pastoral_time" name="preferred_time" type="time" value="<?= e($preferredTime) ?>" required></div>
                <div class="col-md-4"><label class="form-label" for="pastoral_area">Counseling area</label><input class="form-control" id="pastoral_area" value="<?= e(($counselingOffice['name'] ?? 'Counseling Office') . (!empty($counselingOffice['location']) ? ' · ' . $counselingOffice['location'] : '')) ?>" readonly><input type="hidden" name="preferred_facility_id" value="<?= (int) ($counselingOffice['id'] ?? 0) ?>"></div>
            </div>
            <h2 class="h5">Reason/Short Description</h2>
            <div class="mb-3">
                <label class="form-label" for="counseling_reason">Short description of the request</label>
                <textarea class="form-control" id="counseling_reason" name="counseling_reason" rows="3" maxlength="400"><?= $field($data, 'counseling_reason') ?></textarea>
                <small class="text-muted">You do not need to explain sensitive personal information in detail.</small>
            </div>
        <?php else: ?>
            <h2 class="h5 mt-2">Request Information</h2>
            <div class="row g-3 mb-3">
                <div class="col-md-4"><label class="form-label" for="preferred_date">Preferred Date</label><input class="form-control" id="preferred_date" name="preferred_date" type="date" value="<?= e($preferredDate) ?>" required></div>
                <div class="col-md-4"><label class="form-label" for="preferred_time">Preferred Time</label><input class="form-control" id="preferred_time" name="preferred_time" type="time" value="<?= e($preferredTime) ?>" required></div>
                <div class="col-md-4"><label class="form-label" for="preferred_facility_id">Preferred Venue</label>
                    <select class="form-select" id="preferred_facility_id" name="preferred_facility_id">
                        <option value="">No preference</option>
                        <?php foreach ($facilities as $facility): ?><option value="<?= (int) $facility['id'] ?>" <?= $facilityId === (int) $facility['id'] ? 'selected' : '' ?>><?= e($facility['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
        <?php endif; ?>

        <h2 class="h5">Additional Information</h2>
        <div class="row g-3 mb-3">
            <div class="col-12"><label class="form-label" for="special_requests">Special Requests</label><textarea class="form-control" id="special_requests" name="special_requests" rows="2"><?= $field($data, 'special_requests') ?></textarea></div>
            <div class="col-12"><label class="form-label" for="additional_information">Additional Notes</label><textarea class="form-control" id="additional_information" name="additional_information" rows="3"><?= $field($data, 'additional_information') ?></textarea></div>
        </div>

        <h2 class="h5">Documents</h2>
        <div class="mb-4">
            <label class="form-label" for="documents"><?= $code === 'FUNERAL_SERVICE' ? 'Death Certificate and other required documents' : 'Required supporting documents' ?></label>
            <input class="form-control" id="documents" name="documents[]" type="file" multiple accept=".pdf,.jpg,.jpeg,.png">
            <small class="text-muted">PDF, JPG, or PNG. Optional unless church staff later asks for documents.</small>
        </div>

        <button class="btn btn-primary" type="submit">Submit request</button>
        <a class="btn btn-outline-secondary" href="index.php">Cancel</a>
    </form>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
