<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/event.php';

require_role('MEMBER');
$memberId = member_id_for_user((int) current_user_id()) ?? ensure_member_profile((int) current_user_id());
if (!$memberId) { http_response_code(404); exit('Your member profile has not been created yet.'); }
$events = Event::registered($memberId);
$pageTitle = 'My registrations';
$activeNav = 'registrations';
$breadcrumbItems = [['label' => 'My registrations', 'url' => 'index.php']];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header"><div><h1>My registrations</h1><p>Review the events you have registered for.</p></div></div>
<div class="cmmp-table-wrap"><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Event</th><th>Schedule</th><th>Category</th><th>Status</th><th>Registered</th></tr></thead><tbody><?php if ($events === []): ?><tr><td colspan="5" class="text-center text-muted py-5">You have no active event registrations.</td></tr><?php endif; ?><?php foreach ($events as $event): ?><tr><td><a href="<?= e(APP_BASE_URL) ?>/member/events/view.php?id=<?= (int) $event['id'] ?>"><?= e($event['title']) ?></a></td><td><?= e($event['start_datetime']) ?></td><td><?= e($event['category_name'] ?? 'Event') ?></td><td><span class="badge text-bg-success"><?= e($event['registration_status']) ?></span></td><td><?= e($event['registered_at']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
