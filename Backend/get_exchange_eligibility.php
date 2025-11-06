<?php
/**
 * Get Exchange Eligibility
 * Check if an order is eligible for exchange
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

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if (!$order_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Order ID is required'
    ]);
    exit;
}

// PAMO staff can check any order, customers can only check their own
$role = strtoupper($_SESSION['role_category'] ?? '');
$program = strtoupper($_SESSION['program_abbreviation'] ?? '');
$is_pamo_staff = ($role === 'EMPLOYEE' && $program === 'PAMO');

// Get order to find the actual user_id
$stmt = $conn->prepare("SELECT user_id FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order_data) {
    echo json_encode([
        'success' => false,
        'message' => 'Order not found'
    ]);
    exit;
}

// Use the order's user_id for eligibility check
$check_user_id = $order_data['user_id'];

// If not PAMO staff, verify the order belongs to the logged-in user
if (!$is_pamo_staff && $check_user_id != $_SESSION['user_id']) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized to access this order'
    ]);
    exit;
}

$eligibility = checkExchangeEligibility($conn, $order_id, $check_user_id);

if ($eligibility['eligible']) {
    // Get available items for exchange
    $items = getOrderItemsForExchange($conn, $order_id);
    
    // Get customer information
    $stmt = $conn->prepare("
        SELECT a.first_name, a.last_name, a.id_number, a.email
        FROM account a
        WHERE a.id = ?
    ");
    $stmt->execute([$check_user_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'eligible' => true,
        'message' => $eligibility['message'],
        'hours_remaining' => $eligibility['hours_remaining'],
        'order_number' => $eligibility['order']['order_number'],
        'order_date' => $eligibility['order']['created_at'],
        'customer_name' => $customer['first_name'] . ' ' . $customer['last_name'],
        'customer_id_number' => $customer['id_number'],
        'customer_email' => $customer['email'],
        'order' => $eligibility['order'],
        'items' => $items
    ]);
} else {
    echo json_encode([
        'success' => true,
        'eligible' => false,
        'message' => $eligibility['message'],
        'reason' => $eligibility['reason'] ?? $eligibility['message']
    ]);
}
