<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

try {
    $query = database()->prepare('
        SELECT 
            id, 
            service_type_id, 
            subject, 
            status, 
            preferred_start_datetime, 
            preferred_end_datetime
        FROM service_requests 
        WHERE status = "PENDING"
        ORDER BY id DESC 
        LIMIT 5
    ');
    $query->execute();
    $requests = $query->fetchAll();
    
    echo "✅ Service Requests Found: " . count($requests) . "\n\n";
    
    foreach ($requests as $i => $req) {
        echo ($i + 1) . ". ID: " . $req['id'] . "\n";
        echo "   Service Type ID: " . $req['service_type_id'] . "\n";
        echo "   Subject: " . $req['subject'] . "\n";
        echo "   Status: " . $req['status'] . "\n";
        echo "   Start: " . $req['preferred_start_datetime'] . "\n";
        echo "   End: " . $req['preferred_end_datetime'] . "\n";
        echo "   URL: http://localhost/CMMP/member/services/view.php?id=" . $req['id'] . "\n\n";
    }
    
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
