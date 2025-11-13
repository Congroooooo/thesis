<?php
date_default_timezone_set('Asia/Manila');
// Prevent any output before headers
ob_start();
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

// Start session first
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// Clean any previous output and set headers
ob_clean();
header('Content-Type: application/json');

require_once __DIR__ . '/../Includes/connection.php';
require_once __DIR__ . '/../Includes/notifications.php';

/* Payload expectation (POST, form or JSON):
   preorder_item_id: int
   order_number: string
   delivered: { size => qty, ... }
   delivered_data: { size => {price, quantity, damage, item_code, size}, ... }
*/

try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) { $data = $_POST; }

    $preId = intval($data['preorder_item_id'] ?? 0);
    if ($preId <= 0) throw new Exception('preorder_item_id required');

    $orderNumber = trim($data['order_number'] ?? '');
    if ($orderNumber === '') throw new Exception('order_number is required');

    $delivered = $data['delivered'] ?? [];
    if (!is_array($delivered) || empty($delivered)) throw new Exception('delivered map required');
    
    $deliveredData = $data['delivered_data'] ?? [];
    if (!is_array($deliveredData)) $deliveredData = [];

    // Load preorder item
    $stmt = $conn->prepare('SELECT * FROM preorder_items WHERE id = ?');
    $stmt->execute([$preId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item) throw new Exception('Preorder item not found');

    $base = $item['base_item_code'];
    $price = floatval($item['price']);
    $categoryId = $item['category_id'];
    $imagePath = $item['image_path'];
    $itemName = $item['item_name'];

    // Ensure inventory images live under uploads/itemlist for consistency across the app
    $inventoryImagePath = $imagePath;
    if (!empty($imagePath) && strpos($imagePath, 'uploads/preorder/') === 0) {
        $fileName = basename($imagePath);
        $src = dirname(__DIR__) . '/uploads/preorder/' . $fileName;
        $destDir = dirname(__DIR__) . '/uploads/itemlist/';
        if (!is_dir($destDir)) { @mkdir($destDir, 0777, true); }
        $dest = $destDir . $fileName;
        if (is_file($src) && !is_file($dest)) {
            @copy($src, $dest);
        }
        $inventoryImagePath = 'uploads/itemlist/' . $fileName;
    }

    $conn->beginTransaction();

    // For each delivered size, merge into inventory (add or insert)
    foreach ($delivered as $size => $qty) {
        $size = trim($size);
        $qty = intval($qty);
        if ($qty <= 0 || $size === '') continue;

        // Get detailed data if available
        $sizeData = $deliveredData[$size] ?? [];
        $deliveredPrice = isset($sizeData['price']) ? floatval($sizeData['price']) : $price;
        
        // Use the suffixed item code from frontend (e.g., PREORDER2-001 for XS)
        // This matches the format used when adding items through Inventory page
        $itemCode = isset($sizeData['item_code']) && !empty($sizeData['item_code']) 
                    ? trim($sizeData['item_code']) 
                    : $base; // fallback to base if not provided

        // Check existing inventory by item_code (suffixed code like PREORDER2-001)
        // No need to check by sizes column since item_code is unique per size
        $check = $conn->prepare('SELECT id, actual_quantity, image_path FROM inventory WHERE item_code = ? LIMIT 1');
        $check->execute([$itemCode]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $newQty = intval($existing['actual_quantity']) + $qty;
            $upd = $conn->prepare('UPDATE inventory SET actual_quantity = ?, new_delivery = new_delivery + ?, price = ?, status = CASE WHEN ? <= 0 THEN status WHEN ? > 0 THEN "in stock" ELSE status END WHERE id = ?');
            $upd->execute([$newQty, $qty, $deliveredPrice, $newQty, $newQty, $existing['id']]);
            // Backfill image_path to itemlist variant if needed
            if (!empty($inventoryImagePath) && (empty($existing['image_path']) || strpos($existing['image_path'], 'uploads/preorder/') === 0)) {
                $updImg = $conn->prepare('UPDATE inventory SET image_path = ? WHERE id = ?');
                $updImg->execute([$inventoryImagePath, $existing['id']]);
            }
        } else {
            $ins = $conn->prepare('INSERT INTO inventory (item_code, category, actual_quantity, new_delivery, beginning_quantity, item_name, sizes, price, status, sold_quantity, image_path, created_at, RTW, category_id) VALUES (?, ?, ?, ?, 0, ?, ?, ?, "in stock", 0, ?, CURRENT_TIMESTAMP, 0, ?)');
            // category column stores name; we need name from categories
            $catName = null;
            if (!empty($categoryId)) {
                $c = $conn->prepare('SELECT name FROM categories WHERE id = ?');
                $c->execute([$categoryId]);
                $catName = $c->fetchColumn();
            }
            $ins->execute([$itemCode, $catName, $qty, $qty, $itemName, $size, $deliveredPrice, $inventoryImagePath, $categoryId]);
        }
    }

    // Copy subcategory tags
    $subs = $conn->prepare('SELECT subcategory_id FROM preorder_item_subcategory WHERE preorder_item_id = ?');
    $subs->execute([$preId]);
    $subRows = $subs->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($subRows)) {
        foreach ($delivered as $size => $qty) {
            if ($qty <= 0) continue;
            
            // Get the suffixed item code for this size from deliveredData
            $sizeData = $deliveredData[$size] ?? [];
            $itemCode = isset($sizeData['item_code']) && !empty($sizeData['item_code']) 
                        ? trim($sizeData['item_code']) 
                        : $base; // fallback
            
            // Find inventory ID by the suffixed item_code
            $invIdStmt = $conn->prepare('SELECT id FROM inventory WHERE item_code = ? LIMIT 1');
            $invIdStmt->execute([$itemCode]);
            $invId = $invIdStmt->fetchColumn();
            if ($invId) {
                $insLink = $conn->prepare('INSERT IGNORE INTO inventory_subcategory (inventory_id, subcategory_id) VALUES (?, ?)');
                foreach ($subRows as $sid) {
                    $insLink->execute([intval($invId), intval($sid)]);
                }
            }
        }
    }

    // Mark preorder as delivered
    $updPre = $conn->prepare("UPDATE preorder_items SET status='delivered', updated_at=CURRENT_TIMESTAMP WHERE id = ?");
    $updPre->execute([$preId]);

    // Convert pending pre-orders to regular orders (5 minute validation period)
    $preorderOrdersStmt = $conn->prepare("
        SELECT * FROM preorder_orders 
        WHERE preorder_item_id = ? AND status = 'pending'
        ORDER BY created_at ASC
    ");
    $preorderOrdersStmt->execute([$preId]);
    $preorderOrders = $preorderOrdersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $convertedCount = 0;
    $validationDeadline = date('Y-m-d H:i:s', strtotime('+5 minutes'));
    
    // Get category name for adding to order items
    $catName = null;
    if (!empty($categoryId)) {
        $catStmt = $conn->prepare('SELECT name FROM categories WHERE id = ?');
        $catStmt->execute([$categoryId]);
        $catName = $catStmt->fetchColumn();
    }
    
    // Helper function to get size number
    $getSizeNumber = function($size) {
        $sizeMap = [
            'One Size' => 0, 'XS' => 1, 'S' => 2, 'M' => 3, 'L' => 4,
            'XL' => 5, 'XXL' => 6, '3XL' => 7, '4XL' => 8,
            '5XL' => 9, '6XL' => 10, '7XL' => 11
        ];
        return $sizeMap[$size] ?? 99;
    };
    
    foreach ($preorderOrders as $preorder) {
        // Generate unique order number: SI-MMDD-NNNNNN (based on conversion date)
        // Check both orders and sales tables to avoid conflicts with physical shop
        $date = date('md');
        $date_key = 'SI-' . $date;
        $like_pattern = $date_key . '-%';
        
        // Check max sequence from BOTH orders and sales tables (online + physical shop)
        $checkStmt = $conn->prepare("
            SELECT MAX(seq) AS max_seq FROM (
                SELECT CAST(SUBSTRING(order_number, 10) AS UNSIGNED) AS seq
                FROM orders
                WHERE order_number LIKE ?
                UNION ALL
                SELECT CAST(SUBSTRING(transaction_number, 10) AS UNSIGNED) AS seq
                FROM sales
                WHERE transaction_number LIKE ?
            ) AS all_transactions
        ");
        $checkStmt->execute([$like_pattern, $like_pattern]);
        $checkRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
        $max_from_tables = $checkRow && $checkRow['max_seq'] ? (int)$checkRow['max_seq'] : 0;
        
        // Get or create sequence counter with row lock
        $seqStmt = $conn->prepare("
            INSERT INTO order_sequence (date_key, last_sequence, updated_at) 
            VALUES (?, 0, NOW())
            ON DUPLICATE KEY UPDATE updated_at = NOW()
        ");
        $seqStmt->execute([$date_key]);
        
        $seqStmt = $conn->prepare("
            SELECT last_sequence 
            FROM order_sequence 
            WHERE date_key = ? 
            FOR UPDATE
        ");
        $seqStmt->execute([$date_key]);
        $row = $seqStmt->fetch(PDO::FETCH_ASSOC);
        $seq_from_table = $row ? (int)$row['last_sequence'] : 0;
        
        // Use the higher value to ensure no conflicts with physical shop sales
        $new_seq = max($seq_from_table, $max_from_tables) + 1;
        
        // Update sequence counter to the new value
        $updateSeqStmt = $conn->prepare("
            UPDATE order_sequence 
            SET last_sequence = ?, updated_at = NOW() 
            WHERE date_key = ?
        ");
        $updateSeqStmt->execute([$new_seq, $date_key]);
        
        $orderNumber = sprintf('SI-%s-%06d', $date, $new_seq);
        
        // Update items JSON to use proper suffixed inventory item codes, update prices, and add category
        $items = json_decode($preorder['items'], true);
        $priceChanges = []; // Track items with price differences
        $newTotalAmount = 0;
        
        if (is_array($items)) {
            foreach ($items as &$item) {
                $size = $item['size'] ?? 'One Size';
                
                // Get the delivered data for this size
                $sizeData = $deliveredData[$size] ?? [];
                
                // Query inventory to get the ACTUAL item code that exists
                // This ensures we match exactly what's in the inventory table
                if (isset($sizeData['item_code']) && !empty($sizeData['item_code'])) {
                    $suffixedItemCode = trim($sizeData['item_code']);
                } else {
                    // Fallback: generate suffixed item code and verify it exists
                    $sizeNumber = $getSizeNumber($size);
                    $suffixedItemCode = $base . '-' . str_pad($sizeNumber, 3, '0', STR_PAD_LEFT);
                }
                
                // Verify the item code exists in inventory and get the actual price
                $invCheckStmt = $conn->prepare('SELECT item_code, price FROM inventory WHERE item_code = ? OR (item_code LIKE ? AND sizes = ?) LIMIT 1');
                $invCheckStmt->execute([$suffixedItemCode, $base . '%', $size]);
                $invRecord = $invCheckStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($invRecord) {
                    // Use the exact item_code from inventory
                    $suffixedItemCode = $invRecord['item_code'];
                    $deliveredPrice = floatval($invRecord['price']);
                } else {
                    // If not found, use the delivered data or original price
                    $deliveredPrice = isset($sizeData['price']) ? floatval($sizeData['price']) : floatval($item['price']);
                }
                
                // Track price changes
                $originalPrice = floatval($item['price']);
                if (abs($deliveredPrice - $originalPrice) > 0.01) { // Use small epsilon for float comparison
                    $priceChanges[] = [
                        'item_name' => $itemName,
                        'size' => $size,
                        'original_price' => $originalPrice,
                        'new_price' => $deliveredPrice,
                        'quantity' => $item['quantity']
                    ];
                }
                
                // Update item with new price and suffixed code
                $item['item_code'] = $suffixedItemCode;
                $item['price'] = $deliveredPrice; // Update to delivery price
                $item['size'] = $size; // Ensure size is preserved
                $item['category'] = $catName ?? 'Uncategorized';
                
                // Calculate new total
                $newTotalAmount += $deliveredPrice * $item['quantity'];
            }
            unset($item);
        }
        $updatedItemsJson = json_encode($items);
        
        // Create entry in orders table with UPDATED total amount
        $insertOrderStmt = $conn->prepare("
            INSERT INTO orders (
                order_number,
                user_id,
                items,
                phone,
                total_amount,
                status,
                created_at
            ) VALUES (?, ?, ?, ?, ?, 'approved', NOW())
        ");
        
        $insertOrderStmt->execute([
            $orderNumber,
            $preorder['user_id'],
            $updatedItemsJson,
            '',
            $newTotalAmount // Use recalculated total with new prices
        ]);
        
        $newOrderId = $conn->lastInsertId();
        
        // Update preorder_orders: mark as delivered and link to converted order
        $updatePreorderStmt = $conn->prepare("
            UPDATE preorder_orders 
            SET status = 'delivered',
                delivered_at = NOW(),
                validation_deadline = ?,
                converted_to_order_id = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $updatePreorderStmt->execute([
            $validationDeadline,
            $newOrderId,
            $preorder['id']
        ]);
        
        // Send notification to customer - include price change details if any
        if (!empty($priceChanges)) {
            // Build detailed price change message
            $priceChangeDetails = "<br><br><strong>⚠️ Price Updates:</strong><br>";
            foreach ($priceChanges as $change) {
                $priceDiff = $change['new_price'] - $change['original_price'];
                $diffSymbol = $priceDiff > 0 ? '▲' : '▼';
                $diffColor = $priceDiff > 0 ? 'red' : 'green';
                
                $priceChangeDetails .= sprintf(
                    "• <strong>%s</strong> (Size: %s)<br>" .
                    "&nbsp;&nbsp;Pre-order price: ₱%.2f<br>" .
                    "&nbsp;&nbsp;Delivery price: <span style='color:%s'>₱%.2f</span> " .
                    "(<span style='color:%s'>%s ₱%.2f</span>)<br>" .
                    "&nbsp;&nbsp;Qty: %d | Total: <strong>₱%.2f</strong><br>",
                    $change['item_name'],
                    $change['size'],
                    $change['original_price'],
                    $diffColor,
                    $change['new_price'],
                    $diffColor,
                    $diffSymbol,
                    abs($priceDiff),
                    $change['quantity'],
                    $change['new_price'] * $change['quantity']
                );
            }
            
            $originalTotal = floatval($preorder['total_amount']);
            $totalDiff = $newTotalAmount - $originalTotal;
            $totalDiffSymbol = $totalDiff > 0 ? '▲' : '▼';
            $totalDiffColor = $totalDiff > 0 ? 'red' : 'green';
            
            $priceChangeDetails .= sprintf(
                "<br><strong>Total Amount:</strong><br>" .
                "• Original: ₱%.2f<br>" .
                "• Updated: <span style='color:%s'><strong>₱%.2f</strong></span> " .
                "(<span style='color:%s'>%s ₱%.2f</span>)",
                $originalTotal,
                $totalDiffColor,
                $newTotalAmount,
                $totalDiffColor,
                $totalDiffSymbol,
                abs($totalDiff)
            );
            
            $notificationMessage = "🎉 Your pre-order '{$itemName}' (PRE-ORDER #: {$preorder['preorder_number']}) has been delivered!" .
                                 $priceChangeDetails .
                                 "<br><br>Your order has been approved (ORDER #: {$orderNumber}). " .
                                 "Please download your e-slip, pay at the cashier, and claim your item within 5 minutes.";
        } else {
            // No price changes - standard message
            $notificationMessage = "🎉 Great news! Your pre-order '{$itemName}' (PRE-ORDER #: {$preorder['preorder_number']}) has been delivered and is ready for pickup! " .
                                 "Your order has been automatically approved (ORDER #: {$orderNumber}). " .
                                 "Please download your e-slip, pay at the cashier, and claim your item within 5 minutes.";
        }
        
        createNotification($conn, $preorder['user_id'], $notificationMessage, $orderNumber, 'preorder_delivered');
        
        $convertedCount++;
    }

    // Send notifications to OTHER customers (who didn't pre-order) about new stock
    try {
        // Get list of customers who placed pre-orders (already notified above)
        $preorderCustomerIds = array_column($preorderOrders, 'user_id');
        
        if (!empty($preorderCustomerIds)) {
            $placeholders = str_repeat('?,', count($preorderCustomerIds) - 1) . '?';
            // Include all customers (students + employees) except PAMO/Admin and those who already pre-ordered
            $otherCustomersStmt = $conn->prepare("
                SELECT id as user_id FROM account 
                WHERE status = 'active'
                AND role_category IN ('COLLEGE STUDENT', 'SHS', 'EMPLOYEE')
                AND (program_or_position IS NULL OR program_or_position NOT IN ('PAMO', 'ADMIN'))
                AND id NOT IN ({$placeholders})
            ");
            $otherCustomersStmt->execute($preorderCustomerIds);
        } else {
            // Include all customers (students + employees) except PAMO/Admin
            $otherCustomersStmt = $conn->prepare("
                SELECT id as user_id FROM account 
                WHERE status = 'active'
                AND role_category IN ('COLLEGE STUDENT', 'SHS', 'EMPLOYEE')
                AND (program_or_position IS NULL OR program_or_position NOT IN ('PAMO', 'ADMIN'))
            ");
            $otherCustomersStmt->execute();
        }
        $otherCustomers = $otherCustomersStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Notification for general customers
        $generalMessage = "🛍️ New Item Available! '{$itemName}' is now in stock and ready for purchase. Check it out!";
        
        foreach ($otherCustomers as $customerId) {
            createNotification($conn, $customerId, $generalMessage, $orderNumber, 'item_available');
        }
    } catch (Throwable $e) { 
        // Non-critical error, don't fail the request 
    }

    // Update monthly inventory snapshots using MonthlyInventoryManager
    try {
        require_once __DIR__ . '/../Includes/MonthlyInventoryManager.php';
        $monthlyInventory = new MonthlyInventoryManager($conn);
        
        // Get current user for processed_by field
        $processedBy = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        
        // Generate unique delivery order number for this pre-order delivery
        $deliveryOrderNumber = 'PREORDER-DELIVERY-' . $orderNumber . '-' . date('YmdHis');
        
        // Record each delivered item using the proper MonthlyInventoryManager workflow
        foreach ($delivered as $size => $qty) {
            $size = trim($size);
            $qty = intval($qty);
            if ($qty <= 0 || $size === '') continue;
            
            // Get the suffixed item code for this size
            $sizeData = $deliveredData[$size] ?? [];
            $itemCode = isset($sizeData['item_code']) && !empty($sizeData['item_code']) 
                        ? trim($sizeData['item_code']) 
                        : $base; // fallback
            
            // Use recordDelivery() to properly track the delivery
            // This creates delivery_records entry AND updates snapshots correctly
            // useTransaction=false because we're already in a transaction
            $monthlyInventory->recordDelivery(
                $deliveryOrderNumber,
                $itemCode,
                $qty,
                $processedBy,
                false  // Don't use internal transaction since we're already in one
            );
        }
    } catch (Throwable $e) { 
        // This is critical - if monthly inventory tracking fails, we should know
        error_log("Monthly inventory update failed for pre-order delivery: " . $e->getMessage());
        throw new Exception("Failed to update monthly inventory tracking: " . $e->getMessage());
    }
    
    // Audit trail: log this delivery action
    try {
        $userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
        $totalQty = array_sum($delivered);
        $sizeDetails = [];
        foreach ($delivered as $size => $qty) {
            $sizeDetails[] = "{$size}({$qty})";
        }
        $desc = sprintf('Pre-order delivered to inventory → Item: %s, Order: %s, Total Qty: %d, Sizes: %s', 
                       $itemName, $orderNumber, $totalQty, implode(', ', $sizeDetails));
        $log = $conn->prepare('INSERT INTO activities (action_type, description, item_code, user_id) VALUES (?, ?, ?, ?)');
        $log->execute(['PreOrder Delivered', $desc, $base, $userId]);
    } catch (Throwable $e) { /* best-effort logging */ }

    $conn->commit();
    echo json_encode(['success' => true]);
    exit;
} catch (Throwable $e) {
    if ($conn && $conn->inTransaction()) $conn->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>


