<?php
/**
 * Local Test for Web Cron Endpoint
 * Simulates cron-job.org calling your API
 */

echo "=== Local Cron Endpoint Test ===\n";
echo "Testing: void_cron_simple.php\n";
echo "Current time: " . date('Y-m-d H:i:s') . "\n\n";

// Simulate GET request
$_GET['token'] = 'proware_void_2025_secure';

// Capture output
ob_start();
include __DIR__ . '/../api/void_cron_simple.php';
$output = ob_get_clean();

echo "Response:\n";
echo $output . "\n";

echo "\n=== Test Complete ===\n";
?>