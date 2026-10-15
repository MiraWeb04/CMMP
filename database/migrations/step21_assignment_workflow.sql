ALTER TABLE service_requests
    MODIFY COLUMN status ENUM(
        'PENDING',
        'PENDING_ASSIGNMENT',
        'UNDER_REVIEW',
        'DOCUMENTS_REQUIRED',
        'ASSIGNED',
        'APPROVED',
        'SCHEDULED',
        'CONFIRMED',
        'RESCHEDULE_REQUIRED',
        'COMPLETED',
        'REJECTED',
        'CANCELLED'
    ) NOT NULL DEFAULT 'PENDING_ASSIGNMENT';

UPDATE service_requests
SET status = 'PENDING_ASSIGNMENT'
WHERE status = 'PENDING';
