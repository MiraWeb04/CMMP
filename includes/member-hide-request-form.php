<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/csrf.php';

$requestId = (int) ($hideRequestId ?? 0);
$returnTo = (string) ($hideReturnTo ?? 'services');
$buttonClass = (string) ($hideButtonClass ?? 'btn btn-sm btn-outline-danger');
$buttonLabel = (string) ($hideButtonLabel ?? 'Delete');
$confirmMessage = 'Delete this completed request from your list? Church staff will still keep the original record.';
?>
<form method="post" action="<?= e(APP_BASE_URL) ?>/member/services/hide.php" class="d-inline" onsubmit="return confirm(<?= json_encode($confirmMessage) ?>);">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $requestId ?>">
    <input type="hidden" name="return" value="<?= e($returnTo) ?>">
    <button class="<?= e($buttonClass) ?>" type="submit"><?= e($buttonLabel) ?></button>
</form>
<?php
unset($hideRequestId, $hideReturnTo, $hideButtonClass, $hideButtonLabel);
?>
