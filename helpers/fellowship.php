<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

const FELLOWSHIP_ORGANIZATION_CODES = ['CYF', 'CYAF', 'UCM', 'CWA'];

function fellowship_is_organization_code(string $code): bool
{
    return in_array(strtoupper($code), FELLOWSHIP_ORGANIZATION_CODES, true);
}

function fellowship_member_age(?string $dateOfBirth): ?int
{
    if ($dateOfBirth === null || trim($dateOfBirth) === '') {
        return null;
    }

    $birthDate = DateTimeImmutable::createFromFormat('Y-m-d', $dateOfBirth);
    if (!$birthDate || $birthDate->format('Y-m-d') !== $dateOfBirth) {
        return null;
    }

    $today = new DateTimeImmutable('today');
    if ($birthDate > $today) {
        return null;
    }

    return $birthDate->diff($today)->y;
}

function fellowship_code_for_profile(?string $dateOfBirth, ?string $gender): ?string
{
    $age = fellowship_member_age($dateOfBirth);
    if ($age === null) {
        return null;
    }

    if ($age < 12) {
        return null;
    }

    if ($age <= 29) {
        return 'CYF';
    }

    if ($age <= 49) {
        return 'CYAF';
    }

    $normalizedGender = trim((string) $gender);
    if ($normalizedGender === 'Male') {
        return 'UCM';
    }
    if ($normalizedGender === 'Female') {
        return 'CWA';
    }

    return null;
}

function fellowship_label_for_code(?string $code): string
{
    return match (strtoupper((string) $code)) {
        'CYF' => 'Christian Youth Fellowship (CYF)',
        'CYAF' => 'Christian Young Adult Fellowship (CYAF)',
        'UCM' => 'United Churchmen (UCM)',
        'CWA' => 'Church Women\'s Association (CWA)',
        default => 'Not assigned',
    };
}

function fellowship_generated_profile(int $memberId): array
{
    $bucket = $memberId % 4;
    $gender = in_array($bucket, [0, 2], true) ? 'Male' : 'Female';
    $targetAge = match ($bucket) {
        0 => 22,
        1 => 38,
        2 => 58,
        default => 62,
    };

    $today = new DateTimeImmutable('today');
    $birth = $today->modify(sprintf('-%d years', $targetAge))->modify(sprintf('-%d days', $memberId % 180));

    return [
        'date_of_birth' => $birth->format('Y-m-d'),
        'gender' => $gender,
    ];
}

function fellowship_profile_needs_generation(?string $dateOfBirth, ?string $gender): bool
{
    if ($dateOfBirth === null || trim($dateOfBirth) === '') {
        return true;
    }

    if ($gender === null || trim($gender) === '') {
        return true;
    }

    if (fellowship_member_age($dateOfBirth) === null) {
        return true;
    }

    return fellowship_code_for_profile($dateOfBirth, $gender) === null;
}

/**
 * @return array{date_of_birth: string|null, gender: string|null, generated: bool}
 */
function fellowship_ensure_member_profile(int $memberId): array
{
    $query = database()->prepare(
        'SELECT date_of_birth, gender FROM members WHERE id = :id LIMIT 1'
    );
    $query->execute(['id' => $memberId]);
    $member = $query->fetch();
    if (!$member) {
        throw new RuntimeException('Member not found.');
    }

    return [
        'date_of_birth' => $member['date_of_birth'] !== null ? (string) $member['date_of_birth'] : null,
        'gender' => $member['gender'] !== null ? (string) $member['gender'] : null,
        'generated' => false,
    ];
}

function fellowship_backfill_all_active_members(): int
{
    require_once __DIR__ . '/../models/Ministry.php';

    $members = database()->query(
        'SELECT id, date_of_birth, gender
         FROM members
         WHERE membership_status = "ACTIVE"
         ORDER BY id'
    )->fetchAll();

    $updated = 0;
    foreach ($members as $member) {
        $memberId = (int) $member['id'];
        if (!fellowship_profile_needs_generation($member['date_of_birth'] ?? null, $member['gender'] ?? null)) {
            fellowship_sync_member($memberId, $member['date_of_birth'] ?? null, $member['gender'] ?? null);
            continue;
        }

        $profile = fellowship_ensure_member_profile($memberId);
        if ($profile['generated']) {
            $updated++;
        }
        fellowship_sync_member($memberId, $profile['date_of_birth'], $profile['gender']);
    }

    return $updated;
}

function fellowship_validate_member_profile(array $data, bool $admin): array
{
    return [];
}

function fellowship_ministry_id_for_code(string $code): ?int
{
    $query = database()->prepare(
        'SELECT id FROM ministries WHERE code = :code AND ministry_status = "ACTIVE" LIMIT 1'
    );
    $query->execute(['code' => strtoupper($code)]);
    $id = $query->fetchColumn();

    return $id === false ? null : (int) $id;
}

function fellowship_active_code_for_member(int $memberId): ?string
{
    $query = database()->prepare(
        'SELECT m.code
         FROM ministry_members mm
         INNER JOIN ministries m ON m.id = mm.ministry_id
         WHERE mm.member_id = :member_id
           AND mm.left_at IS NULL
           AND m.code IN ("CYF", "CYAF", "UCM", "CWA")
         LIMIT 1'
    );
    $query->execute(['member_id' => $memberId]);
    $code = $query->fetchColumn();

    return $code === false ? null : (string) $code;
}

function fellowship_sync_member(int $memberId, ?string $dateOfBirth, ?string $gender): void
{
    require_once __DIR__ . '/../models/Ministry.php';

    $profile = fellowship_ensure_member_profile($memberId);
    $dateOfBirth = $dateOfBirth ?: $profile['date_of_birth'];
    $gender = $gender ?: $profile['gender'];

    $targetCode = fellowship_code_for_profile($dateOfBirth, $gender);
    $currentCode = fellowship_active_code_for_member($memberId);

    if ($targetCode === $currentCode) {
        return;
    }

    if ($currentCode !== null) {
        $currentMinistryId = fellowship_ministry_id_for_code($currentCode);
        if ($currentMinistryId !== null) {
            Ministry::removeMember($currentMinistryId, $memberId);
        }
    }

    if ($targetCode === null) {
        return;
    }

    $targetMinistryId = fellowship_ministry_id_for_code($targetCode);
    if ($targetMinistryId === null) {
        throw new RuntimeException('Fellowship organization records are missing. Run the latest database migration.');
    }

    Ministry::addMember($targetMinistryId, $memberId, 'Member', true);
}

function fellowship_assert_member_eligible(int $memberId, string $organizationCode): void
{
    $memberQuery = database()->prepare(
        'SELECT date_of_birth, gender FROM members WHERE id = :id LIMIT 1'
    );
    $memberQuery->execute(['id' => $memberId]);
    $member = $memberQuery->fetch();
    if (!$member) {
        throw new InvalidArgumentException('Member not found.');
    }

    $profile = fellowship_ensure_member_profile($memberId);
    $expectedCode = fellowship_code_for_profile($profile['date_of_birth'], $profile['gender']);
    if ($expectedCode === null) {
        throw new InvalidArgumentException('Fellowship organization could not be determined for this member.');
    }
    if (strtoupper($organizationCode) !== $expectedCode) {
        throw new InvalidArgumentException(
            'This member belongs in ' . fellowship_label_for_code($expectedCode) . '. Fellowship transfers between organizations are not allowed.'
        );
    }

    $existingCode = fellowship_active_code_for_member($memberId);
    if ($existingCode !== null && strtoupper($organizationCode) !== $existingCode) {
        throw new InvalidArgumentException(
            'This member is already assigned to ' . fellowship_label_for_code($existingCode) . '. Remove that assignment through profile updates instead of transferring organizations.'
        );
    }
}
