<?php
declare(strict_types=1);

$filters = $notificationInbox['filters'];
$items = $notificationInbox['items'];
$types = $notificationInbox['types'];
$unread = (int) $notificationInbox['unread'];
$total = (int) ($notificationInbox['total'] ?? count($items));
$filterFields = static function () use ($filters): void {
    ?>
    <input type="hidden" name="q" value="<?= e($filters['q']) ?>">
    <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
    <input type="hidden" name="type" value="<?= e($filters['type']) ?>">
    <input type="hidden" name="period" value="<?= e($filters['period']) ?>">
    <?php
};
?>
<div class="cmmp-page-header">
    <div>
        <p class="cmmp-kicker">Inbox</p>
        <h1>Notifications</h1>
        <p><?= e($notificationLead) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if ($unread > 0): ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="read_all">
                <?php $filterFields(); ?>
                <button class="btn btn-outline-light" type="submit">Mark all read</button>
            </form>
        <?php endif; ?>
        <?php if ($total > 0): ?>
            <form method="post" onsubmit="return confirm('Delete all of your notifications? This cannot be undone. Related church records will not be deleted.');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_all">
                <?php $filterFields(); ?>
                <button class="btn btn-outline-danger" type="submit">Delete all</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<form class="cmmp-card cmmp-note-filters" method="get">
    <label class="cmmp-note-search">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input class="form-control" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search title or message">
    </label>
    <select class="form-select" name="status" aria-label="Read status">
        <option value="all" <?= $filters['status'] === 'all' ? 'selected' : '' ?>>All</option>
        <option value="unread" <?= $filters['status'] === 'unread' ? 'selected' : '' ?>>Unread</option>
        <option value="read" <?= $filters['status'] === 'read' ? 'selected' : '' ?>>Read</option>
    </select>
    <select class="form-select" name="type" aria-label="Notification type">
        <option value="">All types</option>
        <?php foreach ($types as $type): ?>
            <option value="<?= e($type) ?>" <?= $filters['type'] === $type ? 'selected' : '' ?>><?= e(notification_type_label($type)) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-select" name="period" aria-label="Date">
        <option value="all" <?= $filters['period'] === 'all' ? 'selected' : '' ?>>Any date</option>
        <option value="today" <?= $filters['period'] === 'today' ? 'selected' : '' ?>>Today</option>
        <option value="week" <?= $filters['period'] === 'week' ? 'selected' : '' ?>>Last 7 days</option>
        <option value="month" <?= $filters['period'] === 'month' ? 'selected' : '' ?>>This month</option>
    </select>
    <button class="btn btn-primary" type="submit">Filter</button>
    <?php if ($filters['q'] !== '' || $filters['status'] !== 'all' || $filters['type'] !== '' || $filters['period'] !== 'all'): ?>
        <a class="btn btn-outline-primary" href="<?= e(basename((string) $_SERVER['SCRIPT_NAME'])) ?>">Clear</a>
    <?php endif; ?>
</form>

<?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success">The notification was deleted. Related church records were not changed.</div>
<?php endif; ?>
<?php if (isset($_GET['deleted_selected'])): ?>
    <div class="alert alert-success">The selected notifications were deleted. Related church records were not changed.</div>
<?php endif; ?>
<?php if (isset($_GET['deleted_all'])): ?>
    <div class="alert alert-success">All of your notifications were deleted. Related church records were not changed.</div>
<?php endif; ?>
<?php if (!empty($_GET['delete_error'])): ?>
    <div class="alert alert-danger"><?= e((string) $_GET['delete_error']) ?></div>
<?php endif; ?>

<?php if ($items !== []): ?>
<form method="post" class="cmmp-note-bulk" data-cmmp-note-form>
    <?= csrf_field() ?>
    <?php $filterFields(); ?>
    <div class="cmmp-note-toolbar">
        <label class="cmmp-note-select-all">
            <input type="checkbox" data-cmmp-select-all>
            <span>Select all</span>
        </label>
        <button class="btn btn-sm btn-outline-danger" type="submit" name="action" value="delete_selected" onclick="return confirm('Delete the selected notifications? Related church records will not be deleted.');">Delete selected</button>
    </div>
    <div class="cmmp-note-list">
        <?php foreach ($items as $item): ?>
            <?php $isUnread = empty($item['read_at']); ?>
            <article class="cmmp-note <?= $isUnread ? 'is-unread' : '' ?>">
                <label class="cmmp-note-select">
                    <input type="checkbox" name="ids[]" value="<?= (int) $item['id'] ?>">
                    <span class="visually-hidden">Select <?= e($item['title']) ?></span>
                </label>
                <span class="cmmp-note-icon" aria-hidden="true"><i class="fa-solid fa-bell"></i></span>
                <div class="cmmp-note-body">
                    <div class="cmmp-note-top">
                        <strong><?= e($item['title']) ?></strong>
                        <time datetime="<?= e((string) $item['created_at']) ?>"><?= e(date('M j, Y g:i A', strtotime((string) $item['created_at']))) ?></time>
                    </div>
                    <p><?= e($item['message']) ?></p>
                    <div class="cmmp-note-meta">
                        <?php if (!empty($item['notification_type'])): ?>
                            <span class="cmmp-note-type"><?= e(notification_type_label((string) $item['notification_type'])) ?></span>
                        <?php endif; ?>
                        <?php if ($isUnread): ?>
                            <span class="cmmp-note-state">Unread</span>
                        <?php else: ?>
                            <span class="cmmp-note-state is-read">Read</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="cmmp-note-actions">
                    <?php if ($isUnread): ?>
                        <button class="btn btn-sm btn-outline-primary" type="submit" name="mark_read" value="<?= (int) $item['id'] ?>">Mark read</button>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-outline-danger" type="submit" name="delete_one" value="<?= (int) $item['id'] ?>" onclick="return confirm('Delete this notification? Related service requests, appointments, and events will not be deleted.');">Delete</button>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</form>
<script>
(function () {
    var form = document.querySelector('[data-cmmp-note-form]');
    if (!form) { return; }
    var master = form.querySelector('[data-cmmp-select-all]');
    var boxes = form.querySelectorAll('input[name="ids[]"]');
    if (!master) { return; }
    master.addEventListener('change', function () {
        boxes.forEach(function (box) { box.checked = master.checked; });
    });
    boxes.forEach(function (box) {
        box.addEventListener('change', function () {
            master.checked = Array.prototype.every.call(boxes, function (item) { return item.checked; });
        });
    });
})();
</script>
<?php else: ?>
    <div class="cmmp-card cmmp-note-empty">
        <i class="fa-regular fa-bell-slash" aria-hidden="true"></i>
        <strong>No notifications match these filters.</strong>
        <p>Try another status, type, or date.</p>
    </div>
<?php endif; ?>
