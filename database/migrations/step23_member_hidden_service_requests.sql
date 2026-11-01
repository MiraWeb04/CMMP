ALTER TABLE service_requests
    ADD COLUMN IF NOT EXISTS hidden_from_member_at DATETIME NULL AFTER completed_at,
    ADD INDEX IF NOT EXISTS idx_service_requests_member_hidden (requested_by_member_id, hidden_from_member_at);
