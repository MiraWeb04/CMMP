<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/event.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$event = $id ? Event::find($id) : null;
if (!$event) { http_response_code(404); exit('Event not found.'); }
$categories = Event::categories();
$facilities = Event::facilities();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();
    $updates = event_input($_POST, false);
    $event = array_merge($event, $updates);
    $errors = validate_event_data($event);
    if ($errors === []) {
        try {
            $image = upload_event_image($_FILES['event_image'] ?? null);
            if ($image !== null) { $updates['event_image'] = $image; }
            Event::update((int) $event['id'], $updates);
            Event::setStatus((int) $event['id'], (string) $event['event_status']);
            header('Location: view.php?id=' . (int) $event['id'] . '&saved=1');
            exit;
        } catch (Throwable $exception) {
            error_log('CMMP event update failed: ' . $exception->getMessage());
            $errors[] = $exception instanceof RuntimeException ? $exception->getMessage() : 'Event could not be updated.';
        }
    }
}
$pageTitle = 'Edit event';
$breadcrumbItems = [['label' => 'Events', 'url' => 'index.php'], ['label' => 'Edit event', 'url' => 'edit.php?id=' . (int) $event['id']]];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header"><div><h1>Edit event</h1><p>Update <?= e($event['title']) ?>.</p></div></div><div class="cmmp-card"><?php $formAction = 'edit.php?id=' . (int) $event['id']; $submitLabel = 'Save changes'; $cancelUrl = 'view.php?id=' . (int) $event['id']; require __DIR__ . '/../../includes/event-form.php'; ?></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
