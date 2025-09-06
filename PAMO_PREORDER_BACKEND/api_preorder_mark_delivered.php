<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../Includes/connection.php';

/* Payload expectation (POST, form or JSON):
   preorder_item_id: int
   delivered: { size => qty, ... }
*/

try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) { $data = $_POST; }

    $preId = intval($data['preorder_item_id'] ?? 0);
    if ($preId <= 0) throw new Exception('preorder_item_id required');

    $delivered = $data['delivered'] ?? [];
    if (!is_array($delivered) || empty($delivered)) throw new Exception('delivered map required');

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

        // Item code should remain the pure base code (no size suffix)
        $itemCode = $base;

        // Check existing
        $check = $conn->prepare('SELECT id, actual_quantity, image_path FROM inventory WHERE item_code = ? AND sizes = ? LIMIT 1');
        $check->execute([$itemCode, $size]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $newQty = intval($existing['actual_quantity']) + $qty;
            $upd = $conn->prepare('UPDATE inventory SET actual_quantity = ?, new_delivery = new_delivery + ?, status = CASE WHEN ? <= 0 THEN status WHEN ? > 0 THEN "in stock" ELSE status END WHERE id = ?');
            $upd->execute([$newQty, $qty, $newQty, $newQty, $existing['id']]);
            // Backfill image_path to itemlist variant if needed
            if (!empty($inventoryImagePath) && (empty($existing['image_path']) || strpos($existing['image_path'], 'uploads/preorder/') === 0)) {
                $updImg = $conn->prepare('UPDATE inventory SET image_path = ? WHERE id = ?');
                $updImg->execute([$inventoryImagePath, $existing['id']]);
            }
        } else {
            $ins = $conn->prepare('INSERT INTO inventory (item_code, category, actual_quantity, new_delivery, beginning_quantity, damage, item_name, sizes, price, status, sold_quantity, image_path, created_at, RTW, category_id) VALUES (?, ?, ?, ?, 0, 0, ?, ?, ?, "in stock", 0, ?, CURRENT_TIMESTAMP, 0, ?)');
            // category column stores name; we need name from categories
            $catName = null;
            if (!empty($categoryId)) {
                $c = $conn->prepare('SELECT name FROM categories WHERE id = ?');
                $c->execute([$categoryId]);
                $catName = $c->fetchColumn();
            }
            $ins->execute([$itemCode, $catName, $qty, $qty, $itemName, $size, $price, $inventoryImagePath, $categoryId]);
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


