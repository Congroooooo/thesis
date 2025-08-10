<?php
// Simple HTTP trigger for voiding unpaid pre-orders
// Protect with a secret token. Set env CRON_SECRET, or edit the fallback below.

header('Content-Type: text/plain');

$providedToken = isset($_GET['token']) ? (string)$_GET['token'] : '';
$secretToken = getenv('CRON_SECRET');
if ($secretToken === false || $secretToken === '') {
    // Fallback: change this before exposing publicly
    $secretToken = 'CHANGE_THIS_TO_A_LONG_RANDOM_SECRET';
}

if (!hash_equals($secretToken, $providedToken)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// Run the job
require_once __DIR__ . '/void_unpaid_orders.php';

echo 'ok';
?>


