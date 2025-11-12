<?php
date_default_timezone_set('Asia/Manila');
ob_start();
// Log errors but don't display them (would break JSON response)
error_reporting(E_ALL);
ini_set('display_errors', 0);  // Changed to 0 to prevent HTML output
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../error_log.txt');

session_start();
require_once '../Includes/connection.php';
require_once '../Includes/notifications.php';
require_once '../Includes/inventory_update_notifier.php';
require_once '../Includes/MonthlyInventoryManager.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    if (!isset($_POST['order_id']) || !isset($_POST['status'])) {
        $response['message'] = 'Missing required parameters';
        echo json_encode($response);
        ob_end_flush();
        exit;
    }

    $order_id = $_POST['order_id'];
    $status = $_POST['status'];
    $rejection_reason = isset($_POST['rejection_reason']) ? $_POST['rejection_reason'] : null;

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? FOR UPDATE");
        
        if (!$stmt->execute([$order_id])) {
            throw new Exception('Failed to get order details');
        }
        
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        


        if (!$order) {
            throw new Exception('Order not found');
        }

        // If status is being changed to approved, validate stock availability first
        if ($status === 'approved') {
            // Decode the items JSON
            $order_items = json_decode($order['items'], true);
            if (!$order_items || !is_array($order_items)) {
                throw new Exception('Invalid order items data');
            }
            
            // Check stock availability for each item
            $insufficientStockItems = [];
            foreach ($order_items as $item) {
                // Get current inventory with lock to prevent race conditions
                $stockCheckStmt = $conn->prepare("SELECT item_name, actual_quantity FROM inventory WHERE item_code = ? FOR UPDATE");
                if (!$stockCheckStmt->execute([$item['item_code']])) {
                    throw new Exception('Failed to check inventory for item: ' . $item['item_code']);
                }
                
                $inventory = $stockCheckStmt->fetch(PDO::FETCH_ASSOC);
                if (!$inventory) {
                    throw new Exception('Item no longer exists in inventory: ' . $item['item_code']);
                }
                
                // Calculate quantity reserved by OTHER approved orders (not yet completed)
                $reservedQtyStmt = $conn->prepare("
                    SELECT SUM(jt.quantity) as reserved_qty
                    FROM orders o,
                    JSON_TABLE(
                        o.items,
                        '$[*]' COLUMNS(
                            item_code VARCHAR(50) PATH '$.item_code',
                            quantity INT PATH '$.quantity'
                        )
                    ) AS jt
                    WHERE o.status = 'approved' 
                    AND o.id != ?
                    AND jt.item_code = ?
                ");
                $reservedQtyStmt->execute([$order_id, $item['item_code']]);
                $reservedResult = $reservedQtyStmt->fetch(PDO::FETCH_ASSOC);
                $reservedQty = $reservedResult['reserved_qty'] ? (int)$reservedResult['reserved_qty'] : 0;
                
                // Calculate actual available quantity (physical stock - reserved by approved orders)
                $requestedQty = $item['quantity'];
                $physicalStock = $inventory['actual_quantity'];
                $availableQty = $physicalStock - $reservedQty;
                
                if ($availableQty < $requestedQty) {
                    $insufficientStockItems[] = [
                        'item_name' => $inventory['item_name'],
                        'size' => $item['size'] ?? 'N/A',
                        'requested' => $requestedQty,
                        'available' => $availableQty,
                        'physical_stock' => $physicalStock,
                        'reserved' => $reservedQty
                    ];
                }
            }

            if (!empty($insufficientStockItems)) {
                $rejectionMessage = "Order automatically rejected due to insufficient remaining stock:\n";
                foreach ($insufficientStockItems as $stockItem) {
                    $rejectionMessage .= "• {$stockItem['item_name']} (Size: {$stockItem['size']})\n";
                    $rejectionMessage .= "  Requested: {$stockItem['requested']}\n";
                    $rejectionMessage .= "  Available: {$stockItem['available']}";
                    if ($stockItem['reserved'] > 0) {
                        $rejectionMessage .= " (Physical stock: {$stockItem['physical_stock']}, Reserved by other orders: {$stockItem['reserved']})";
                    }
                    $rejectionMessage .= "";
                }
                $rejectionMessage .= "Note: Available stock accounts for items reserved by other accepted orders.";
                
                // Build HTML formatted message for notification
                $notificationHtml = "Your order #{$order['order_number']} has been automatically rejected due to insufficient stock.<br><br><span class='rejection-reason'><strong>Details:</strong><br>";
                foreach ($insufficientStockItems as $stockItem) {
                    $notificationHtml .= "• <strong>{$stockItem['item_name']}</strong> (Size: {$stockItem['size']})<br>";
                    $notificationHtml .= "&nbsp;&nbsp;Requested: {$stockItem['requested']} | Available: <strong>{$stockItem['available']}</strong>";
                    if ($stockItem['reserved'] > 0) {
                        $notificationHtml .= "<br>&nbsp;&nbsp;<em>(Physical stock: {$stockItem['physical_stock']}, Reserved: {$stockItem['reserved']})</em>";
                    }
                    $notificationHtml .= "<br>";
                }
                $notificationHtml .= "<br><em>Note: Available stock accounts for items reserved by other accepted orders.</em>";
                $notificationHtml .= "</span>";
                
                // Update order status to rejected with auto-generated reason
                $updateStmt = $conn->prepare("UPDATE orders SET status = 'rejected', rejection_reason = ?, updated_at = NOW() WHERE id = ?");
                if (!$updateStmt->execute([$rejectionMessage, $order_id])) {
                    throw new Exception('Failed to auto-reject order due to insufficient stock');
                }
                
                // Create sales records for auto-rejected order - one per item (with ₱0 amount)
                foreach ($order_items as $item) {
                    $salesStmt = $conn->prepare("
                        INSERT INTO sales (
                            transaction_number,
                            item_code,
                            size,
                            quantity,
                            price_per_item,
                            total_amount,
                            transaction_type,
                            sale_date
                        ) VALUES (?, ?, ?, ?, ?, 0.00, 'Rejected', NOW())
                    ");
                    if (!$salesStmt->execute([
                        $order['order_number'],
                        $item['item_code'],
                        $item['size'],
                        $item['quantity'],
                        $item['price']
                    ])) {
                        throw new Exception('Failed to create sales record for auto-rejected order item');
                    }
                }
                
                // Log the rejection activity
                foreach ($order_items as $item) {
                    $activity_description = "Order Rejected (Auto) - Order #: {$order['order_number']}, Item: {$item['item_name']}, Quantity: {$item['quantity']} - Insufficient Stock";
                    $activity_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
                    
                    $activityStmt = $conn->prepare(
                        "INSERT INTO activities (
                            action_type,
                            description,
                            item_code,
                            user_id,
                            timestamp
                        ) VALUES (?, ?, ?, ?, NOW())"
                    );
                    $activityStmt->execute([
                        'Order Rejected',
                        $activity_description,
                        $item['item_code'],
                        $activity_user_id
                    ]);
                }
                
                // Send notification to customer
                try {
                    createNotification($conn, $order['user_id'], $notificationHtml, $order['order_number'], 'rejected');
                } catch (Exception $e) {
                    error_log("Failed to create notification: " . $e->getMessage());
                }
                
                // Commit the auto-rejection
                $conn->commit();
                
                // Return response indicating auto-rejection
                $response['success'] = false;
                $response['auto_rejected'] = true;
                $response['message'] = 'Order cannot be approved due to insufficient stock and has been automatically rejected.';
                $response['insufficient_items'] = $insufficientStockItems;
                $response['rejection_reason'] = $rejectionMessage;
                
                echo json_encode($response);
                ob_end_flush();
                exit;
            }
        }

        // If status is being changed to completed, process inventory updates
        if ($status === 'completed') {
            
            // Create MonthlyInventoryManager instance
            $monthlyInventory = new MonthlyInventoryManager($conn);
            
            // Decode the items JSON
            $order_items = json_decode($order['items'], true);
            if (!$order_items || !is_array($order_items)) {
                throw new Exception('Invalid order items data');
            }
            
            foreach ($order_items as $item) {

                
                // Get current inventory with lock
                $stockStmt = $conn->prepare("SELECT * FROM inventory WHERE item_code = ? FOR UPDATE");
                if (!$stockStmt->execute([$item['item_code']])) {
                    throw new Exception('Failed to get inventory for item: ' . $item['item_code']);
                }
                
                $inventory = $stockStmt->fetch(PDO::FETCH_ASSOC);
                if (!$inventory) {
                    throw new Exception('Item no longer exists in inventory: ' . $item['item_code']);
                }
                

                
                // Verify sufficient quantity BEFORE recording the sale
                if ($inventory['actual_quantity'] < $item['quantity']) {
                    throw new Exception('Insufficient quantity for item: ' . $inventory['item_name']);
                }
                

                
                // Record the sale in the sales table
                $saleStmt = $conn->prepare(
                    "INSERT INTO sales (
                        transaction_number, 
                        item_code, 
                        size, 
                        quantity, 
                        price_per_item, 
                        total_amount, 
                        sale_date
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW())"
                );
                
                $transaction_number = $order['order_number'];
                $size = $item['size'] ?? 'One Size';
                $quantity = $item['quantity'];
                $price_per_item = $item['price'];
                $total_amount = $item['price'] * $item['quantity'];
                
                if (!$saleStmt->execute([
                    $transaction_number,
                    $item['item_code'],
                    $size,
                    $quantity,
                    $price_per_item,
                    $total_amount
                ])) {
                    throw new Exception('Failed to record sale for item: ' . $inventory['item_name']);
                }
                
                // Update sold_quantity in inventory
                $updateSoldStmt = $conn->prepare(
                    "UPDATE inventory 
                    SET sold_quantity = sold_quantity + ?
                    WHERE item_code = ?"
                );
                if (!$updateSoldStmt->execute([$item['quantity'], $item['item_code']])) {
                    throw new Exception('Failed to update sold quantity for item: ' . $inventory['item_name']);
                }
                
                // Record sale in monthly inventory system
                // This will create monthly_sales_records entry, update snapshot, and sync actual_quantity
                $processedBy = $_SESSION['user_id'] ?? 0;
                
                // Debug logging
                $logFile = __DIR__ . '/../../order_completion_log.txt';
                $logMessage = date('[Y-m-d H:i:s] ') . "About to record sale: Order={$transaction_number}, Item={$item['item_code']}, Qty={$quantity}, Price={$price_per_item}, ProcessedBy={$processedBy}\n";
                file_put_contents($logFile, $logMessage, FILE_APPEND);
                
                try {
                    $monthlyInventory->recordSale(
                        $transaction_number,
                        $item['item_code'],
                        $quantity,
                        $price_per_item,
                        $total_amount,
                        $processedBy,
                        false  // Don't use internal transaction since we're already in one
                    );
                    
                    // Log success
                    $logMessage = date('[Y-m-d H:i:s] ') . "Successfully recorded sale for {$item['item_code']}\n";
                    file_put_contents($logFile, $logMessage, FILE_APPEND);
                    
                } catch (Exception $monthlyError) {
                    // Log error
                    $logMessage = date('[Y-m-d H:i:s] ') . "ERROR recording sale: " . $monthlyError->getMessage() . "\n";
                    file_put_contents($logFile, $logMessage, FILE_APPEND);
                    
                    throw new Exception('Failed to record sale in monthly inventory: ' . $monthlyError->getMessage());
                }
                
                $customerStmt = $conn->prepare("
                    SELECT id, CONCAT(first_name, ' ', last_name) as full_name 
                    FROM account 
                    WHERE id = ?
                ");
                if (!$customerStmt->execute([$order['user_id']])) {
                    throw new Exception('Failed to get customer information');
                }
                
                $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
                if ($customer) {
                    $customerInsertStmt = $conn->prepare("
                        INSERT INTO transaction_customers (
                            transaction_number, 
                            customer_id, 
                            customer_name, 
                            sale_date
                        ) VALUES (?, ?, ?, NOW()) 
                        ON DUPLICATE KEY UPDATE customer_name = VALUES(customer_name)
                    ");
                    
                    if (!$customerInsertStmt->execute([
                        $transaction_number,
                        $customer['id'],
                        $customer['full_name']
                    ])) {
                        throw new Exception('Failed to record customer transaction information');
                    }
                }
                
                $activity_description = "Sales - Order #: {$order['order_number']}, Item: {$inventory['item_name']}, Quantity: {$item['quantity']}";
                $activity_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : $order['user_id'];
                $activityStmt = $conn->prepare(
                    "INSERT INTO activities (
                        action_type,
                        description,
                        item_code,
                        user_id,
                        timestamp
                    ) VALUES (?, ?, ?, ?, NOW())"
                );
                if (!$activityStmt->execute([
                    'Sales',
                    $activity_description,
                    $item['item_code'],
                    $activity_user_id
                ])) {
                    throw new Exception('Failed to log activity for item: ' . $inventory['item_name']);
                }

            }
        }

        // Update order status
        if ($status === 'approved') {
            $staff_name = isset($_SESSION['name']) ? $_SESSION['name'] : '';
            $updateStmt = $conn->prepare("UPDATE orders SET status = ?, approved_by = ?, updated_at = NOW() WHERE id = ?");
            if (!$updateStmt->execute([$status, $staff_name, $order_id])) {
                throw new Exception('Failed to update order status with staff name');
            }
        } else if ($status === 'rejected' && $rejection_reason) {
            $updateStmt = $conn->prepare("UPDATE orders SET status = ?, rejection_reason = ?, updated_at = NOW() WHERE id = ?");
            if (!$updateStmt->execute([$status, $rejection_reason, $order_id])) {
                throw new Exception('Failed to update order status with rejection reason');
            }
            
            // Create sales records for rejected order - one per item (with ₱0 amount)
            $order_items = json_decode($order['items'], true);
            if ($order_items && is_array($order_items)) {
                foreach ($order_items as $item) {
                    $salesStmt = $conn->prepare("
                        INSERT INTO sales (
                            transaction_number,
                            item_code,
                            size,
                            quantity,
                            price_per_item,
                            total_amount,
                            transaction_type,
                            sale_date
                        ) VALUES (?, ?, ?, ?, ?, 0.00, 'Rejected', NOW())
                    ");
                    if (!$salesStmt->execute([
                        $order['order_number'],
                        $item['item_code'],
                        $item['size'],
                        $item['quantity'],
                        $item['price']
                    ])) {
                        throw new Exception('Failed to create sales record for rejected order item');
                    }
                }
            }
        } else {
            $updateStmt = $conn->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
            if (!$updateStmt->execute([$status, $order_id])) {
                throw new Exception('Failed to update order status');
            }
        }
        
        // If status is completed, record the payment date
        if ($status === 'completed') {
            $paymentDateStmt = $conn->prepare("UPDATE orders SET payment_date = NOW(), updated_at = NOW() WHERE id = ?");
            if (!$paymentDateStmt->execute([$order_id])) {
                throw new Exception('Failed to record payment date');
            }
            // If approved_by is still NULL, set it now
            if (empty($order['approved_by'])) {
                $staff_name = isset($_SESSION['name']) ? $_SESSION['name'] : '';
                if ($staff_name) {
                    $setStaffStmt = $conn->prepare("UPDATE orders SET approved_by = ? WHERE id = ?");
                    $setStaffStmt->execute([$staff_name, $order_id]);
                }
            }
        }

        // Log activities for status changes 
        $statusActionMap = [
            'approved'  => 'Order Accepted',
            'rejected'  => 'Order Rejected',
            'completed' => 'Sales',
            'voided'    => 'Voided',
            'cancelled' => 'Cancelled'
        ];
        if (isset($statusActionMap[$status])) {
            $order_items = json_decode($order['items'], true);
            if (!$order_items || !is_array($order_items)) {
                throw new Exception('Order items are missing or invalid for status: ' . $status);
            }
            foreach ($order_items as $item) {
                // Prevent duplicate 'Sales' activity logs
                if ($status === 'completed') {
                    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM activities WHERE action_type = 'Sales' AND item_code = ? AND description LIKE ?");
                    $descLike = "%Order #: {$order['order_number']}%";
                    $checkStmt->execute([$item['item_code'], $descLike]);
                    $alreadyLogged = $checkStmt->fetchColumn();
                    if ($alreadyLogged) {
                        continue; // Skip if already logged
                    }
                }
                $activity_description = "{$statusActionMap[$status]} - Order #: {$order['order_number']}, Item: {$item['item_name']}, Quantity: {$item['quantity']}";
                // Determine user_id for activity
                $activity_user_id = null;
                if ($status === 'cancelled') {
                    $activity_user_id = $order['user_id']; // student
                } else if ($status === 'voided') {
                    $activity_user_id = null; // system/voided
                } else {
                    $activity_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : $order['user_id']; // admin/PAMO or fallback
                }
                $activityStmt = $conn->prepare(
                    "INSERT INTO activities (
                        action_type,
                        description,
                        item_code,
                        user_id,
                        timestamp
                    ) VALUES (?, ?, ?, ?, NOW())"
                );
                $activityStmt->execute([
                    $statusActionMap[$status],
                    $activity_description,
                    $item['item_code'],
                    $activity_user_id
                ]);
            }
        }

        // Create notification message based on status  
        if ($status === 'rejected') {
            $reasonText = $rejection_reason ? "<br><span class='rejection-reason'>Reason: " . htmlspecialchars($rejection_reason) . "</span>" : "";
            $message = "Your order #{$order['order_number']} has been rejected." . $reasonText;
        } else {
            $message = "Your order #{$order['order_number']} has been " . 
                ($status === 'approved' ? 'approved! You can now proceed with the payment.' : 
                ($status === 'completed' ? 'completed. Thank you for your purchase!' : 'updated.'));
        }

        try {

            createNotification($conn, $order['user_id'], $message, $order['order_number'], $status);
        } catch (Exception $e) {
            error_log("Failed to create notification: " . $e->getMessage());
            // Continue processing even if notification fails
        }

        // Commit transaction
        $conn->commit();
        
        // Trigger real-time inventory update notification if order was completed
        if ($status === 'completed') {
            $itemCount = count($order_items);
            triggerInventoryUpdate(
                $conn, 
                'order_completion', 
                "Order #{$order['order_number']} completed - {$itemCount} item(s) sold"
            );
        }

        $response['success'] = true;
        $response['message'] = 'Order status updated successfully';
        $response['debug'] = [
            'order_id' => $order_id,
            'status' => $status,
            'order_number' => $order['order_number'],
            'user_info' => [
                'id' => $order['user_id'],
                'name' => $order['first_name'] . ' ' . $order['last_name']
            ]
        ];
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Error updating order status: " . $e->getMessage());
        $response['success'] = false;
        $response['message'] = 'Error updating order status: ' . $e->getMessage();
        $response['debug'] = [
            'order_id' => $order_id,
            'status' => $status,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ];
    }

    echo json_encode($response);
    ob_end_flush();
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    ob_end_flush();
} 