<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/pagination.php';
require_once __DIR__ . '/../../models/Announcement.php';

require_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_id'])) {
    require_valid_csrf_token();
    $archiveId = filter_input(INPUT_POST, 'archive_id', FILTER_VALIDATE_INT);
    if ($archiveId) {
        Announcement::archive((int) $archiveId, current_user_id());
        header('Location: index.php?archived=1');
        exit;
    }
}

$page = list_page();
$perPage = list_per_page();
$result = Announcement::paginate($page, $perPage);
$items = $result['items'];
$page = min($page, pagination_total_pages($result['total'], $perPage));
$pageTitle = 'Announcements';
$activeNav = 'announcements';
require __DIR__ . '/../../includes/header.php';
?>
<div class="cmmp-page-header">
    <div><h1>Announcements</h1><p>Publish targeted portal announcements.</p></div>
    <a class="btn btn-primary" href="edit.php">Create announcement</a>
</div>
<?php if (isset($_GET['archived'])): ?><div class="alert alert-success">Announcement archived.</div><?php endif; ?>
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
                    <td><?= e($item['published_at'] ?? 'Not published') ?></td>
                    <td><?= e($item['expires_at'] ?? 'Never') ?></td>
                    <td class="text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="edit.php?id=<?= (int) $item['id'] ?>">Edit</a>
                        <?php if ($item['announcement_status'] !== 'ARCHIVED'): ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('Archive this announcement?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="archive_id" value="<?= (int) $item['id'] ?>">
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
$paginationFilters = [];
$paginationLabel = 'Announcement pages';
require __DIR__ . '/../../includes/pagination.php';
require __DIR__ . '/../../includes/footer.php';
