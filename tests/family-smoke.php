<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/Family.php';

$connection = database();
$suffix = strtoupper(bin2hex(random_bytes(4)));
$familyId = null;
$memberIds = [];

try {
    $familyData = [
        'family_code' => 'FAM-' . $suffix,
        'family_name' => 'Step 8 Test Family',
        'address_line_1' => 'Family Street',
        'address_line_2' => null,
        'city' => 'Manila',
        'state_province' => null,
        'postal_code' => '1000',
        'country' => 'Philippines',
    ];
    $familyId = Family::create($familyData);

    $insertMember = $connection->prepare(
        'INSERT INTO members (member_number, first_name, last_name, membership_status)
         VALUES (:member_number, :first_name, :last_name, :status)'
    );
    foreach ([['Juan', 'Dela Cruz', 'Father'], ['Maria', 'Dela Cruz', 'Mother']] as $index => [$firstName, $lastName, $relationship]) {
        $insertMember->execute([
            'member_number' => 'FAMTEST-' . $suffix . '-' . $index,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'status' => 'ACTIVE',
        ]);
        $memberId = (int) $connection->lastInsertId();
        $memberIds[] = $memberId;
        Family::addMember($familyId, $memberId, $relationship);
    }

    Family::setPrimaryContact($familyId, $memberIds[0]);
    $family = Family::find($familyId);
    if (!$family || count($family['members']) !== 2 || (int) $family['members'][0]['id'] !== $memberIds[0] || !(bool) $family['members'][0]['is_primary_contact']) {
        throw new RuntimeException('Family/member relationship or primary contact failed.');
    }

    Family::update($familyId, ['family_name' => 'Updated Test Family']);
    if (Family::find($familyId)['family_name'] !== 'Updated Test Family') {
        throw new RuntimeException('Family update failed.');
    }

    Family::removeMember($familyId, $memberIds[1]);
    if (count(Family::find($familyId)['members']) !== 1) {
        throw new RuntimeException('Family member removal failed.');
    }

    $searchResult = Family::paginate('101', 1, 15);
    if (!is_array($searchResult) || !isset($searchResult['items'], $searchResult['total'])) {
        throw new RuntimeException('Family paginate search result shape failed.');
    }

    echo "Family relationship smoke test passed.\n";
} finally {
    if ($familyId !== null) {
        $deleteFamily = $connection->prepare('DELETE FROM families WHERE id = :id');
        $deleteFamily->execute(['id' => $familyId]);
    }
    foreach ($memberIds as $memberId) {
        $deleteMember = $connection->prepare('DELETE FROM members WHERE id = :id');
        $deleteMember->execute(['id' => $memberId]);
    }
}
