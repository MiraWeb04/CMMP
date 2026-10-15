<?php
declare(strict_types=1);

function ministry_posted_members(array $post, array $activeMembers): array
{
    $allowed = [];
    foreach ($activeMembers as $member) {
        $allowed[(int) $member['id']] = true;
    }

    $ids = $post['member_ids'] ?? [];
    $roles = $post['member_roles'] ?? [];
    if (!is_array($ids)) {
        $ids = [];
    }
    if (!is_array($roles)) {
        $roles = [];
    }

    $rows = [];
    $selected = [];
    foreach ($ids as $index => $rawId) {
        $memberId = (int) $rawId;
        $role = trim((string) ($roles[$index] ?? ''));
        $rows[] = ['member_id' => $memberId, 'role' => $role];
        if ($memberId <= 0) {
            continue;
        }
        if (!isset($allowed[$memberId])) {
            throw new InvalidArgumentException('Select an active church member.');
        }
        if (isset($selected[$memberId])) {
            throw new InvalidArgumentException('Each member can only be added once.');
        }
        $selected[$memberId] = $role !== '' ? $role : 'Member';
    }

    return [
        'rows' => $rows === [] ? [['member_id' => 0, 'role' => '']] : $rows,
        'selected' => $selected,
    ];
}
