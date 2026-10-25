<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../models/Personnel.php';
require_once __DIR__ . '/../models/ServiceRequest.php';

function pastor_only_without_staff(): bool
{
    return has_role('PASTOR') && !has_role(['SUPER_ADMIN', 'CHURCH_ADMIN', 'STAFF']);
}

function current_pastor_staff_id(): ?int
{
    if (!pastor_only_without_staff()) {
        return null;
    }

    $person = Personnel::findForUser((int) current_user_id());
    if (!$person || ($person['personnel_type'] ?? '') !== 'PASTOR') {
        return null;
    }

    return (int) $person['id'];
}

function require_assigned_service_request(int $requestId): void
{
    $staffId = current_pastor_staff_id();
    if ($staffId === null) {
        return;
    }

    if (!ServiceRequest::isAssignedToStaff($requestId, $staffId)) {
        http_response_code(403);
        exit('You can only open service requests assigned to you.');
    }
}
