<?php
/**
 * Process Walk-In Exchange Request
 * 
 * This endpoint allows PAMO staff to process exchanges for walk-in orders
 * on behalf of customers. The PAMO staff member initiates and completes
 * the exchange transaction.
 * 
 * Flow: PAMO Staff → Select Walk-in Order → Process Exchange
 * 
 * Author: Exchange System
 * Date: November 5, 2025
 */

require_once '../Includes/session_start.php';
require_once '../Includes/connection.php';
require_once '../Includes/exchange_helpers.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is authenticated and is PAMO staff
$role = strtoupper($_SESSION['role_category'] ?? '');
$program = strtoupper($_SESSION['program_abbreviation'] ?? '');

if (!isset($_SESSION['user_id']) || !($role === 'EMPLOYEE' && $program === 'PAMO')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. PAMO staff only.'
    ]);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

try {
    // Get POST data
    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $exchange_items_json = $_POST['exchange_items'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');
    $auto_approve = filter_input(INPUT_POST, 'auto_approve', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    
    $pamo_staff_id = $_SESSION['user_id'];
    $pamo_staff_name = $_SESSION['name'] ?? 'PAMO Staff';
    
    // Validate inputs
    if (!$order_id) {
        throw new Exception('Invalid order ID');
    }
    
    if (empty($exchange_items_json)) {
        throw new Exception('No exchange items provided');
    }
    
    $exchange_items = json_decode($exchange_items_json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid exchange items format');
    }
    
    if (empty($exchange_items)) {
        throw new Exception('At least one item must be selected for exchange');
    }
    
    // Get order details and verify it's a walk-in order
    $stmt = $conn->prepare("
        SELECT o.*, a.first_name, a.last_name, a.id_number, a.email
        FROM orders o
        JOIN account a ON o.user_id = a.id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Order not found');
    }
    
    // Support both walk-in and online orders for PAMO staff processing
    // This allows staff to process exchanges for customers who come in person
    // regardless of whether the order was originally placed online or walk-in
    
    if ($order['status'] !== 'completed') {
        throw new Exception('Only completed orders can be exchanged. Please mark the order as completed first.');
    }
    
    // Check exchange eligibility (24-hour window still applies)
    $eligibility = checkExchangeEligibility($conn, $order_id, $order['user_id']);
    if (!$eligibility['eligible']) {
        throw new Exception($eligibility['reason']);
    }
    
    // Validate exchange request
    $validation = validateExchangeRequest([
        'order_id' => $order_id,
        'user_id' => $order['user_id'],
        'items' => $exchange_items
    ]);
    if (!$validation['valid']) {
        throw new Exception(implode(', ', $validation['errors']));
    }
    
    // Start transaction
    $conn->beginTransaction();
    
    // Calculate total price difference
    $total_price_difference = 0;
    $processed_items = [];
    
    foreach ($exchange_items as $item) {
        $original_item_code = $item['original_item_code'];
        $new_item_code = $item['new_item_code'];
        $new_size = $item['new_size'] ?? '';
        $quantity = (int)($item['exchange_quantity'] ?? $item['quantity'] ?? 0);
        
        // Get original item details
        $stmt = $conn->prepare("SELECT * FROM inventory WHERE item_code = ?");
        $stmt->execute([$original_item_code]);
        $original_item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$original_item) {
            throw new Exception("Original item not found: {$original_item_code}");
        }
        
        // Get new item details - need to find the item_code that matches the base code and size
        // Extract base code from original (e.g., UHRMB001 from UHRMB001-002)
        $base_code = preg_replace('/-\d{3}$/', '', $new_item_code);
        
        $stmt = $conn->prepare("SELECT * FROM inventory WHERE item_code LIKE ? AND sizes = ?");
        $stmt->execute([$base_code . '%', $new_size]);
        $new_item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$new_item) {
            throw new Exception("New item not found: {$base_code} (Size: {$new_size})");
        }
        
        // Check stock availability
        if ($new_item['actual_quantity'] < $quantity) {
            throw new Exception("Insufficient stock for {$new_item['item_name']} (Size: {$new_size}). Available: {$new_item['actual_quantity']}, Requested: {$quantity}");
        }
        
        // Calculate price difference
        $price_calc = calculatePriceDifference(
            $original_item['price'],
            $new_item['price'],
            $quantity
        );
        
        $total_price_difference += $price_calc['price_difference'];
        
        $processed_items[] = [
            'original_item_code' => $original_item_code,
            'original_item_name' => $original_item['item_name'],
            'original_size' => $original_item['sizes'],
            'original_price' => $original_item['price'],
            'original_quantity_purchased' => $quantity,
            'exchange_quantity' => $quantity,
            'new_item_code' => $new_item['item_code'],
            'new_item_name' => $new_item['item_name'],
            'new_size' => $new_size,
            'new_price' => $new_item['price'],
            'price_difference' => $price_calc['price_difference'],
            'subtotal_adjustment' => $price_calc['subtotal_adjustment']
        ];
    }
    
    // Determine adjustment type and payment status
    if ($total_price_difference > 0) {
        $adjustment_type = 'additional_payment';
        $payment_status = 'pending';
    } elseif ($total_price_difference < 0) {
        $adjustment_type = 'refund';
        $payment_status = 'pending';
    } else {
        $adjustment_type = 'equal_exchange';
        $payment_status = 'no_payment';
    }
    
    // Generate exchange number
    $exchange_number = generateExchangeNumber($conn);
    
    // Determine initial status based on auto-approve setting
    $initial_status = 'pending';
    $approved_by = null;
    $approved_date = null;
    
    // Check system config for auto-approval setting (default to allow auto-approve)
    $requires_approval = false;
    try {
        $stmt = $conn->prepare("SELECT config_value FROM system_config WHERE config_key = 'walkin_exchange_requires_approval'");
        $stmt->execute();
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($config) {
            $requires_approval = (bool)$config['config_value'];
        }
    } catch (Exception $e) {
        // system_config table might not exist, default to allowing auto-approve
        $requires_approval = false;
    }
    
    // If auto-approve is requested and allowed, approve immediately
    if ($auto_approve && !$requires_approval) {
        $initial_status = 'approved';
        $approved_by = $pamo_staff_name;
        $approved_date = date('Y-m-d H:i:s');
    }
    
    // Insert exchange record
    $stmt = $conn->prepare("
        INSERT INTO order_exchanges (
            exchange_number, order_id, order_number, user_id, total_price_difference,
            adjustment_type, payment_status, status, remarks,
            processed_by, processed_at, approved_by, approved_date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
    ");
    
    $stmt->execute([
        $exchange_number,
        $order_id,
        $order['order_number'],  // FIXED: Include order_number
        $order['user_id'],
        $total_price_difference,
        $adjustment_type,
        $payment_status,
        $initial_status,
        $remarks,
        $pamo_staff_id,
        $approved_by,
        $approved_date
    ]);
    
    $exchange_id = $conn->lastInsertId();
    
    // Insert exchange items
    $stmt = $conn->prepare("
        INSERT INTO order_exchange_items (
            exchange_id, original_item_code, original_item_name, original_size,
            original_price, original_quantity_purchased, exchange_quantity,
            new_item_code, new_item_name, new_size, new_price,
            price_difference, subtotal_adjustment
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($processed_items as $item) {
        $stmt->execute([
            $exchange_id,
            $item['original_item_code'],
            $item['original_item_name'],
            $item['original_size'],
            $item['original_price'],
            $item['original_quantity_purchased'],
            $item['exchange_quantity'],
            $item['new_item_code'],
            $item['new_item_name'],
            $item['new_size'],
            $item['new_price'],
            $item['price_difference'],
            $item['subtotal_adjustment']
        ]);
    }
    
    // DO NOT UPDATE INVENTORY HERE
    // Inventory updates should only happen when the exchange is marked as "completed"
    // This allows staff to:
    // 1. Generate exchange slip
    // 2. Print slip for customer
    // 3. Customer pays any price difference (if applicable)
    // 4. Staff marks exchange as "completed" → THEN inventory updates
    // This prevents double inventory updates and ensures payment is collected before items are exchanged
    
    // Update order exchange tracking
    $stmt = $conn->prepare("
        UPDATE orders 
        SET has_exchange = 1, 
            exchange_count = exchange_count + 1 
        WHERE id = ?
    ");
    $stmt->execute([$order_id]);
    
    // Log activity
    $activity_description = "Walk-in exchange processed by PAMO staff ({$pamo_staff_name})";
    if ($initial_status === 'approved') {
        $activity_description .= " and auto-approved";
    }
    
    logExchangeActivity(
        $conn,
        $exchange_id,
        'created',
        $activity_description,
        $pamo_staff_id
    );
    
    // Log to activities table for audit trail
    $stmt = $conn->prepare("
        INSERT INTO activities (
            action_type,
            description,
            item_code,
            user_id,
            timestamp
        ) VALUES (?, ?, NULL, ?, NOW())
    ");
    $stmt->execute([
        'Generated Exchange Item Slip',
        "Exchange slip generated - Exchange #: {$exchange_number}, Order: {$order['order_number']}, Customer: {$order['first_name']} {$order['last_name']}, Status: {$initial_status}",
        $pamo_staff_id
    ]);
    
    // Commit transaction
    $conn->commit();
    
    // Prepare response
    $response = [
        'success' => true,
        'message' => $initial_status === 'approved' 
            ? 'Walk-in exchange processed and approved successfully!' 
            : 'Walk-in exchange request created successfully! Awaiting approval.',
        'exchange_id' => $exchange_id,
        'exchange_number' => $exchange_number,
        'order_number' => $order['order_number'],
        'customer_name' => $order['first_name'] . ' ' . $order['last_name'],
        'customer_id_number' => $order['id_number'],
        'total_price_difference' => $total_price_difference,
        'adjustment_type' => $adjustment_type,
        'payment_status' => $payment_status,
        'status' => $initial_status,
        'processed_by' => $pamo_staff_name,
        'auto_approved' => $initial_status === 'approved',
        'items_count' => count($processed_items),
        'download_slip_url' => "../Backend/generate_exchange_slip.php?exchange_id={$exchange_id}&admin=1"
    ];
    
    http_response_code(200);
    echo json_encode($response);
    
} catch (Exception $e) {
    // Rollback on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    // Log detailed error for debugging
    error_log("Walk-in Exchange Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTrace()
        ]
    ]);
}
