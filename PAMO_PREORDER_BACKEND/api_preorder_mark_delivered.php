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

    // Send notifications about delivery
    try {
        // 1. Notify customers who placed pre-orders for this item
        $preorderCustomersStmt = $conn->prepare('
            SELECT DISTINCT r.user_id, CONCAT(u.first_name, " ", u.last_name) as name 
            FROM preorder_requests r 
            JOIN account u ON r.user_id = u.id 
            WHERE r.preorder_item_id = ? AND r.status = "active"
        ');
        $preorderCustomersStmt->execute([$preId]);
        $preorderCustomers = $preorderCustomersStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Notification for customers who placed pre-orders
        $preorderMessage = "🎉 Your Pre-Order is Ready! '{$itemName}' (Order: {$orderNumber}) has been delivered and is now available for pickup/purchase!";
        
        foreach ($preorderCustomers as $customer) {
            createNotification($conn, $customer['user_id'], $preorderMessage, $orderNumber, 'preorder_delivered');
        }
        
        // 2. Notify all other customers (who didn't place pre-orders)
        $preorderCustomerIds = array_column($preorderCustomers, 'user_id');
        
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


