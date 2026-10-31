<?php
declare(strict_types=1);

$filters = $notificationInbox['filters'];
$items = $notificationInbox['items'];
$types = $notificationInbox['types'];
$unread = (int) $notificationInbox['unread'];
?>
<div class="cmmp-page-header">
    <div>
        <p class="cmmp-kicker">Inbox</p>
        <h1>Notifications</h1>
        <p><?= e($notificationLead) ?></p>
    </div>
    <?php if ($unread > 0): ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="read_all">
            <input type="hidden" name="q" value="<?= e($filters['q']) ?>">
            <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
            <input type="hidden" name="type" value="<?= e($filters['type']) ?>">
            <input type="hidden" name="period" value="<?= e($filters['period']) ?>">
            <button class="btn btn-outline-light" type="submit">Mark all read</button>
        </form>
    <?php endif; ?>
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
<?php if (!empty($_GET['delete_error'])): ?>
    <div class="alert alert-danger"><?= e((string) $_GET['delete_error']) ?></div>
<?php endif; ?>

<div class="cmmp-note-list">
    <?php foreach ($items as $item): ?>
        <?php $isUnread = empty($item['read_at']); ?>
        <article class="cmmp-note <?= $isUnread ? 'is-unread' : '' ?>">
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
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="read">
                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                        <input type="hidden" name="q" value="<?= e($filters['q']) ?>">
                        <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
                        <input type="hidden" name="type" value="<?= e($filters['type']) ?>">
                        <input type="hidden" name="period" value="<?= e($filters['period']) ?>">
                        <button class="btn btn-sm btn-outline-primary" type="submit">Mark read</button>
                    </form>
                <?php endif; ?>
                <form method="post" onsubmit="return confirm('Delete this notification? Related service requests, appointments, and events will not be deleted.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                    <input type="hidden" name="q" value="<?= e($filters['q']) ?>">
                    <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
                    <input type="hidden" name="type" value="<?= e($filters['type']) ?>">
                    <input type="hidden" name="period" value="<?= e($filters['period']) ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if ($items === []): ?>
        <div class="cmmp-card cmmp-note-empty">
            <i class="fa-regular fa-bell-slash" aria-hidden="true"></i>
            <strong>No notifications match these filters.</strong>
            <p>Try another status, type, or date.</p>
        </div>
    <?php endif; ?>
</div>
