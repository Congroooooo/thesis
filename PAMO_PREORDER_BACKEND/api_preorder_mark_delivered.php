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
        $deliveredDamage = isset($sizeData['damage']) ? intval($sizeData['damage']) : 0;
        $specificItemCode = isset($sizeData['item_code']) ? trim($sizeData['item_code']) : $base;

        // Item code should remain the pure base code (no size suffix) for consistency
        $itemCode = $base;

        // Check existing
        $check = $conn->prepare('SELECT id, actual_quantity, image_path, damage FROM inventory WHERE item_code = ? AND sizes = ? LIMIT 1');
        $check->execute([$itemCode, $size]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $newQty = intval($existing['actual_quantity']) + $qty;
            $newDamage = intval($existing['damage']) + $deliveredDamage;
            $upd = $conn->prepare('UPDATE inventory SET actual_quantity = ?, new_delivery = new_delivery + ?, damage = ?, price = ?, status = CASE WHEN ? <= 0 THEN status WHEN ? > 0 THEN "in stock" ELSE status END WHERE id = ?');
            $upd->execute([$newQty, $qty, $newDamage, $deliveredPrice, $newQty, $newQty, $existing['id']]);
            // Backfill image_path to itemlist variant if needed
            if (!empty($inventoryImagePath) && (empty($existing['image_path']) || strpos($existing['image_path'], 'uploads/preorder/') === 0)) {
                $updImg = $conn->prepare('UPDATE inventory SET image_path = ? WHERE id = ?');
                $updImg->execute([$inventoryImagePath, $existing['id']]);
            }
        } else {
            $ins = $conn->prepare('INSERT INTO inventory (item_code, category, actual_quantity, new_delivery, beginning_quantity, damage, item_name, sizes, price, status, sold_quantity, image_path, created_at, RTW, category_id) VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, "in stock", 0, ?, CURRENT_TIMESTAMP, 0, ?)');
            // category column stores name; we need name from categories
            $catName = null;
            if (!empty($categoryId)) {
                $c = $conn->prepare('SELECT name FROM categories WHERE id = ?');
                $c->execute([$categoryId]);
                $catName = $c->fetchColumn();
            }
            $ins->execute([$itemCode, $catName, $qty, $qty, $deliveredDamage, $itemName, $size, $deliveredPrice, $inventoryImagePath, $categoryId]);
        }
    }

    // Copy subcategory tags
    $subs = $conn->prepare('SELECT subcategory_id FROM preorder_item_subcategory WHERE preorder_item_id = ?');
    $subs->execute([$preId]);
    $subRows = $subs->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($subRows)) {
        foreach ($delivered as $size => $qty) {
            if ($qty <= 0) continue;
            $itemCode = $base; // pure base code
            $invIdStmt = $conn->prepare('SELECT id FROM inventory WHERE item_code = ? AND sizes = ? LIMIT 1');
            $invIdStmt->execute([$itemCode, $size]);
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
        // Get the highest order number from both orders and sales tables (to avoid duplicates)
        $date = date('md');
        $likePattern = 'SI-' . $date . '-%';
        $maxStmt = $conn->prepare("
            SELECT MAX(seq) AS max_seq FROM (
                SELECT CAST(SUBSTRING(order_number, 10) AS UNSIGNED) AS seq
                FROM orders
                WHERE order_number LIKE ?
                UNION ALL
                SELECT CAST(SUBSTRING(transaction_number, 10) AS UNSIGNED) AS seq
                FROM sales
                WHERE transaction_number LIKE ?
            ) AS all_orders
        ");
        $maxStmt->execute([$likePattern, $likePattern]);
        $row = $maxStmt->fetch(PDO::FETCH_ASSOC);
        $maxNumber = $row && $row['max_seq'] ? (int)$row['max_seq'] : 0;
        $orderNumber = 'SI-' . $date . '-' . str_pad($maxNumber + 1, 6, '0', STR_PAD_LEFT);
        
        // Update items JSON to use proper inventory item codes and add category
        $items = json_decode($preorder['items'], true);
        if (is_array($items)) {
            foreach ($items as &$item) {
                $size = $item['size'] ?? 'One Size';
                $sizeNumber = $getSizeNumber($size);
                $item['item_code'] = $base . '-' . str_pad($sizeNumber, 3, '0', STR_PAD_LEFT);
                $item['category'] = $catName ?? 'Uncategorized';
            }
            unset($item);
        }
        $updatedItemsJson = json_encode($items);
        
        // Create entry in orders table with status 'approved' (ready for payment)
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
            $generatedOrderNumber,
            $preorder['user_id'],
            $updatedItemsJson,
            '',
            $preorder['total_amount']
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
        
        // Send notification to customer about order conversion
        $notificationMessage = "🎉 Great news! Your pre-order '{$itemName}' (PRE-ORDER #: {$preorder['preorder_number']}) has been delivered and is ready for pickup! " .
                             "Your order has been automatically approved (ORDER #: {$generatedOrderNumber}). " .
                             "Please download your e-slip, pay at the cashier, and claim your item within 5 minutes.";
        
        createNotification($conn, $preorder['user_id'], $notificationMessage, $generatedOrderNumber, 'preorder_delivered');
        
        $convertedCount++;
    }

    // Send notifications to OTHER customers (who didn't pre-order) about new stock
    try {
        // Get list of customers who placed pre-orders (already notified above)
        $preorderCustomerIds = array_column($preorderOrders, 'user_id');
        
        if (!empty($preorderCustomerIds)) {
            $placeholders = str_repeat('?,', count($preorderCustomerIds) - 1) . '?';
            $otherCustomersStmt = $conn->prepare("
                SELECT id as user_id FROM account 
                WHERE role_category = 'COLLEGE STUDENT' AND status = 'active' 
                AND id NOT IN ({$placeholders})
            ");
            $otherCustomersStmt->execute($preorderCustomerIds);
        } else {
            $otherCustomersStmt = $conn->prepare("
                SELECT id as user_id FROM account 
                WHERE role_category = 'COLLEGE STUDENT' AND status = 'active'
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


