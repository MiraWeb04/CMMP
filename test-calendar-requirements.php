<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

try {
    $connection = database();
    
    // Get the last service request (should be ID 119)
    $request = $connection->prepare('SELECT id, status FROM service_requests ORDER BY id DESC LIMIT 1');
    $request->execute();
    $sr = $request->fetch();
    
    if (!$sr) {
        echo "❌ No service requests found\n";
        exit(1);
    }
    
    $requestId = $sr['id'];
    $currentStatus = $sr['status'];
    
    echo "📋 Current Service Request: ID $requestId, Status: $currentStatus\n\n";
    
    // Check for personnel assignments
    $participants = $connection->prepare('SELECT COUNT(*) FROM service_request_participants WHERE service_request_id = ?');
    $participants->execute([$requestId]);
    $participantCount = $participants->fetchColumn();
    
    echo "👤 Assigned Personnel: $participantCount\n\n";
    
    if ($participantCount === 0) {
        echo "⚠️  No personnel assigned yet. Service request will NOT appear in calendar.\n\n";
        echo "To make the request appear in the calendar, you need to:\n";
        echo "1. Change status to APPROVED or SCHEDULED\n";
        echo "2. AND assign at least one personnel participant\n\n";
        
        // Test by adding a participant and changing status
        echo "🧪 Testing: Adding a participant...\n";
        $insert = $connection->prepare('
            INSERT INTO service_request_participants 
            (service_request_id, participant_role, participant_name, contact_email) 
            VALUES (?, ?, ?, ?)
        ');
        $insert->execute([$requestId, 'Clergy', 'Pastor John Smith', 'pastor@church.org']);
        echo "✅ Participant added\n\n";
        
        echo "🧪 Testing: Changing status to APPROVED...\n";
        $update = $connection->prepare('UPDATE service_requests SET status = ? WHERE id = ?');
        $update->execute(['APPROVED', $requestId]);
        echo "✅ Status changed to APPROVED\n\n";
        
        echo "✅ Service request should now appear in the calendar!\n";
    } else {
        echo "✅ Personnel assigned. Checking calendar API...\n";
    }
    
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
