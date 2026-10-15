<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

try {
    $connection = database();
    
    // Get the last service request
    $request = $connection->prepare('SELECT id FROM service_requests ORDER BY id DESC LIMIT 1');
    $request->execute();
    $sr = $request->fetch();
    $requestId = $sr['id'];
    
    // Get personnel count before deletion
    $beforeCheck = $connection->prepare('SELECT COUNT(*) FROM service_request_participants WHERE service_request_id = ?');
    $beforeCheck->execute([$requestId]);
    $beforeCount = $beforeCheck->fetchColumn();
    
    echo "🧪 Testing: Remove all personnel assignments\n";
    echo "   Before: $beforeCount personnel assigned\n\n";
    
    // Delete all participants
    $delete = $connection->prepare('DELETE FROM service_request_participants WHERE service_request_id = ?');
    $delete->execute([$requestId]);
    
    echo "✅ All personnel removed\n\n";
    
    // Check count after deletion
    $afterCheck = $connection->prepare('SELECT COUNT(*) FROM service_request_participants WHERE service_request_id = ?');
    $afterCheck->execute([$requestId]);
    $afterCount = $afterCheck->fetchColumn();
    
    echo "   After: $afterCount personnel assigned\n\n";
    
    // Get status
    $status = $connection->prepare('SELECT status FROM service_requests WHERE id = ?');
    $status->execute([$requestId]);
    $currentStatus = $status->fetchColumn();
    
    echo "📋 Service Request Status: $currentStatus\n\n";
    
    echo "❌ Service request will now DISAPPEAR from calendar\n";
    echo "   (Even though status is APPROVED, no personnel are assigned)\n";
    
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
