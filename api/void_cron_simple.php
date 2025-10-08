<?php
/**
 * Production web endpoint for cron-job.org
 * Executes void unpaid orders cron job via HTTP
 */

// Security token
$secret_token = 'proware_void_2025_secure';
$provided_token = $_GET['token'] ?? '';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($provided_token !== $secret_token) {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Invalid token']));
}

try {
    $start_time = microtime(true);
    
    // Execute the cron script with output buffering
    ob_start();
    include __DIR__ . '/../cron/void_unpaid_orders.php';
    $cron_output = ob_get_clean();
    
    $execution_time = round((microtime(true) - $start_time) * 1000, 2);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Void cron executed successfully',
        'execution_time_ms' => $execution_time,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>