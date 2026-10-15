<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/facility.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$facility = $id ? Facility::find($id) : null;
if (!$facility) { http_response_code(404); exit('Facility not found.'); }
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();
    $updates = facility_input($_POST, false);
    $errors = validate_facility_data(array_merge($facility, $updates), false);
    $facility = array_merge($facility, $updates);
    if ($errors === []) {
        try {
            Facility::update((int) $facility['id'], $updates);
            Facility::setStatus((int) $facility['id'], (string) ($_POST['facility_status'] ?? $facility['facility_status']));
            header('Location: view.php?id=' . (int) $facility['id'] . '&saved=1');
            exit;
        } catch (Throwable $exception) {
            error_log('CMMP facility update failed: ' . $exception->getMessage());
            $errors[] = $exception instanceof PDOException && $exception->getCode() === '23000' ? 'That facility name is already in use.' : 'Facility could not be updated.';
        }
    }
}
$pageTitle = 'Edit facility';
$breadcrumbItems = [['label' => 'Facilities', 'url' => 'index.php'], ['label' => 'Edit facility', 'url' => 'edit.php?id=' . (int) $facility['id']]];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header"><div><h1>Edit facility</h1><p>Update <?= e($facility['code']) ?> facility details.</p></div></div><div class="cmmp-card"><?php $formAction = 'edit.php?id=' . (int) $facility['id']; $submitLabel = 'Save changes'; $cancelUrl = 'view.php?id=' . (int) $facility['id']; require __DIR__ . '/../../includes/facility-form.php'; ?></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
