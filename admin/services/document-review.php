<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/csrf.php';
require_once __DIR__ . '/../../models/ServiceDocument.php';
require_once __DIR__ . '/../../models/ServiceRequest.php';

require_once __DIR__ . '/../../helpers/service_request.php';
require_role(SERVICE_REVIEW_ROLES);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed.'); }
require_valid_csrf_token();
$documentId = filter_input(INPUT_POST, 'document_id', FILTER_VALIDATE_INT);
$status = (string) ($_POST['document_status'] ?? '');
$document = $documentId ? ServiceDocument::find($documentId) : null;
if (!$document) { http_response_code(404); exit('Document not found.'); }
try {
    ServiceDocument::review($documentId, $status, $_POST['rejection_reason'] ?? null, (int) current_user_id());
    if ($status === 'REJECTED') {
        ServiceRequest::updateStatus((int) $document['service_request_id'], 'DOCUMENTS_REQUIRED', (int) current_user_id(), trim((string) ($_POST['rejection_reason'] ?? '')));
    } else {
        $pendingDocuments = database()->prepare('SELECT COUNT(*) FROM service_documents WHERE service_request_id = :request_id AND document_status <> "VERIFIED"');
        $pendingDocuments->execute(['request_id' => (int) $document['service_request_id']]);
        if ((int) $pendingDocuments->fetchColumn() === 0) {
            ServiceRequest::updateStatus((int) $document['service_request_id'], 'APPROVED', (int) current_user_id(), 'All submitted service documents were verified.');
        }
    }
    header('Location: view.php?id=' . (int) $document['service_request_id'] . '&document_reviewed=1');
    exit;
} catch (Throwable $exception) {
    header('Location: view.php?id=' . (int) $document['service_request_id'] . '&document_error=' . urlencode($exception->getMessage()));
    exit;
}
