<?php
declare(strict_types=1);

$memberChoices = $memberChoices ?? [];
$memberRows = $memberRows ?? [['member_id' => 0, 'role' => '']];
$memberOption = static function (array $member, int $selectedId): string {
    $id = (int) $member['id'];
    $label = $member['member_number'] . ' - ' . $member['first_name'] . ' ' . $member['last_name'];
    if (trim((string) ($member['other_ministries'] ?? '')) !== '') {
        $label .= ' (Already in ' . $member['other_ministries'] . ')';
    }
    $selected = $id === $selectedId ? ' selected' : '';
    return '<option value="' . $id . '"' . $selected . '>' . e($label) . '</option>';
};
?>
<div class="mb-3">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
        <label class="form-label mb-0">Members</label>
        <button class="btn btn-sm btn-outline-primary" id="add-ministry-member" type="button" <?= $memberChoices === [] ? 'disabled' : '' ?>>Add member</button>
    </div>
    <div class="form-text mb-2">Add every church member who belongs to this ministry. Use Add member for each additional person.</div>
    <div id="ministry-member-rows">
        <?php foreach ($memberRows as $row): ?>
            <div class="row g-2 align-items-center mb-2 ministry-member-row">
                <div class="col-md-7">
                    <select class="form-select" name="member_ids[]" <?= $memberChoices === [] ? 'disabled' : '' ?>>
                        <option value="">Select a church member</option>
                        <?php foreach ($memberChoices as $member): ?>
                            <?= $memberOption($member, (int) $row['member_id']) ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <input class="form-control" name="member_roles[]" placeholder="Member" value="<?= e($row['role']) ?>">
                </div>
                <div class="col-md-1">
                    <button class="btn btn-outline-danger w-100 remove-ministry-member" type="button" aria-label="Remove member">×</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if ($memberChoices === []): ?>
        <p class="text-muted small mb-0">Every active church member is already in this ministry.</p>
    <?php endif; ?>
</div>
<template id="ministry-member-template">
    <div class="row g-2 align-items-center mb-2 ministry-member-row">
        <div class="col-md-7">
            <select class="form-select" name="member_ids[]">
                <option value="">Select a church member</option>
                <?php foreach ($memberChoices as $member): ?>
                    <?= $memberOption($member, 0) ?>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <input class="form-control" name="member_roles[]" placeholder="Member">
        </div>
        <div class="col-md-1">
            <button class="btn btn-outline-danger w-100 remove-ministry-member" type="button" aria-label="Remove member">×</button>
        </div>
    </div>
</template>
<script>
document.getElementById('add-ministry-member')?.addEventListener('click', function () {
    const rows = document.getElementById('ministry-member-rows');
    const template = document.getElementById('ministry-member-template');
    if (!rows || !template) {
        return;
    }
    rows.appendChild(template.content.cloneNode(true));
});
document.getElementById('ministry-member-rows')?.addEventListener('click', function (event) {
    const button = event.target.closest('.remove-ministry-member');
    if (!button) {
        return;
    }
    const row = button.closest('.ministry-member-row');
    const rows = document.querySelectorAll('#ministry-member-rows .ministry-member-row');
    if (rows.length <= 1) {
        const select = row.querySelector('select');
        const input = row.querySelector('input');
        if (select) {
            select.value = '';
        }
        if (input) {
            input.value = '';
        }
        return;
    }
    row.remove();
});
</script>
