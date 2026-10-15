<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/event.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);
$event = ['event_status' => 'DRAFT', 'registration_required' => 0];
$categories = Event::categories();
$facilities = Event::facilities();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();
    $event = array_merge($event, event_input($_POST, true));
    $errors = validate_event_data($event);
    if ($errors === []) {
        try {
            $event['created_by_user_id'] = (int) current_user_id();
            $event['event_image'] = upload_event_image($_FILES['event_image'] ?? null);
            Event::create($event);
            header('Location: index.php?saved=1');
            exit;
        } catch (Throwable $exception) {
            error_log('CMMP event creation failed: ' . $exception->getMessage());
            $errors[] = $exception instanceof PDOException && $exception->getCode() === '23000' ? 'The selected category or facility is no longer available.' : ($exception instanceof RuntimeException ? $exception->getMessage() : 'Event could not be created.');
        }
    }
}
$pageTitle = 'Create event';
$breadcrumbItems = [['label' => 'Events', 'url' => 'index.php'], ['label' => 'Create event', 'url' => 'add.php']];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header"><div><h1>Create event</h1><p>Plan a church event using the centralized facility relationship.</p></div></div><div class="cmmp-card"><?php $formAction = 'add.php'; $submitLabel = 'Create event'; $cancelUrl = 'index.php'; require __DIR__ . '/../../includes/event-form.php'; ?></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
