<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../helpers/service_request.php';
require_once __DIR__ . '/../../models/ServiceDocument.php';

require_role('MEMBER');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed.'); }
require_valid_csrf_token();
$requestId = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
$request = $requestId ? ServiceRequest::find($requestId) : null;
$memberId = ServiceRequest::memberIdForUser((int) current_user_id());
if (!$request || (int) $request['requested_by_member_id'] !== $memberId) { http_response_code(404); exit('Service request not found.'); }

try {
    $documents = upload_service_documents($_FILES['document'] ?? null, (int) current_user_id());
    if ($documents === []) { throw new RuntimeException('Select a document to upload.'); }
    foreach ($documents as $document) {
        ServiceDocument::create($requestId, (int) current_user_id(), $document, trim((string) ($_POST['document_type'] ?? 'SUPPORTING_DOCUMENT')) ?: 'SUPPORTING_DOCUMENT');
    }
    header('Location: view.php?id=' . $requestId . '&document_uploaded=1');
    exit;
} catch (Throwable $exception) {
    error_log('CMMP service document upload failed: ' . $exception->getMessage());
    header('Location: view.php?id=' . $requestId . '&document_error=' . urlencode($exception->getMessage()));
    exit;
}
