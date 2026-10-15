<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

try {
    $connection = database();
    
    // Get the last service request details
    $request = $connection->prepare('SELECT id, status, preferred_start_datetime, preferred_end_datetime FROM service_requests ORDER BY id DESC LIMIT 1');
    $request->execute();
    $sr = $request->fetch();
    
    echo "📋 Service Request Details:\n";
    echo "   ID: " . $sr['id'] . "\n";
    echo "   Status: " . $sr['status'] . "\n";
    echo "   Start: " . $sr['preferred_start_datetime'] . "\n";
    echo "   End: " . $sr['preferred_end_datetime'] . "\n\n";
    
    // Get personnel
    $participants = $connection->prepare('SELECT participant_role, participant_name FROM service_request_participants WHERE service_request_id = ? ORDER BY id');
    $participants->execute([$sr['id']]);
    $rows = $participants->fetchAll();
    
    echo "👤 Personnel Assigned: " . count($rows) . "\n";
    foreach ($rows as $p) {
        echo "   - " . $p['participant_role'] . ": " . $p['participant_name'] . "\n";
    }
    echo "\n";
    
    // Check if it will appear in calendar
    $willAppear = ($sr['status'] === 'APPROVED' || $sr['status'] === 'SCHEDULED') && count($rows) > 0;
    
    if ($willAppear) {
        echo "✅ Service request WILL appear in calendar\n";
        echo "   (Status is " . $sr['status'] . " and has " . count($rows) . " personnel assigned)\n";
    } else {
        echo "❌ Service request will NOT appear in calendar\n";
        if ($sr['status'] !== 'APPROVED' && $sr['status'] !== 'SCHEDULED') {
            echo "   Reason: Status is '" . $sr['status'] . "' (must be APPROVED or SCHEDULED)\n";
        }
        if (count($rows) === 0) {
            echo "   Reason: No personnel assigned yet\n";
        }
    }
    echo "\n";
    
    // Test the calendar API
    echo "🧪 Calendar API Test:\n";
    echo "   URL: api/calendar/availability.php?start=" . substr($sr['preferred_start_datetime'], 0, 10) . "&end=" . date('Y-m-d', strtotime(substr($sr['preferred_end_datetime'], 0, 10) . ' +1 day')) . "\n";
    
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
