<?php
/**
 * Process Exchange Request
 * Handles the creation of exchange requests with price adjustments
 */

session_start();
header('Content-Type: application/json');
require_once '../Includes/connection.php';
require_once '../Includes/exchange_helpers.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Please log in.'
    ]);
    exit;
}

// Get POST data
$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
$exchange_items = isset($_POST['exchange_items']) ? json_decode($_POST['exchange_items'], true) : [];
$remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';

try {
    // Validate input
    $validation = validateExchangeRequest([
        'order_id' => $order_id,
        'user_id' => $_SESSION['user_id'],
        'items' => $exchange_items
    ]);
    
    if (!$validation['valid']) {
        echo json_encode([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validation['errors']
        ]);
        exit;
    }
    
    // Check eligibility
    $eligibility = checkExchangeEligibility($conn, $order_id, $_SESSION['user_id']);
    
    if (!$eligibility['eligible']) {
        echo json_encode([
            'success' => false,
            'message' => $eligibility['message']
        ]);
        exit;
    }
    
    // Get order details
    $order = $eligibility['order'];
    
    // Begin transaction
    $conn->beginTransaction();
    
    // Generate exchange number
    $exchange_number = generateExchangeNumber($conn);
    
    // Calculate total price difference
    $total_price_difference = 0;
    $adjustment_type = 'none';
    
    // Validate each item and get current inventory
    $processed_items = [];
    
    foreach ($exchange_items as $item) {
        // Get original item details from order
        $order_items = json_decode($order['items'], true);
        $original_item = null;
        
        foreach ($order_items as $oi) {
            if ($oi['item_code'] == $item['original_item_code'] && $oi['size'] == $item['original_size']) {
                $original_item = $oi;
                break;
            }
        }
        
        if (!$original_item) {
            throw new Exception("Original item not found in order: " . $item['original_item_code']);
        }
        
        // Get new item details from inventory
        $new_item_stmt = $conn->prepare("
            SELECT item_code, item_name, sizes, price, actual_quantity 
            FROM inventory 
            WHERE item_code = ?
        ");
        $new_item_stmt->execute([$item['new_item_code']]);
        $new_item = $new_item_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$new_item) {
            throw new Exception("New item not found in inventory: " . $item['new_item_code']);
        }
        
        // Check stock availability
        if ($new_item['actual_quantity'] < $item['exchange_quantity']) {
            throw new Exception("Insufficient stock for {$new_item['item_name']} (Size: {$new_item['sizes']}). Available: {$new_item['actual_quantity']}, Requested: {$item['exchange_quantity']}");
        }
        
        // Calculate price difference
        $price_calc = calculatePriceDifference(
            floatval($original_item['price']),
            floatval($new_item['price']),
            intval($item['exchange_quantity'])
        );
        
        $total_price_difference += $price_calc['subtotal_adjustment'];
        
        $processed_items[] = [
            'original_item_code' => $original_item['item_code'],
            'original_item_name' => $original_item['item_name'],
            'original_size' => $original_item['size'],
            'original_price' => $original_item['price'],
            'original_quantity_purchased' => $original_item['quantity'],
            'exchange_quantity' => $item['exchange_quantity'],
            'new_item_code' => $new_item['item_code'],
            'new_item_name' => $new_item['item_name'],
            'new_size' => $new_item['sizes'],
            'new_price' => $new_item['price'],
            'price_difference' => $price_calc['price_difference'],
            'subtotal_adjustment' => $price_calc['subtotal_adjustment']
        ];
    }
    
    // Determine adjustment type
    if ($total_price_difference > 0) {
        $adjustment_type = 'additional_payment';
        $payment_status = 'pending';
    } elseif ($total_price_difference < 0) {
        $adjustment_type = 'refund';
        $payment_status = 'pending';
    } else {
        $adjustment_type = 'none';
        $payment_status = 'not_applicable';
    }
    
    // Insert exchange record
    $exchange_stmt = $conn->prepare("
        INSERT INTO order_exchanges (
            exchange_number, 
            order_id, 
            order_number, 
            user_id, 
            status, 
            total_price_difference, 
            adjustment_type,
            payment_status,
            exchange_date,
            remarks
        ) VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, NOW(), ?)
    ");
    
    $exchange_stmt->execute([
        $exchange_number,
        $order_id,
        $order['order_number'],
        $_SESSION['user_id'],
        $total_price_difference,
        $adjustment_type,
        $payment_status,
        $remarks
    ]);
    
    $exchange_id = $conn->lastInsertId();
    
    // Insert exchange items
    $item_stmt = $conn->prepare("
        INSERT INTO order_exchange_items (
            exchange_id,
            original_item_code,
            original_item_name,
            original_size,
            original_price,
            original_quantity_purchased,
            exchange_quantity,
            new_item_code,
            new_item_name,
            new_size,
            new_price,
            price_difference,
            subtotal_adjustment
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($processed_items as $item) {
        $item_stmt->execute([
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
    
    // Update order exchange tracking
    $update_order_stmt = $conn->prepare("
        UPDATE orders 
        SET has_exchange = 1, 
            exchange_count = exchange_count + 1 
        WHERE id = ?
    ");
    $update_order_stmt->execute([$order_id]);
    
    // Log activity
    $item_count = count($processed_items);
    $total_qty = array_sum(array_column($processed_items, 'exchange_quantity'));
    logExchangeActivity(
        $conn,
        $exchange_id,
        'created',
        "Exchange request created: {$item_count} item(s), {$total_qty} total quantity, " . 
        "Price adjustment: " . ($adjustment_type == 'additional_payment' ? '+' : '') . 
        number_format($total_price_difference, 2),
        $_SESSION['user_id']
    );
    
    // Commit transaction
    $conn->commit();
    
    // Prepare response
    $response = [
        'success' => true,
        'message' => 'Exchange request submitted successfully',
        'exchange_id' => $exchange_id,
        'exchange_number' => $exchange_number,
        'total_price_difference' => $total_price_difference,
        'adjustment_type' => $adjustment_type,
        'payment_status' => $payment_status,
        'items_count' => $item_count,
        'total_quantity' => $total_qty
    ];
    
    // Add adjustment message
    if ($adjustment_type == 'additional_payment') {
        $response['adjustment_message'] = "Additional Payment Required: ₱" . number_format(abs($total_price_difference), 2);
    } elseif ($adjustment_type == 'refund') {
        $response['adjustment_message'] = "Refund Due: ₱" . number_format(abs($total_price_difference), 2);
    } else {
        $response['adjustment_message'] = "No price adjustment needed (equal exchange)";
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("Exchange processing error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error processing exchange: ' . $e->getMessage()
    ]);
}
