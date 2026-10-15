<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/Facility.php';

$connection = database();
$suffix = strtoupper(bin2hex(random_bytes(4)));
$facilityId = null;

try {
    $facilityId = Facility::create([
        'name' => 'Step 9 Test Facility ' . $suffix,
        'code' => 'FAC-' . $suffix,
        'description' => 'Temporary facility CRUD test record.',
        'location' => 'Test campus',
        'capacity' => 120,
        'facility_type' => 'CHAPEL',
        'facility_status' => 'AVAILABLE',
    ]);

    $facility = Facility::find($facilityId);
    if (!$facility || $facility['code'] !== 'FAC-' . $suffix || (int) $facility['capacity'] !== 120) {
        throw new RuntimeException('Facility creation/read failed.');
    }
    if ($facility['appointment_count'] !== 0) {
        throw new RuntimeException('Unexpected facility appointments found.');
    }

    Facility::update($facilityId, [
        'name' => 'Updated Facility ' . $suffix,
        'description' => 'Updated description.',
        'location' => 'Updated campus',
        'capacity' => 200,
        'facility_type' => 'SANCTUARY',
    ]);
    $updated = Facility::find($facilityId);
    if ($updated['name'] !== 'Updated Facility ' . $suffix || (int) $updated['capacity'] !== 200 || $updated['facility_type'] !== 'SANCTUARY') {
        throw new RuntimeException('Facility update failed.');
    }

    Facility::setStatus($facilityId, 'UNAVAILABLE');
    $deactivated = Facility::find($facilityId);
    if ($deactivated['facility_status'] !== 'UNAVAILABLE') {
        throw new RuntimeException('Facility status update failed.');
    }

    $facilityColumn = $connection->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'cmmp_db' AND table_name = 'appointments' AND column_name = 'facility_id'")->fetchColumn();
    if ((int) $facilityColumn !== 1) {
        throw new RuntimeException('Centralized appointment facility relationship is missing.');
    }

    echo "Facility CRUD smoke test passed.\n";
} finally {
    if ($facilityId !== null) {
        $delete = $connection->prepare('DELETE FROM facilities WHERE id = :id');
        $delete->execute(['id' => $facilityId]);
    }
}
