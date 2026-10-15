USE cmmp_db;

ALTER TABLE service_documents
    ADD COLUMN IF NOT EXISTS document_status ENUM('PENDING', 'VERIFIED', 'REJECTED') NOT NULL DEFAULT 'PENDING' AFTER is_private,
    ADD COLUMN IF NOT EXISTS rejection_reason VARCHAR(500) NULL AFTER document_status,
    ADD COLUMN IF NOT EXISTS reviewed_by_user_id BIGINT UNSIGNED NULL AFTER rejection_reason,
    ADD COLUMN IF NOT EXISTS reviewed_at DATETIME NULL AFTER reviewed_by_user_id;

ALTER TABLE service_documents
    ADD INDEX IF NOT EXISTS idx_service_documents_status (document_status),
    ADD CONSTRAINT fk_service_documents_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE;