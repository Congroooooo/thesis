<?php
/**
 * Exchange Helper Functions
 * Provides utility functions for the exchange system
 */

/**
 * Check if an order is eligible for exchange
 * @param PDO $conn Database connection
 * @param int $order_id Order ID
 * @param int $user_id User ID
 * @return array Result array with 'eligible' boolean and 'message' string
 */
function checkExchangeEligibility($conn, $order_id, $user_id) {
    try {
        // Get order details
        $stmt = $conn->prepare("
            SELECT id, order_number, status, created_at, total_amount 
            FROM orders 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$order_id, $user_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            return [
                'eligible' => false,
                'message' => 'Order not found or does not belong to you.'
            ];
        }
        
        // Check order status - must be completed
        if ($order['status'] !== 'completed') {
            return [
                'eligible' => false,
                'message' => 'Only completed orders can be exchanged. Please ensure the order is marked as completed first.'
            ];
        }
        
        // Get exchange time limit from config
        $config_stmt = $conn->prepare("SELECT config_value FROM system_config WHERE config_key = 'exchange_time_limit_hours'");
        $config_stmt->execute();
        $config = $config_stmt->fetch(PDO::FETCH_ASSOC);
        $time_limit_hours = $config ? intval($config['config_value']) : 24;
        
        // Check if within time limit
        $order_time = strtotime($order['created_at']);
        $current_time = time();
        $hours_passed = ($current_time - $order_time) / 3600;
        
        if ($hours_passed > $time_limit_hours) {
            return [
                'eligible' => false,
                'message' => "Exchange period has expired. Exchanges are only allowed within {$time_limit_hours} hours of order creation."
            ];
        }
        
        // Check if exchange feature is enabled
        $enabled_stmt = $conn->prepare("SELECT config_value FROM system_config WHERE config_key = 'exchange_enabled'");
        $enabled_stmt->execute();
        $enabled = $enabled_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$enabled || $enabled['config_value'] != '1') {
            return [
                'eligible' => false,
                'message' => 'Exchange feature is currently disabled.'
            ];
        }
        
        return [
            'eligible' => true,
            'message' => 'Order is eligible for exchange.',
            'order' => $order,
            'hours_remaining' => round($time_limit_hours - $hours_passed, 1)
        ];
        
    } catch (PDOException $e) {
        error_log("Exchange eligibility check error: " . $e->getMessage());
        return [
            'eligible' => false,
            'message' => 'An error occurred while checking eligibility.'
        ];
    }
}

/**
 * Get available items from an order for exchange
 * @param PDO $conn Database connection
 * @param int $order_id Order ID
 * @return array List of items with their details
 */
function getOrderItemsForExchange($conn, $order_id) {
    try {
        $stmt = $conn->prepare("SELECT items FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            return [];
        }
        
        $items = json_decode($order['items'], true);
        if (!is_array($items)) {
            return [];
        }
        
        // Add available quantity for each item (considering previous exchanges)
        foreach ($items as &$item) {
            $item['available_for_exchange'] = $item['quantity'];
            
            // Get already exchanged quantity
            $exchange_stmt = $conn->prepare("
                SELECT COALESCE(SUM(oei.exchange_quantity), 0) as exchanged_qty
                FROM order_exchange_items oei
                JOIN order_exchanges oe ON oei.exchange_id = oe.id
                WHERE oe.order_id = ? 
                AND oei.original_item_code = ? 
                AND oei.original_size = ?
                AND oe.status NOT IN ('rejected', 'cancelled')
            ");
            $exchange_stmt->execute([$order_id, $item['item_code'], $item['size']]);
            $exchanged = $exchange_stmt->fetch(PDO::FETCH_ASSOC);
            
            $item['exchanged_quantity'] = $exchanged ? intval($exchanged['exchanged_qty']) : 0;
            $item['available_for_exchange'] = $item['quantity'] - $item['exchanged_quantity'];
            
            // Get image_path from inventory
            $img_stmt = $conn->prepare("SELECT image_path FROM inventory WHERE item_code = ?");
            $img_stmt->execute([$item['item_code']]);
            $img_data = $img_stmt->fetch(PDO::FETCH_ASSOC);
            $item['image_path'] = $img_data ? $img_data['image_path'] : 'Images/default-item.png';
        }
        unset($item);
        
        return $items;
        
    } catch (PDOException $e) {
        error_log("Get order items error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get available sizes/variants for an item
 * @param PDO $conn Database connection
 * @param string $item_code Original item code
 * @return array List of available sizes with stock info
 */
function getAvailableSizesForExchange($conn, $item_code) {
    try {
        // Extract base item code (remove size suffix)
        $base_code = preg_replace('/-\d{3}$/', '', $item_code);
        
        // Get all sizes for this item
        $stmt = $conn->prepare("
            SELECT item_code, item_name, sizes, actual_quantity, price
            FROM inventory
            WHERE item_code LIKE ? AND actual_quantity > 0
            ORDER BY sizes
        ");
        $stmt->execute([$base_code . '%']);
        $sizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $sizes;
        
    } catch (PDOException $e) {
        error_log("Get available sizes error: " . $e->getMessage());
        return [];
    }
}

/**
 * Calculate price difference for exchange
 * @param float $original_price Original item price
 * @param float $new_price New item price
 * @param int $quantity Exchange quantity
 * @return array Price calculation details
 */
function calculatePriceDifference($original_price, $new_price, $quantity) {
    $price_diff = $new_price - $original_price;
    $subtotal_adjustment = $price_diff * $quantity;
    
    $adjustment_type = 'none';
    if ($subtotal_adjustment > 0) {
        $adjustment_type = 'additional_payment';
    } elseif ($subtotal_adjustment < 0) {
        $adjustment_type = 'refund';
    }
    
    return [
        'price_difference' => round($price_diff, 2),
        'subtotal_adjustment' => round($subtotal_adjustment, 2),
        'adjustment_type' => $adjustment_type,
        'quantity' => $quantity
    ];
}

/**
 * Generate exchange number
 * @param PDO $conn Database connection
 * @return string Exchange number (format: EX-MMDD-XXXXXX)
 */
function generateExchangeNumber($conn) {
    try {
        $prefix = 'EX';
        $date_part = date('md');
        $date_key = $prefix . '-' . $date_part;
        
        // Get or create sequence counter with row lock
        $stmt = $conn->prepare("
            INSERT INTO exchange_sequence (date_key, last_sequence, updated_at) 
            VALUES (?, 0, NOW())
            ON DUPLICATE KEY UPDATE updated_at = NOW()
        ");
        $stmt->execute([$date_key]);
        
        $stmt = $conn->prepare("
            SELECT last_sequence 
            FROM exchange_sequence 
            WHERE date_key = ? 
            FOR UPDATE
        ");
        $stmt->execute([$date_key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $current_seq = $row ? intval($row['last_sequence']) : 0;
        
        $new_seq = $current_seq + 1;
        
        // Update sequence counter
        $update_stmt = $conn->prepare("
            UPDATE exchange_sequence 
            SET last_sequence = ?, updated_at = NOW() 
            WHERE date_key = ?
        ");
        $update_stmt->execute([$new_seq, $date_key]);
        
        $exchange_number = sprintf('%s-%s-%06d', $prefix, $date_part, $new_seq);
        
        return $exchange_number;
        
    } catch (PDOException $e) {
        error_log("Generate exchange number error: " . $e->getMessage());
        throw new Exception("Failed to generate exchange number");
    }
}

/**
 * Log exchange activity
 * @param PDO $conn Database connection
 * @param int $exchange_id Exchange ID
 * @param string $action_type Action type (created, approved, rejected, etc.)
 * @param string $description Activity description
 * @param int $performed_by User ID who performed the action
 */
function logExchangeActivity($conn, $exchange_id, $action_type, $description, $performed_by) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO exchange_activities (exchange_id, action_type, description, performed_by, performed_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$exchange_id, $action_type, $description, $performed_by]);
    } catch (PDOException $e) {
        error_log("Log exchange activity error: " . $e->getMessage());
    }
}

/**
 * Validate exchange request data
 * @param array $exchange_data Exchange data to validate
 * @return array Validation result with 'valid' boolean and 'errors' array
 */
function validateExchangeRequest($exchange_data) {
    $errors = [];
    
    if (empty($exchange_data['order_id'])) {
        $errors[] = "Order ID is required";
    }
    
    if (empty($exchange_data['user_id'])) {
        $errors[] = "User ID is required";
    }
    
    if (empty($exchange_data['items']) || !is_array($exchange_data['items']) || count($exchange_data['items']) == 0) {
        $errors[] = "At least one item must be selected for exchange";
    }
    
    if (!empty($exchange_data['items'])) {
        foreach ($exchange_data['items'] as $index => $item) {
            if (empty($item['original_item_code'])) {
                $errors[] = "Item #{$index}: Original item code is required";
            }
            if (empty($item['new_item_code'])) {
                $errors[] = "Item #{$index}: New item code is required";
            }
            if (empty($item['exchange_quantity']) || $item['exchange_quantity'] <= 0) {
                $errors[] = "Item #{$index}: Exchange quantity must be greater than 0";
            }
            if (isset($item['available_quantity']) && $item['exchange_quantity'] > $item['available_quantity']) {
                $errors[] = "Item #{$index}: Exchange quantity exceeds available quantity";
            }
        }
    }
    
    return [
        'valid' => count($errors) == 0,
        'errors' => $errors
    ];
}
