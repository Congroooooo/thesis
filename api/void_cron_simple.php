<?php
/**
 * Production web endpoint for cron-job.org
 * Executes void unpaid orders cron job asynchronously via HTTP
 * Prevents timeout and ensures immediate success response
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
    // Log the cron trigger time
    $logFile = __DIR__ . '/../cron/void_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] [INFO] Web cron triggered via cron-job.org\n", FILE_APPEND | LOCK_EX);

    // Respond immediately to cron-job.org
    echo json_encode([
        'status' => 'success',
        'message' => 'Void cron started in background',
        'timestamp' => $timestamp
    ]);

    // Continue the process in background
    fastcgi_finish_request(); // Safely flush output to client and continue execution

    // Execute the real cron script asynchronously
    $phpPath = PHP_BINARY;
    $cronScript = realpath(__DIR__ . '/../cron/void_unpaid_orders.php');

    if (stripos(PHP_OS, 'WIN') === 0) {
        // Windows environment
        pclose(popen("start /B {$phpPath} {$cronScript}", "r"));
    } else {
        // Linux / AlwaysData / Docker environment
        exec("nohup {$phpPath} {$cronScript} > /dev/null 2>&1 &");
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
