<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/facility.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);
$facility = ['facility_status' => 'AVAILABLE'];
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();
    $facility = array_merge($facility, facility_input($_POST, true));
    $errors = validate_facility_data($facility, true);
    if ($errors === []) {
        try {
            $duplicate = database()->prepare(
                'SELECT name, code FROM facilities WHERE name = :name OR code = :code LIMIT 2'
            );
            $duplicate->execute(['name' => $facility['name'], 'code' => $facility['code']]);
            $duplicateNames = false;
            $duplicateCodes = false;
            foreach ($duplicate->fetchAll() as $existing) {
                $duplicateNames = $duplicateNames || strcasecmp((string) $existing['name'], (string) $facility['name']) === 0;
                $duplicateCodes = $duplicateCodes || strcasecmp((string) $existing['code'], (string) $facility['code']) === 0;
            }
            if ($duplicateNames) {
                $errors[] = 'A facility with this name already exists.';
            }
            if ($duplicateCodes) {
                $errors[] = 'A facility with this code already exists.';
            }
            if ($errors !== []) {
                throw new InvalidArgumentException('Duplicate facility details.');
            }
            Facility::create($facility);
            header('Location: index.php?saved=1');
            exit;
        } catch (Throwable $exception) {
            error_log('CMMP facility creation failed: ' . $exception->getMessage());
            if ($exception instanceof PDOException && $exception->getCode() === '23000' && $errors === []) {
                $errors[] = 'That facility name or code is already in use.';
            } elseif (!($exception instanceof InvalidArgumentException)) {
                $errors[] = 'Facility could not be created.';
            }
        }
    }
}
$pageTitle = 'Add facility';
$breadcrumbItems = [['label' => 'Facilities', 'url' => 'index.php'], ['label' => 'Add facility', 'url' => 'add.php']];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header"><div><h1>Add facility</h1><p>Register a church space for future centralized scheduling.</p></div></div><div class="cmmp-card"><?php $formAction = 'add.php'; $submitLabel = 'Create facility'; $cancelUrl = 'index.php'; require __DIR__ . '/../../includes/facility-form.php'; ?></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
