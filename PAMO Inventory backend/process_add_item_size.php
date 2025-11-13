<?php
session_start();
header('Content-Type: application/json');

require_once '../Includes/connection.php'; // PDO $conn
require_once '../Includes/MonthlyInventoryManager.php'; // Monthly inventory manager
require_once '../Includes/inventory_update_notifier.php';

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

// Create MonthlyInventoryManager instance
$monthlyInventory = new MonthlyInventoryManager($conn);

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

        // Get original item data including category_id, RTW and period info
        $sql = "SELECT item_name, category, category_id, image_path, RTW, inventory_period_id FROM inventory WHERE item_code LIKE ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $prefix = $existingItem . '%';
        $stmt->execute([$prefix]);
        $originalItem = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$originalItem) {
            $errors[] = "Original item not found for prefix: {$existingItem}";
            continue;
        }

        include_once '../PAMO_PAGES/includes/config_functions.php';
        $lowStockThreshold = getLowStockThreshold($conn);
        $currentPeriodId = $monthlyInventory->getCurrentPeriodId();

        foreach ($sizes as $sizeData) {
            $newSize = $sizeData['size'] ?? '';
            $newItemCode = $sizeData['itemCode'] ?? '';
            $newQuantity = $sizeData['quantity'] ?? 0;
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

            // For new item sizes, beginning quantity is 0 for the current month
            // New delivery represents the initial stock received
            $beginning_quantity = 0;
            $new_delivery = $newQuantity;
            $actual_quantity = $beginning_quantity + $new_delivery;
            $sold_quantity = 0;
            $status = ($actual_quantity <= 0) ? 'Out of Stock' : (($actual_quantity <= $lowStockThreshold) ? 'Low Stock' : 'In Stock');

            // Insert new size entry with monthly inventory tracking
            $sql = "INSERT INTO inventory (
                item_code, category_id, category, item_name, sizes, price,
                actual_quantity, new_delivery, beginning_quantity,
                sold_quantity, status, image_path, RTW, created_at,
                current_month_deliveries, current_month_sales, inventory_period_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt->execute([
                $newItemCode,
                $originalItem['category_id'],
                $originalItem['category'],
                $originalItem['item_name'],
                $newSize,
                $newPrice,
                $actual_quantity,
                $new_delivery,
                $beginning_quantity,
                $sold_quantity,
                $status,
                $originalItem['image_path'],
                $originalItem['RTW'],
                $newQuantity, // current_month_deliveries
                0, // current_month_sales
                $currentPeriodId
            ])) {
                $errors[] = "Error adding size {$newSize} for {$originalItem['item_name']}";
                continue;
            }

            $new_inventory_id = (int)$conn->lastInsertId();

            // Initialize the item in the monthly inventory system
            // Pass the delivery order number so it gets recorded in delivery_records
            $monthlyInventory->initializeNewItem($newItemCode, $newQuantity, $deliveryOrderNumber);

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
            $activity_description = "New size added for {$originalItem['item_name']} ({$newItemCode}) - Size: {$newSize}, Initial stock: {$newQuantity}, Price: ₱{$newPrice}, Delivery Order: {$deliveryOrderNumber}";
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
    
    // Trigger real-time inventory update notification
    triggerInventoryUpdate(
        $conn, 
        'add_size', 
        "{$totalSizesAdded} new size(s) added via delivery #{$deliveryOrderNumber}"
    );
    
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