<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/ServiceDocument.php';
require_once __DIR__ . '/../models/ServiceRequest.php';

$connection = database();
$suffix = strtoupper(bin2hex(random_bytes(4)));
$memberId = null;
$requestId = null;
$documentIds = [];
$storedFiles = [];

try {
    $memberInsert = $connection->prepare('INSERT INTO members (member_number, first_name, last_name, membership_status) VALUES (:number, :first_name, :last_name, :status)');
    $memberInsert->execute(['number' => 'DOC-' . $suffix, 'first_name' => 'Document', 'last_name' => 'Tester', 'status' => 'ACTIVE']);
    $memberId = (int) $connection->lastInsertId();
    $typeId = (int) $connection->query("SELECT id FROM service_types WHERE code = 'BAPTISM' LIMIT 1")->fetchColumn();
    $requestId = ServiceRequest::create([
        'service_type_id' => $typeId,
        'requested_by_member_id' => $memberId,
        'request_number' => 'DOC-REQ-' . $suffix,
        'subject' => 'Document test request',
        'description' => null,
        'request_data' => '{}',
        'preferred_start_datetime' => date('Y-m-d H:i:s', time() + 86400),
        'preferred_end_datetime' => date('Y-m-d H:i:s', time() + 90000),
        'preferred_facility_id' => null,
        'requested_by_member_id_user' => null,
    ]);

    $directory = 'C:\\xampp\\htdocs\\CMMP\\uploads\\service-documents';
    if (!is_dir($directory)) { mkdir($directory, 0750, true); }
    foreach (['first.pdf', 'replacement.pdf'] as $name) {
        $stored = 'smoke-' . $suffix . '-' . $name;
        $path = $directory . DIRECTORY_SEPARATOR . $stored;
        file_put_contents($path, '%PDF-test');
        $storedFiles[] = $path;
        $documentIds[] = ServiceDocument::create($requestId, null, [
            'original_filename' => $name,
            'stored_filename' => $stored,
            'storage_path' => 'uploads/service-documents/' . $stored,
            'mime_type' => 'application/pdf',
            'file_size_bytes' => filesize($path),
        ]);
    }

    if (ServiceDocument::find($documentIds[0])['document_status'] !== 'PENDING') {
        throw new RuntimeException('Document did not start as pending.');
    }
    ServiceDocument::review($documentIds[0], 'REJECTED', 'Image is not legible.', null);
    $rejected = ServiceDocument::find($documentIds[0]);
    if ($rejected['document_status'] !== 'REJECTED' || $rejected['rejection_reason'] !== 'Image is not legible.') {
        throw new RuntimeException('Document rejection failed.');
    }
    ServiceDocument::review($documentIds[1], 'VERIFIED', null, null);
    if (ServiceDocument::find($documentIds[1])['document_status'] !== 'VERIFIED') {
        throw new RuntimeException('Document verification failed.');
    }

    echo "Document upload, verification, rejection, and replacement smoke test passed.\n";
} finally {
    foreach ($storedFiles as $file) { if (is_file($file)) { unlink($file); } }
    if ($requestId !== null) { $connection->prepare('DELETE FROM service_requests WHERE id = :id')->execute(['id' => $requestId]); }
    if ($memberId !== null) { $connection->prepare('DELETE FROM members WHERE id = :id')->execute(['id' => $memberId]); }
}
