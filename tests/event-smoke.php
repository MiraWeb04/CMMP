<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/Event.php';

$connection = database();
$suffix = strtoupper(bin2hex(random_bytes(4)));
$eventId = null;
$facilityId = null;
$memberId = null;

try {
    $categoryId = $connection->query("SELECT id FROM event_categories WHERE is_active = 1 ORDER BY id LIMIT 1")->fetchColumn();
    if (!$categoryId) {
        throw new RuntimeException('No event category is configured.');
    }

    $facilityInsert = $connection->prepare(
        'INSERT INTO facilities (name, code, facility_type, facility_status) VALUES (:name, :code, :type, :status)'
    );
    $facilityInsert->execute([
        'name' => 'Step 10 Facility ' . $suffix,
        'code' => 'EVT-' . $suffix,
        'type' => 'CHAPEL',
        'status' => 'AVAILABLE',
    ]);
    $facilityId = (int) $connection->lastInsertId();

    $memberInsert = $connection->prepare(
        'INSERT INTO members (member_number, first_name, last_name, membership_status)
         VALUES (:number, :first_name, :last_name, :status)'
    );
    $memberInsert->execute([
        'number' => 'EVENT-' . $suffix,
        'first_name' => 'Event',
        'last_name' => 'Tester',
        'status' => 'ACTIVE',
    ]);
    $memberId = (int) $connection->lastInsertId();

    $eventId = Event::create([
        'created_by_user_id' => null,
        'category_id' => (int) $categoryId,
        'facility_id' => $facilityId,
        'title' => 'Step 10 Test Event ' . $suffix,
        'description' => 'Temporary event CRUD test record.',
        'start_datetime' => date('Y-m-d H:i:s', time() + 86400),
        'end_datetime' => date('Y-m-d H:i:s', time() + 90000),
        'location' => null,
        'capacity' => 1,
        'registration_required' => 1,
        'registration_deadline' => date('Y-m-d H:i:s', time() + 43200),
        'event_image' => null,
        'event_status' => 'PUBLISHED',
    ]);

    $event = Event::find($eventId, $memberId);
    if (!$event || $event['facility_id'] != $facilityId || $event['event_status'] !== 'PUBLISHED') {
        throw new RuntimeException('Event creation/read failed.');
    }

    Event::register($eventId, $memberId);
    $registered = Event::find($eventId, $memberId);
    if ($registered['own_registration_status'] !== 'REGISTERED' || (int) $registered['registration_count'] !== 1) {
        throw new RuntimeException('Event registration failed.');
    }

    try {
        Event::register($eventId, $memberId);
        throw new RuntimeException('Duplicate registration was accepted.');
    } catch (DomainException $expected) {
    }

    Event::cancelRegistration($eventId, $memberId);
    $cancelled = Event::find($eventId, $memberId);
    if ($cancelled['own_registration_status'] !== 'CANCELLED') {
        throw new RuntimeException('Registration cancellation failed.');
    }

    $deadlineEventId = Event::create([
        'created_by_user_id' => null,
        'category_id' => (int) $categoryId,
        'facility_id' => $facilityId,
        'title' => 'Step 10 Deadline Test ' . $suffix,
        'description' => null,
        'start_datetime' => date('Y-m-d H:i:s', time() + 172800),
        'end_datetime' => date('Y-m-d H:i:s', time() + 176400),
        'location' => null,
        'capacity' => 1,
        'registration_required' => 1,
        'registration_deadline' => date('Y-m-d H:i:s', time() - 60),
        'event_image' => null,
        'event_status' => 'PUBLISHED',
    ]);
    try {
        Event::register($deadlineEventId, $memberId);
        throw new RuntimeException('Registration after deadline was accepted.');
    } catch (DomainException $expected) {
    }
    $deleteDeadline = $connection->prepare('DELETE FROM events WHERE id = :id');
    $deleteDeadline->execute(['id' => $deadlineEventId]);

    Event::setStatus($eventId, 'CANCELLED');
    try {
        Event::register($eventId, $memberId);
        throw new RuntimeException('Registration for cancelled event was accepted.');
    } catch (DomainException $expected) {
    }

    echo "Event creation and registration smoke test passed.\n";
} finally {
    if ($eventId !== null) {
        $deleteRegistrations = $connection->prepare('DELETE FROM event_registrations WHERE event_id = :event_id');
        $deleteRegistrations->execute(['event_id' => $eventId]);
        $deleteEvent = $connection->prepare('DELETE FROM events WHERE id = :id');
        $deleteEvent->execute(['id' => $eventId]);
    }
    if ($memberId !== null) {
        $deleteMember = $connection->prepare('DELETE FROM members WHERE id = :id');
        $deleteMember->execute(['id' => $memberId]);
    }
    if ($facilityId !== null) {
        $deleteFacility = $connection->prepare('DELETE FROM facilities WHERE id = :id');
        $deleteFacility->execute(['id' => $facilityId]);
    }
}
