<?php
declare(strict_types=1);

$eventCoverClass = $eventCoverClass ?? '';
$eventImageUrl = event_image_url(isset($event['event_image']) ? (string) $event['event_image'] : null);
?>
<div class="cmmp-event-cover<?= $eventCoverClass !== '' ? ' ' . e($eventCoverClass) : '' ?><?= $eventImageUrl ? '' : ' is-empty' ?>">
    <?php if ($eventImageUrl): ?>
        <img src="<?= e($eventImageUrl) ?>" alt="<?= e((string) ($event['title'] ?? 'Event')) ?>">
    <?php else: ?>
        <span><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></span>
    <?php endif; ?>
</div>
