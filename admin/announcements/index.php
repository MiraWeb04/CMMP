<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/pagination.php';
require_once __DIR__ . '/../../models/Announcement.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);

$status = strtoupper(trim((string) ($_GET['status'] ?? '')));
if (!in_array($status, ['', 'DRAFT', 'PUBLISHED', 'ARCHIVED'], true)) {
    $status = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();
    $action = (string) ($_POST['action'] ?? '');
    $targetId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $location = 'index.php' . ($status !== '' ? '?status=' . urlencode($status) : '');
    $separator = str_contains($location, '?') ? '&' : '?';
    try {
        if (!$targetId) {
            throw new InvalidArgumentException('Invalid announcement.');
        }
        if ($action === 'restore') {
            Announcement::restore((int) $targetId, current_user_id());
            header('Location: ' . $location . $separator . 'restored=1');
            exit;
        }
        if ($action === 'archive') {
            Announcement::archive((int) $targetId, current_user_id());
            header('Location: ' . $location . $separator . 'archived=1');
            exit;
        }
        throw new InvalidArgumentException('Unknown announcement action.');
    } catch (InvalidArgumentException $exception) {
        header('Location: ' . $location . $separator . 'error=' . urlencode($exception->getMessage()));
        exit;
    }
}

$page = list_page();
$perPage = list_per_page();
$result = Announcement::paginate($page, $perPage, $status);
$items = $result['items'];
$page = min($page, pagination_total_pages($result['total'], $perPage));
$pageTitle = 'Announcements';
$activeNav = 'announcements';
require __DIR__ . '/../../includes/header.php';
?>
<div class="cmmp-page-header">
    <div><h1>Announcements</h1><p>Publish targeted portal announcements. Archive keeps the record without showing it to members.</p></div>
    <a class="btn btn-primary" href="edit.php">Create announcement</a>
</div>
<?php if (isset($_GET['archived'])): ?><div class="alert alert-success">Announcement archived.</div><?php endif; ?>
<?php if (isset($_GET['restored'])): ?><div class="alert alert-success">Announcement restored.</div><?php endif; ?>
<?php if (!empty($_GET['error'])): ?><div class="alert alert-danger"><?= e((string) $_GET['error']) ?></div><?php endif; ?>
<div class="cmmp-card mb-4">
    <form method="get" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">All statuses</option>
                <?php foreach (['DRAFT', 'PUBLISHED', 'ARCHIVED'] as $item): ?>
                    <option value="<?= e($item) ?>" <?= $status === $item ? 'selected' : '' ?>><?= e($item) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" type="submit">Filter</button>
            <a class="btn btn-outline-secondary" href="index.php">Reset</a>
        </div>
    </form>
</div>
<div class="cmmp-table-wrap">
    <table class="table table-hover">
        <thead>
            <tr><th>Title</th><th>Audience</th><th>Status</th><th>Published</th><th>Expires</th><th>Action</th></tr>
        </thead>
        <tbody>
            <?php if ($items === []): ?><tr><td colspan="6" class="text-center py-5 text-muted">No announcements found.</td></tr><?php endif; ?>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= e($item['title']) ?></td>
                    <td><?= e($item['audience_role'] ?? 'ALL') ?></td>
                    <td><?= e($item['announcement_status']) ?></td>
                    <td><?= e(format_app_datetime($item['published_at'] ?? null) ?: 'Not published') ?></td>
                    <td><?= e(format_app_datetime($item['expires_at'] ?? null) ?: 'Never') ?></td>
                    <td class="text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="edit.php?id=<?= (int) $item['id'] ?>">Edit</a>
                        <?php if ($item['announcement_status'] === 'ARCHIVED'): ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('Restore this announcement?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="restore">
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <button class="btn btn-sm btn-outline-success" type="submit">Restore</button>
                            </form>
                        <?php else: ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('Archive this announcement? Members will no longer see it.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="archive">
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Archive</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
$paginationTotal = $result['total'];
$paginationPage = $page;
$paginationPerPage = $perPage;
$paginationFilters = ['status' => $status];
$paginationLabel = 'Announcement pages';
require __DIR__ . '/../../includes/pagination.php';
require __DIR__ . '/../../includes/footer.php';
