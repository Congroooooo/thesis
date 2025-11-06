<?php
/**
 * Get Available Sizes for Exchange
 * Returns available sizes/variants for a specific item
 */

session_start();
header('Content-Type: application/json');
require_once '../Includes/connection.php';
require_once '../Includes/exchange_helpers.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

$item_code = isset($_GET['item_code']) ? trim($_GET['item_code']) : '';
$exclude_size = isset($_GET['exclude_size']) ? trim($_GET['exclude_size']) : (isset($_GET['current_size']) ? trim($_GET['current_size']) : '');

if (!$item_code) {
    echo json_encode([
        'success' => false,
        'message' => 'Item code is required'
    ]);
    exit;
}

$sizes = getAvailableSizesForExchange($conn, $item_code);

// Filter out the excluded size
$available_sizes = array_filter($sizes, function($size) use ($exclude_size) {
    return $size['sizes'] !== $exclude_size;
});

// Format response with size, quantity, and price
$formatted_sizes = array_map(function($size) {
    return [
        'size' => $size['sizes'],
        'quantity' => $size['actual_quantity'],
        'price' => $size['price']
    ];
}, $available_sizes);

echo json_encode([
    'success' => true,
    'sizes' => array_values($formatted_sizes)
]);
