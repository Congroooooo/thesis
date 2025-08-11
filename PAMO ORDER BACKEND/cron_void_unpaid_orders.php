<?php
header('Content-Type: text/plain');

$providedToken = isset($_GET['token']) ? (string)$_GET['token'] : '';
$secretToken = getenv('CRON_SECRET');
if ($secretToken === false || $secretToken === '') {
    $secretToken = 'CHANGE_THIS_TO_A_LONG_RANDOM_SECRET';
}

if (!hash_equals($secretToken, $providedToken)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

require_once __DIR__ . '/void_unpaid_orders.php';

echo 'ok';
?>


