<?php
/**
 * Fast-response web endpoint for cron-job.org
 * Triggers background void processing and returns immediately
 */

// Security token
$secret_token = 'proware_void_2025_secure';
$provided_token = $_GET['token'] ?? '';

// Set headers for fast response
header('Content-Type: application/json');
header('Connection: close');

if ($provided_token !== $secret_token) {
    http_response_code(403);
    die('{"status":"error","message":"Invalid token"}');
}

try {
    $trigger_time = date('Y-m-d H:i:s');
    
    // Log the trigger attempt immediately
    $log_entry = "[$trigger_time] [INFO] Cron triggered via web endpoint\n";
    file_put_contents(__DIR__ . '/../cron/void_debug.log', $log_entry, FILE_APPEND | LOCK_EX);
    
    // Immediately return success to cron-job.org
    echo json_encode([
        'status' => 'success',
        'message' => 'Background void process triggered',
        'trigger_time' => $trigger_time,
        'response_time_ms' => round(microtime(true) * 1000, 2)
    ]);
    
    // Flush output to client immediately
    if (ob_get_level()) ob_end_flush();
    flush();
    
    // Close connection to client (cron-job.org gets instant response)
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    
    // Now execute the background void process
    include __DIR__ . '/../cron/void_unpaid_orders.php';
    
} catch (Exception $e) {
    // Log any errors
    $error_log = "[" . date('Y-m-d H:i:s') . "] [ERROR] Web endpoint error: " . $e->getMessage() . "\n";
    file_put_contents(__DIR__ . '/../cron/void_debug.log', $error_log, FILE_APPEND | LOCK_EX);
    
    http_response_code(500);
    echo '{"status":"error","message":"' . addslashes($e->getMessage()) . '"}';
}
?>