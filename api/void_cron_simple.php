<?php
/**
 * Simple Web Endpoint for Manual Testing
 * Use this for testing while Heroku Scheduler handles automatic execution
 */

$secret_token = 'proware_void_2025_secure';
$provided_token = $_GET['token'] ?? '';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Validate token
if ($provided_token !== $secret_token) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
    exit;
}

try {
    $timestamp = date('Y-m-d H:i:s');
    
    // This endpoint is now just for manual testing
    // Automatic execution is handled by Heroku Scheduler
    echo json_encode([
        'status' => 'info',
        'message' => 'This endpoint is for manual testing only. Automatic void processing is handled by Heroku Scheduler.',
        'heroku_command' => 'heroku run php cron/heroku_void.php -a your-app-name',
        'scheduler_info' => 'Configure Heroku Scheduler to run: php cron/heroku_void.php',
        'current_time' => $timestamp,
        'note' => 'To test void functionality manually, run the heroku_void.php script directly'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Endpoint error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>