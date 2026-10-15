<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

try {
    $connection = database();
    $connection->beginTransaction();
    
    // Get all request IDs first to cascade delete related data
    $requestIds = $connection->query('SELECT id FROM service_requests')->fetchAll(PDO::FETCH_COLUMN);
    
    // Delete service request related data
    foreach ($requestIds as $requestId) {
        // Delete documents
        $connection->prepare('DELETE FROM service_documents WHERE service_request_id = ?')->execute([$requestId]);
        
        // Delete participants
        $connection->prepare('DELETE FROM service_request_participants WHERE service_request_id = ?')->execute([$requestId]);
        
        // Delete status history
        $connection->prepare('DELETE FROM service_status_history WHERE service_request_id = ?')->execute([$requestId]);
    }
    
    // Delete all service requests
    $connection->query('DELETE FROM service_requests');
    
    // Delete all events
    $connection->query('DELETE FROM events');
    
    // Delete all ministry activities
    $connection->query('DELETE FROM ministry_activities');
    
    // Delete all appointments
    $connection->query('DELETE FROM appointments');
    
    // Delete all calendar locks
    $connection->query('DELETE FROM scheduling_resource_locks');
    
    $connection->commit();
    
    echo "✅ All test data cleaned up successfully!\n";
    echo "   - Service requests: Deleted\n";
    echo "   - Events: Deleted\n";
    echo "   - Ministry activities: Deleted\n";
    echo "   - Appointments: Deleted\n";
    echo "   - Calendar locks: Deleted\n";
    echo "\n✅ Database ready for testing!\n";
    
} catch (Throwable $exception) {
    echo "❌ Error cleaning up test data: " . $exception->getMessage() . "\n";
    exit(1);
}
