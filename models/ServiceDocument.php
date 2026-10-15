<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Notification.php';
require_once __DIR__ . '/AuditLog.php';

final class ServiceDocument
{
    public static function find(int $id): ?array
    {
        $query = database()->prepare(
            'SELECT sd.*, sr.requested_by_member_id, m.user_id AS member_user_id
             FROM service_documents sd
             INNER JOIN service_requests sr ON sr.id = sd.service_request_id
             INNER JOIN members m ON m.id = sr.requested_by_member_id
             WHERE sd.id = :id LIMIT 1'
        );
        $query->execute(['id' => $id]);
        $document = $query->fetch();
        return $document ?: null;
    }

    public static function create(int $requestId, ?int $userId, array $file, string $documentType = 'SUPPORTING_DOCUMENT'): int
    {
        $query = database()->prepare(
            'INSERT INTO service_documents
                (service_request_id, uploaded_by_user_id, document_type, original_filename,
                 stored_filename, storage_path, mime_type, file_size_bytes, is_private, document_status)
             VALUES (:request_id, :user_id, :document_type, :original_filename,
                     :stored_filename, :storage_path, :mime_type, :file_size_bytes, 1, "PENDING")'
        );
        $query->execute([
            'request_id' => $requestId,
            'user_id' => $userId,
            'document_type' => $documentType,
            'original_filename' => $file['original_filename'],
            'stored_filename' => $file['stored_filename'],
            'storage_path' => $file['storage_path'],
            'mime_type' => $file['mime_type'],
            'file_size_bytes' => $file['file_size_bytes'],
        ]);
        return (int) database()->lastInsertId();
    }

    public static function review(int $id, string $status, ?string $reason, ?int $userId): void
    {
        if (!in_array($status, ['VERIFIED', 'REJECTED'], true)) {
            throw new InvalidArgumentException('Invalid document status.');
        }
        if ($status === 'REJECTED' && trim((string) $reason) === '') {
            throw new InvalidArgumentException('A rejection reason is required.');
        }
        $query = database()->prepare(
            'UPDATE service_documents
             SET document_status = :status, rejection_reason = :reason,
                 reviewed_by_user_id = :user_id, reviewed_at = NOW()
             WHERE id = :id'
        );
        $query->execute(['status' => $status, 'reason' => $status === 'REJECTED' ? trim((string) $reason) : null, 'user_id' => $userId, 'id' => $id]);
        if ($query->rowCount() !== 1) {
            throw new RuntimeException('Document not found or unchanged.');
        }
        $owner = self::find($id);
        if ($owner) Notification::notifyMembersByMemberId((int) $owner['requested_by_member_id'], $status === 'VERIFIED' ? 'Document verified' : 'Document rejected', $status === 'VERIFIED' ? 'Your service document was verified.' : (string) $reason, $status === 'VERIFIED' ? 'DOCUMENT_VERIFIED' : 'DOCUMENT_REJECTED', 'service_document', $id);
        if ($status === 'VERIFIED') AuditLog::record($userId, 'DOCUMENT_VERIFIED', 'SERVICE_DOCUMENT', $id);
    }
}
