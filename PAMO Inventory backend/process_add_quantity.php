<?php
/**
 * Process Add Quantity - Fixed Transaction Handling Version
 * This version properly handles transaction conflicts
 */

// Disable error display in output
error_reporting(0);
ini_set('display_errors', 0);

// Set JSON header
header('Content-Type: application/json');

try {
    require_once '../Includes/connection.php'; // PDO $conn
    require_once '../Includes/MonthlyInventoryManager.php'; // Monthly inventory manager

    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Make sure we don't have any existing transactions
    while ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    // Get and validate form data
    $orderNumber = isset($_POST['orderNumber']) ? trim($_POST['orderNumber']) : '';
    if (empty($orderNumber)) {
        throw new Exception('Order number is required');
    }

    // Validate arrays
    if (!isset($_POST['itemId']) || !isset($_POST['quantityToAdd'])) {
        throw new Exception('Missing item data');
    }

    $itemIds = is_array($_POST['itemId']) ? $_POST['itemId'] : [$_POST['itemId']];
    $quantitiesToAdd = is_array($_POST['quantityToAdd']) ? $_POST['quantityToAdd'] : [$_POST['quantityToAdd']];

    if (count($itemIds) !== count($quantitiesToAdd)) {
        throw new Exception('Mismatched item and quantity data');
    }

    if (empty($itemIds)) {
        throw new Exception('No items provided');
    }

    // Validate all items before starting transaction
    $validatedItems = [];
    foreach ($itemIds as $i => $itemId) {
        $itemId = trim((string)$itemId);
        $quantity = intval($quantitiesToAdd[$i]);

        if (empty($itemId)) {
            throw new Exception("Empty item ID at position " . ($i + 1));
        }

        if ($quantity <= 0) {
            throw new Exception("Invalid quantity for item $itemId: must be greater than 0");
        }

        // Check if item exists (without starting a transaction)
        $stmt = $conn->prepare("SELECT item_code FROM inventory WHERE item_code = ?");
        if (!$stmt->execute([$itemId])) {
            throw new Exception("Database error");
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new Exception("Item not found: $itemId");
        }

        $validatedItems[] = [
            'itemId' => $itemId,
            'quantity' => $quantity
        ];
    }

    // Create MonthlyInventoryManager instance (this might create periods if needed)
    $monthlyInventory = new MonthlyInventoryManager($conn);
    
    // Make sure no transaction was accidentally started
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    // Now start our main transaction
    $conn->beginTransaction();

    // Process each validated item
    foreach ($validatedItems as $item) {
        // Get current quantities with row lock
        $sql = "SELECT actual_quantity, new_delivery, beginning_quantity FROM inventory WHERE item_code = ? FOR UPDATE";
        $stmt = $conn->prepare($sql);
        if (!$stmt->execute([$item['itemId']])) {
            throw new Exception("Database error");
        }
        $currentItem = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$currentItem) {
            throw new Exception("Item not found: {$item['itemId']}");
        }

        // Record the delivery in the monthly inventory system
        $user_id = $_SESSION['user_id'] ?? null;
        if ($user_id === null) {
            throw new Exception("User not logged in");
        }
        
        $monthlyInventory->recordDelivery(
            $orderNumber, 
            $item['itemId'], 
            $item['quantity'], 
            $user_id,
            false  // Don't use internal transaction since we're already in one
        );
        
        // Update the delivery date
        $stmt = $conn->prepare("UPDATE inventory SET date_delivered = NOW() WHERE item_code = ?");
        $stmt->execute([$item['itemId']]);

        // Get updated quantities for logging
        $stmt = $conn->prepare("SELECT beginning_quantity, actual_quantity FROM inventory WHERE item_code = ?");
        $stmt->execute([$item['itemId']]);
        $updatedItem = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $beginning_quantity = $updatedItem['beginning_quantity'];
        $actual_quantity = $updatedItem['actual_quantity'];
        
        // Log the activity
        $activity_description = "New delivery added - Order #: $orderNumber, Item: {$item['itemId']}, Quantity: {$item['quantity']}, Month beginning: $beginning_quantity, Current total: $actual_quantity";
        $log_activity_query = "INSERT INTO activities (action_type, description, item_code, user_id, timestamp) VALUES ('Restock Item', ?, ?, ?, NOW())";
        $stmtLog = $conn->prepare($log_activity_query);
        if (!$stmtLog->execute([$activity_description, $item['itemId'], $user_id])) {
            throw new Exception("Failed to log activity");
        }
    }

    // Notify all customers (students + employees, excluding PAMO/Admin) about the restock
    $customer_query = "
        SELECT id, first_name FROM account 
        WHERE status = 'active'
        AND role_category IN ('COLLEGE STUDENT', 'SHS', 'EMPLOYEE')
        AND (program_abbreviation IS NULL OR program_abbreviation NOT IN ('PAMO', 'ADMIN'))
    ";
    $customers_stmt = $conn->query($customer_query);
    if ($customers_stmt) {
        // Build a message for the notification using item names
        $restocked_item_names = [];
        foreach ($validatedItems as $item) {
            $itemId = $item['itemId'];
            $name_stmt = $conn->prepare("SELECT item_name FROM inventory WHERE item_code = ?");
            $name_stmt->execute([$itemId]);
            if ($name_row = $name_stmt->fetch(PDO::FETCH_ASSOC)) {
                $restocked_item_names[] = $name_row['item_name'];
            } else {
                $restocked_item_names[] = $itemId; // fallback to code if name not found
            }
        }
        $restocked_items_str = implode(', ', $restocked_item_names);
        $notif_message = "New stock has arrived for the following product: $restocked_items_str. Check the Products page for details!";
        $insert_notif = $conn->prepare("INSERT INTO notifications (user_id, message, order_number, type, is_read, created_at) VALUES (?, ?, NULL, 'restock', 0, NOW())");
        while ($customer = $customers_stmt->fetch(PDO::FETCH_ASSOC)) {
            $insert_notif->execute([$customer['id'], $notif_message]);
        }
    }

    // If we got here, everything succeeded
    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => 'All items in delivery recorded successfully'
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>