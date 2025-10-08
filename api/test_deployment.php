<?php
// Simple test endpoint to verify Heroku deployment
header('Content-Type: application/json');

echo json_encode([
    'status' => 'success',
    'message' => 'Heroku deployment is working!',
    'timestamp' => date('Y-m-d H:i:s'),
    'server' => $_SERVER['HTTP_HOST'] ?? 'unknown',
    'php_version' => phpversion(),
    'files_exist' => [
        'cron_script' => file_exists(__DIR__ . '/../cron/void_unpaid_orders.php'),
        'connection' => file_exists(__DIR__ . '/../Includes/connection.php'),
        'notifications' => file_exists(__DIR__ . '/../Includes/notifications.php')
    ]
]);
?>