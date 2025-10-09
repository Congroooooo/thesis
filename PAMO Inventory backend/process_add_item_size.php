<?php
session_start();
file_put_contents(__DIR__ . '/debug_add_item_size.txt', print_r($_POST, true));
header('Content-Type: application/json');

require_once '../Includes/connection.php'; // PDO $conn

$deliveryOrderNumber = isset($_POST['deliveryOrderNumber']) ? $_POST['deliveryOrderNumber'] : '';
$itemsDataJson = isset($_POST['itemsData']) ? $_POST['itemsData'] : '';

if (empty($deliveryOrderNumber) || empty($itemsDataJson)) {
    echo json_encode(['success' => false, 'message' => 'Delivery order number and items data are required']);
    exit;
}

$itemsData = json_decode($itemsDataJson, true);
if (!$itemsData || !is_array($itemsData)) {
    echo json_encode(['success' => false, 'message' => 'Invalid items data format']);
    exit;
}

$conn->beginTransaction();

try {
    $success = true;
    $errors = [];
    $totalSizesAdded = 0;

    foreach ($itemsData as $itemIndex => $itemData) {
        $existingItem = $itemData['existingItem'] ?? '';
        $sizes = $itemData['sizes'] ?? [];

        if (empty($existingItem) || empty($sizes)) {
            $errors[] = "Missing item data for item #" . ($itemIndex + 1);
            continue;
        }

        // Get original item data
        $sql = "SELECT item_name, category, image_path FROM inventory WHERE item_code LIKE ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $prefix = $existingItem . '%';
        $stmt->execute([$prefix]);
        $originalItem = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$originalItem) {
            $errors[] = "Original item not found for prefix: {$existingItem}";
            continue;
        }

        foreach ($sizes as $sizeData) {
            $newSize = $sizeData['size'] ?? '';
            $newItemCode = $sizeData['itemCode'] ?? '';
            $newQuantity = $sizeData['quantity'] ?? 0;
            $newDamage = $sizeData['damage'] ?? 0;
            $newPrice = $sizeData['price'] ?? 0;

            if (empty($newSize) || empty($newItemCode) || $newQuantity < 1 || $newPrice <= 0) {
                $errors[] = "Invalid data for item {$originalItem['item_name']}, size {$newSize}";
                continue;
            }

            // Check if item code already exists
            $sql = "SELECT item_code FROM inventory WHERE item_code = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$newItemCode]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $errors[] = "Item code {$newItemCode} already exists";
                continue;
            }

            // Insert new size entry
            $sql = "INSERT INTO inventory (item_code, item_name, category, sizes, actual_quantity, damage, price, image_path, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            if (!$stmt->execute([
                $newItemCode,
                $originalItem['item_name'],
                $originalItem['category'],
                $newSize,
                $newQuantity,
                $newDamage,
                $newPrice,
                $originalItem['image_path']
            ])) {
                $errors[] = "Error adding size {$newSize} for {$originalItem['item_name']}";
                continue;
            }

            $new_inventory_id = (int)$conn->lastInsertId();

            // Get parent inventory ID for subcategory cloning
            $parent_sql = "SELECT id FROM inventory WHERE item_code LIKE ? ORDER BY id ASC LIMIT 1";
            $parent_stmt = $conn->prepare($parent_sql);
            $parent_stmt->execute([$prefix]);
            $parent_row = $parent_stmt->fetch(PDO::FETCH_ASSOC);
            $parent_inventory_id = $parent_row ? $parent_row['id'] : null;

            if ($parent_inventory_id) {
                // Clone subcategory links from parent to the new inventory row
                $sub_sql = "SELECT subcategory_id FROM inventory_subcategory WHERE inventory_id = ?";
                $sub_stmt = $conn->prepare($sub_sql);
                $sub_stmt->execute([$parent_inventory_id]);
                $insert_sub_stmt = $conn->prepare("INSERT IGNORE INTO inventory_subcategory (inventory_id, subcategory_id) VALUES (?, ?)");
                while ($sub_row = $sub_stmt->fetch(PDO::FETCH_ASSOC)) {
                    $insert_sub_stmt->execute([$new_inventory_id, $sub_row['subcategory_id']]);
                }
            }

            // Log activity
            $activity_description = "New size added for {$originalItem['item_name']} ({$newItemCode}) - Size: {$newSize}, Initial stock: {$newQuantity}, Damage: {$newDamage}, Price: ₱{$newPrice}, Delivery Order: {$deliveryOrderNumber}";
            $log_activity_query = "INSERT INTO activities (action_type, description, item_code, user_id, timestamp) VALUES ('Add Item Size', ?, ?, ?, NOW())";
            $stmt = $conn->prepare($log_activity_query);
            $user_id = $_SESSION['user_id'] ?? null;
            $stmt->execute([$activity_description, $newItemCode, $user_id]);

            $totalSizesAdded++;
        }
    }

    if (!empty($errors)) {
        throw new Exception(implode("\n", $errors));
    }

    if ($totalSizesAdded === 0) {
        throw new Exception("No sizes were added. Please check your data.");
    }

    $conn->commit();
    
    $successMessage = $totalSizesAdded === 1 
        ? "1 new size added successfully!" 
        : "{$totalSizesAdded} new sizes added successfully!";
        
    echo json_encode(['success' => true, 'message' => $successMessage]);

} catch (Exception $e) {
    if ($conn instanceof PDO && $conn->inTransaction()) { $conn->rollBack(); }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// PDO will close automatically
?> 