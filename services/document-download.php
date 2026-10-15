<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../models/ServiceDocument.php';

require_login();
$documentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$document = $documentId ? ServiceDocument::find($documentId) : null;
if (!$document) { http_response_code(404); exit('Document not found.'); }
$isAdmin = has_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);
$isOwner = (int) ($document['member_user_id'] ?? 0) === (int) current_user_id();
if (!$isAdmin && !$isOwner) { http_response_code(403); exit('Forbidden'); }

$baseDirectory = realpath(__DIR__ . '/../uploads/service-documents');
$filePath = realpath(__DIR__ . '/../' . ltrim($document['storage_path'], '/\\'));
if (!$baseDirectory || !$filePath || !str_starts_with($filePath, $baseDirectory . DIRECTORY_SEPARATOR) || !is_file($filePath)) {
    http_response_code(404);
    exit('Document file not found.');
}

header('Content-Type: ' . $document['mime_type']);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: attachment; filename="' . rawurlencode(basename($document['original_filename'])) . '"');
readfile($filePath);
exit;
