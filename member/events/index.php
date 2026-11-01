<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/event.php';

require_role('MEMBER');
$memberId = member_id_for_user((int) current_user_id());
if (!$memberId) { http_response_code(404); exit('Your member profile has not been created yet.'); }
$events = Event::upcoming($memberId);
$pageTitle = 'Church events';
$activeNav = 'events';
$breadcrumbItems = [['label' => 'Events', 'url' => 'index.php']];
require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/breadcrumb.php';
?>
<div class="cmmp-page-header"><div><h1>Church events</h1><p>Explore upcoming gatherings and register where available.</p></div></div>
<?php if (isset($_GET['registered'])): ?><div class="alert alert-success">You are registered for this event.</div><?php endif; ?><?php if (isset($_GET['cancelled'])): ?><div class="alert alert-success">Your registration was cancelled.</div><?php endif; ?><?php if (isset($_GET['error'])): ?><div class="alert alert-danger"><?= e($_GET['error']) ?></div><?php endif; ?>
<div class="row g-3">
<?php if ($events === []): ?><div class="col-12"><div class="cmmp-card text-center text-muted py-5">No upcoming events are currently published.</div></div><?php endif; ?>
<?php foreach ($events as $event): ?>
    <div class="col-md-6 col-xl-4">
        <article class="cmmp-card cmmp-event-card h-100">
            <?php $eventCoverClass = ''; require __DIR__ . '/../../includes/event-cover.php'; ?>
            <div class="cmmp-event-body">
                <div class="d-flex justify-content-between gap-2 mb-3">
                    <span class="badge text-bg-light"><?= e($event['category_name'] ?? 'Event') ?></span>
                    <?php if ($event['own_registration_status'] === 'REGISTERED'): ?><span class="badge text-bg-success">Registered</span><?php endif; ?>
                </div>
                <h2 class="h5"><a class="text-decoration-none" href="view.php?id=<?= (int) $event['id'] ?>"><?= e($event['title']) ?></a></h2>
                <p class="text-muted small mb-2"><i class="fa-solid fa-calendar me-1"></i><?= e($event['start_datetime']) ?></p>
                <p class="text-muted small mb-3"><i class="fa-solid fa-location-dot me-1"></i><?= e($event['facility_name'] ?? $event['location'] ?? 'Location to be announced') ?></p>
                <p><?= e(mb_strimwidth((string) ($event['description'] ?? ''), 0, 130, '...')) ?></p>
                <a class="btn btn-sm btn-outline-primary mt-auto" href="view.php?id=<?= (int) $event['id'] ?>">View details</a>
            </div>
        </article>
    </div>
<?php endforeach; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
