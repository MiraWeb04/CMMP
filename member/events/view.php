<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/event.php';

require_role('MEMBER');
$memberId = member_id_for_user((int) current_user_id());
if (!$memberId) { http_response_code(404); exit('Your member profile has not been created yet.'); }
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$event = $id ? Event::find($id, $memberId) : null;
if (!$event || $event['event_status'] !== 'PUBLISHED') { http_response_code(404); exit('Event not found.'); }
$pageTitle = $event['title'];
$activeNav = 'events';
$breadcrumbItems = [['label' => 'Events', 'url' => 'index.php'], ['label' => $event['title'], 'url' => 'view.php?id=' . (int) $event['id']]];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header"><div><h1><?= e($event['title']) ?></h1><p><?= e($event['category_name'] ?? 'Church event') ?></p></div><a class="btn btn-outline-secondary" href="index.php">Back to events</a></div>
<?php if (isset($_GET['error'])): ?><div class="alert alert-danger"><?= e($_GET['error']) ?></div><?php endif; ?>
<div class="row g-3"><div class="col-lg-8"><div class="cmmp-card"><?php $eventCoverClass = 'is-hero'; require __DIR__ . '/../../includes/event-cover.php'; ?><h2 class="h5">Event details</h2><p><?= nl2br(e($event['description'] ?? 'No description provided.')) ?></p><dl class="row mb-0"><dt class="col-sm-4">When</dt><dd class="col-sm-8"><?= e($event['start_datetime']) ?><br><?= e($event['end_datetime']) ?></dd><dt class="col-sm-4">Where</dt><dd class="col-sm-8"><?= e($event['facility_name'] ?? $event['location'] ?? 'To be announced') ?></dd><dt class="col-sm-4">Capacity</dt><dd class="col-sm-8"><?= e($event['capacity'] === null ? 'Unlimited' : (string) $event['capacity']) ?> (<?= (int) $event['registration_count'] ?> registered)</dd><dt class="col-sm-4">Deadline</dt><dd class="col-sm-8"><?= e($event['registration_deadline'] ?? 'No deadline') ?></dd></dl></div></div><div class="col-lg-4"><div class="cmmp-card"><h2 class="h5">Registration</h2><?php if (!$event['registration_required']): ?><p class="text-muted">Registration is not required for this event.</p><?php elseif ($event['own_registration_status'] === 'REGISTERED'): ?><p class="text-success"><i class="fa-solid fa-circle-check me-1"></i>You are registered.</p><form method="post" action="registration.php"><input type="hidden" name="action" value="cancel"><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>"><?= csrf_field() ?><button class="btn btn-outline-danger" type="submit">Cancel registration</button></form><?php elseif ($event['registration_deadline'] !== null && strtotime($event['registration_deadline']) < time()): ?><p class="text-muted">Registration has closed.</p><?php elseif ($event['capacity'] !== null && (int) $event['registration_count'] >= (int) $event['capacity']): ?><p class="text-muted">This event has reached capacity.</p><?php else: ?><form method="post" action="registration.php"><input type="hidden" name="action" value="register"><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>"><?= csrf_field() ?><button class="btn btn-primary" type="submit">Register for event</button></form><?php endif; ?></div></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
