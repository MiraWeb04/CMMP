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
    
    echo "🧪 Approving Service Request #$requestId...\n\n";
    
    // Update status to APPROVED
    $update = $connection->prepare('UPDATE service_requests SET status = ? WHERE id = ?');
    $update->execute(['APPROVED', $requestId]);
    
    echo "✅ Service Request #$requestId is now APPROVED\n\n";
    
    // Check status
    $check = $connection->prepare('SELECT status FROM service_requests WHERE id = ?');
    $check->execute([$requestId]);
    $status = $check->fetchColumn();
    
    echo "Current Status: $status\n\n";
    echo "✅ Service request should now appear in the Church calendar!\n";
    
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
