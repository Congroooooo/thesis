<?php
/**
 * Local Test for Heroku Void Script
 * Run this to test the Heroku void functionality locally
 */

echo "=== Testing Heroku Void Script Locally ===\n";
echo "Current time: " . date('Y-m-d H:i:s') . "\n\n";

// Execute the heroku void script
ob_start();
include __DIR__ . '/heroku_void.php';
$output = ob_get_clean();

echo "Output from heroku_void.php:\n";
echo "─────────────────────────────\n";
echo $output;
echo "─────────────────────────────\n";

echo "\n=== Test Complete ===\n";
?>