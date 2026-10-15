-- Remove Baptism and Child Dedication from active service types
-- These services are no longer available for request submission

UPDATE service_types SET is_active = 0 WHERE code IN ('BAPTISM', 'CHILD_DEDICATION');
