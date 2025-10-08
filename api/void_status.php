<?php
/**
 * Status monitoring endpoint for void cron job
 * Shows recent execution logs and performance metrics
 */

// Security token
$secret_token = 'proware_void_2025_secure';
$provided_token = $_GET['token'] ?? '';

header('Content-Type: application/json');

if ($provided_token !== $secret_token) {
    http_response_code(403);
    die('{"status":"error","message":"Invalid token"}');
}

try {
    $log_file = __DIR__ . '/../cron/void_debug.log';
    
    if (!file_exists($log_file)) {
        echo json_encode([
            'status' => 'info',
            'message' => 'No log file found yet',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    // Get last 20 lines of log
    $lines = array_slice(file($log_file, FILE_IGNORE_NEW_LINES), -20);
    
    // Count recent activities (last 1 hour)
    $recent_triggers = 0;
    $recent_voids = 0;
    $hour_ago = strtotime('-1 hour');
    
    foreach ($lines as $line) {
        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
            $log_time = strtotime($matches[1]);
            if ($log_time >= $hour_ago) {
                if (strpos($line, 'triggered via web endpoint') !== false) {
                    $recent_triggers++;
                }
                if (strpos($line, 'Voided order') !== false) {
                    $recent_voids++;
                }
            }
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Void cron status retrieved',
        'timestamp' => date('Y-m-d H:i:s'),
        'stats' => [
            'recent_triggers_1h' => $recent_triggers,
            'recent_voids_1h' => $recent_voids,
            'log_file_size' => filesize($log_file),
            'last_modified' => date('Y-m-d H:i:s', filemtime($log_file))
        ],
        'recent_logs' => array_slice($lines, -10) // Last 10 log entries
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