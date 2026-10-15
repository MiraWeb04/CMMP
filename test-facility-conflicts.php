<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

try {
    // Clean up test data
    $connection = database();
    $connection->beginTransaction();
    
    $requestIds = $connection->query('SELECT id FROM service_requests')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($requestIds as $id) {
        $connection->prepare('DELETE FROM service_documents WHERE service_request_id = ?')->execute([$id]);
        $connection->prepare('DELETE FROM service_request_participants WHERE service_request_id = ?')->execute([$id]);
        $connection->prepare('DELETE FROM service_status_history WHERE service_request_id = ?')->execute([$id]);
    }
    $connection->query('DELETE FROM service_requests');
    $connection->query('DELETE FROM events');
    $connection->query('DELETE FROM appointments');
    $connection->query('DELETE FROM scheduling_resource_locks');
    
    $connection->commit();
    
    echo "✅ Database cleaned\n\n";
    
    echo "🧪 Test Scenario:\n";
    echo "1. Create a funeral service request (no venue)\n";
    echo "2. Try to create an event at Main Hall\n";
    echo "3. Should NOT conflict - both should be allowed\n\n";
    
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
