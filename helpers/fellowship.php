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

function fellowship_validate_member_profile(array $data, bool $admin): array
{
    $errors = [];
    if (!$admin) {
        return $errors;
    }

    $status = (string) ($data['membership_status'] ?? 'ACTIVE');
    if ($status !== 'ACTIVE') {
        return $errors;
    }

    $dateOfBirth = trim((string) ($data['date_of_birth'] ?? ''));
    $gender = trim((string) ($data['gender'] ?? ''));
    if ($dateOfBirth === '') {
        $errors[] = 'Date of birth is required before an active member can be assigned to a fellowship organization.';
        return $errors;
    }

    if ($gender === '') {
        $errors[] = 'Gender is required before an active member can be assigned to a fellowship organization.';
        return $errors;
    }

    $age = fellowship_member_age($dateOfBirth);
    if ($age === null) {
        $errors[] = 'Enter a valid date of birth for fellowship organization assignment.';
        return $errors;
    }

    if ($age >= 12 && fellowship_code_for_profile($dateOfBirth, $gender) === null) {
        $errors[] = 'Select Male or Female for members aged 50 and above so the system can assign UCM or CWA.';
    }

    return $errors;
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

    $expectedCode = fellowship_code_for_profile(
        $member['date_of_birth'] ?? null,
        $member['gender'] ?? null
    );
    if ($expectedCode === null) {
        throw new InvalidArgumentException('Complete date of birth and gender before assigning a fellowship organization.');
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
