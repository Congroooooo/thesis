<?php
date_default_timezone_set('Asia/Manila');
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once '../Includes/connection.php';
require_once '../Includes/notifications.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    

    
    // Validate input parameters
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
        // Start transaction
        $conn->beginTransaction();



        // Get order details first
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
            
            // If any items have insufficient stock, automatically reject the order
            if (!empty($insufficientStockItems)) {
                // Build rejection reason message for database (plain text)
                $rejectionMessage = "Order automatically rejected due to insufficient remaining stock:\n\n";
                foreach ($insufficientStockItems as $stockItem) {
                    $rejectionMessage .= "• {$stockItem['item_name']} (Size: {$stockItem['size']})\n";
                    $rejectionMessage .= "  Requested: {$stockItem['requested']}\n";
                    $rejectionMessage .= "  Available: {$stockItem['available']}";
                    if ($stockItem['reserved'] > 0) {
                        $rejectionMessage .= " (Physical stock: {$stockItem['physical_stock']}, Reserved by other orders: {$stockItem['reserved']})";
                    }
                    $rejectionMessage .= "\n\n";
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
                

                
                // Verify sufficient quantity
                $new_quantity = $inventory['actual_quantity'] - $item['quantity'];
                if ($new_quantity < 0) {
                    throw new Exception('Insufficient quantity for item: ' . $inventory['item_name']);
                }
                
                // Update inventory with optimistic locking
                $updateStockStmt = $conn->prepare(
                    "UPDATE inventory 
                    SET actual_quantity = ?,
                        sold_quantity = sold_quantity + ?,
                        status = CASE 
                            WHEN ? <= 0 THEN 'Out of Stock'
                            WHEN ? <= 10 THEN 'Low Stock'
                            ELSE 'In Stock'
                        END
                    WHERE item_code = ? AND actual_quantity = ?"
                );
                
                if (!$updateStockStmt->execute([
                    $new_quantity,
                    $item['quantity'],
                    $new_quantity,
                    $new_quantity,
                    $item['item_code'],
                    $inventory['actual_quantity']
                ])) {
                    throw new Exception('Failed to update inventory for item: ' . $inventory['item_name']);
                }
                
                if ($updateStockStmt->rowCount() === 0) {
                    throw new Exception('Item ' . $inventory['item_name'] . ' was modified by another transaction. Please try again.');
                }
                

                
                // Record the sale
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
                
                // Update monthly inventory snapshots
                try {
                    $currentYear = date('Y');
                    $currentMonth = date('n');
                    
                    // Get or create current period
                    $periodStmt = $conn->prepare("SELECT id FROM monthly_inventory_periods WHERE year = ? AND month = ?");
                    $periodStmt->execute([$currentYear, $currentMonth]);
                    $period = $periodStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$period) {
                        // Create period if it doesn't exist
                        $periodStart = date('Y-m-01');
                        $periodEnd = date('Y-m-t');
                        $createPeriodStmt = $conn->prepare("
                            INSERT INTO monthly_inventory_periods (year, month, period_start, period_end, is_closed)
                            VALUES (?, ?, ?, ?, 0)
                        ");
                        $createPeriodStmt->execute([$currentYear, $currentMonth, $periodStart, $periodEnd]);
                        $periodId = $conn->lastInsertId();
                    } else {
                        $periodId = $period['id'];
                    }
                    
                    // Check if snapshot exists for this item
                    $snapshotStmt = $conn->prepare("
                        SELECT id, sales_total, ending_quantity 
                        FROM monthly_inventory_snapshots 
                        WHERE period_id = ? AND item_code = ?
                    ");
                    $snapshotStmt->execute([$periodId, $item['item_code']]);
                    $snapshot = $snapshotStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($snapshot) {
                        // Update existing snapshot - add to sales and update ending quantity
                        $updateSnapshotStmt = $conn->prepare("
                            UPDATE monthly_inventory_snapshots 
                            SET sales_total = sales_total + ?,
                                ending_quantity = ending_quantity - ?,
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $updateSnapshotStmt->execute([
                            $item['quantity'],
                            $item['quantity'],
                            $snapshot['id']
                        ]);
                    } else {
                        // Create new snapshot for this item
                        // Get current inventory values
                        $invStmt = $conn->prepare("
                            SELECT actual_quantity, new_delivery, beginning_quantity, damage, sold_quantity 
                            FROM inventory 
                            WHERE item_code = ?
                        ");
                        $invStmt->execute([$item['item_code']]);
                        $invData = $invStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($invData) {
                            $insertSnapshotStmt = $conn->prepare("
                                INSERT INTO monthly_inventory_snapshots (
                                    period_id, item_code, beginning_quantity, new_delivery_total, 
                                    sales_total, damage_total, ending_quantity, created_at, updated_at
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                            ");
                            $insertSnapshotStmt->execute([
                                $periodId,
                                $item['item_code'],
                                $invData['beginning_quantity'],
                                $invData['new_delivery'],
                                $item['quantity'], // This sale
                                $invData['damage'],
                                $invData['actual_quantity'] // Current quantity after deduction
                            ]);
                        }
                    }
                } catch (Exception $snapshotError) {
                    // Log error but don't fail the transaction
                    error_log("Failed to update monthly snapshot: " . $snapshotError->getMessage());
                }

            }
        }

        // Update order status
        if ($status === 'approved') {
            $staff_name = isset($_SESSION['first_name'], $_SESSION['last_name']) ? $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] : '';
            $updateStmt = $conn->prepare("UPDATE orders SET status = ?, approved_by = ?, updated_at = NOW() WHERE id = ?");
            if (!$updateStmt->execute([$status, $staff_name, $order_id])) {
                throw new Exception('Failed to update order status with staff name');
            }
        } else if ($status === 'rejected' && $rejection_reason) {
            $updateStmt = $conn->prepare("UPDATE orders SET status = ?, rejection_reason = ?, updated_at = NOW() WHERE id = ?");
            if (!$updateStmt->execute([$status, $rejection_reason, $order_id])) {
                throw new Exception('Failed to update order status with rejection reason');
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
                $staff_name = isset($_SESSION['first_name'], $_SESSION['last_name']) ? $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] : '';
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