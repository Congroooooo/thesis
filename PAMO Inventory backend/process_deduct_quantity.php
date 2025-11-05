<?php
session_start();
header('Content-Type: application/json');

require_once '../Includes/connection.php'; // PDO $conn
require_once '../Includes/MonthlyInventoryManager.php'; // Monthly inventory manager

// Ensure autocommit is disabled for proper transaction handling
$conn->setAttribute(PDO::ATTR_AUTOCOMMIT, FALSE);

// Clear any existing transaction state that might be lingering
if ($conn->inTransaction()) {
    $conn->rollBack();
}

$monthlyInventory = new MonthlyInventoryManager($conn);

$transactionNumber = (string)($_POST['transactionNumber'] ?? '');
$itemIds = is_array($_POST['itemId'] ?? []) ? $_POST['itemId'] : [$_POST['itemId'] ?? ''];
$sizes = is_array($_POST['size'] ?? []) ? $_POST['size'] : [$_POST['size'] ?? ''];
$quantitiesToDeduct = is_array($_POST['quantityToDeduct'] ?? []) ? $_POST['quantityToDeduct'] : [$_POST['quantityToDeduct'] ?? 0];
$pricesPerItem = is_array($_POST['pricePerItem'] ?? []) ? $_POST['pricePerItem'] : [$_POST['pricePerItem'] ?? 0];
$itemTotals = is_array($_POST['itemTotal'] ?? []) ? $_POST['itemTotal'] : [$_POST['itemTotal'] ?? 0];
$totalAmount = floatval($_POST['totalAmount'] ?? 0);

if (count($itemIds) !== count($sizes) || 
    count($itemIds) !== count($quantitiesToDeduct) || 
    count($itemIds) !== count($pricesPerItem) || 
    count($itemIds) !== count($itemTotals)) {
    die(json_encode([
        'success' => false,
        'message' => 'Mismatched item data'
    ]));
}

// Check if there's already an active transaction
if ($conn->inTransaction()) {
    die(json_encode([
        'success' => false,
        'message' => 'Database transaction is already active. Please try again.'
    ]));
}

$conn->beginTransaction();

try {
    $success = true;
    $errors = [];

    if (empty($transactionNumber)) {
        throw new Exception("Transaction number is required");
    }

    for ($i = 0; $i < count($itemIds); $i++) {
        $itemId = (string)$itemIds[$i];
        $size = (string)$sizes[$i];
        $quantityToDeduct = intval($quantitiesToDeduct[$i]);
        $pricePerItem = floatval($pricesPerItem[$i]);
        $itemTotal = floatval($itemTotals[$i]);

        $sql = "SELECT actual_quantity, beginning_quantity FROM inventory WHERE item_code = ? FOR UPDATE";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$itemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            throw new Exception("Item not found or no longer exists in inventory: $itemId");
        }

        if ($item['actual_quantity'] < $quantityToDeduct) {
            throw new Exception("Insufficient stock for item $itemId. Current stock: " . $item['actual_quantity']);
        }

        // Record the sale in the monthly inventory system
        $user_id = $_SESSION['user_id'] ?? null;
        if ($user_id === null) {
            throw new Exception("User not logged in");
        }
        
        $monthlyInventory->recordSale(
            $transactionNumber,
            $itemId,
            $quantityToDeduct,
            $pricePerItem,
            $itemTotal,
            $user_id,
            false  // Don't use internal transaction since we're already in one
        );

        // Also record in the existing sales table for compatibility
        $sql = "INSERT INTO sales (transaction_number, item_code, size, quantity, price_per_item, total_amount, sale_date) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        if (!$stmt->execute([$transactionNumber, $itemId, $size, $quantityToDeduct, $pricePerItem, $itemTotal])) {
            throw new Exception("Error recording sale for item $itemId");
        }

        // Get updated stock for logging
        $stmt = $conn->prepare("SELECT actual_quantity FROM inventory WHERE item_code = ?");
        $stmt->execute([$itemId]);
        $updatedItem = $stmt->fetch(PDO::FETCH_ASSOC);
        $new_actual_quantity = $updatedItem['actual_quantity'];
        
        $activity_description = "Sale recorded - Transaction #: $transactionNumber, Item: $itemId, Size: $size, Quantity: $quantityToDeduct, Total: $itemTotal, Previous stock: {$item['actual_quantity']}, New stock: $new_actual_quantity";
        $log_activity_query = "INSERT INTO activities (action_type, description, item_code, user_id, timestamp) VALUES ('Sales', ?, ?, ?, NOW())";
        $stmt = $conn->prepare($log_activity_query);
        if (!$stmt->execute([$activity_description, $itemId, $user_id])) {
            throw new Exception("Error logging activity for item $itemId");
        }
    }

    $customer_sql = "INSERT INTO transaction_customers (transaction_number, customer_id, customer_name, sale_date) 
                     VALUES (?, ?, ?, NOW()) 
                     ON DUPLICATE KEY UPDATE customer_name = VALUES(customer_name)";
    $customer_stmt = $conn->prepare($customer_sql);
    
    $customerId = isset($_POST['customerId']) ? intval($_POST['customerId']) : 0;
    $customerName = isset($_POST['customerName']) ? (string)$_POST['customerName'] : '';
    
    if (!$customerId || !$customerName) {
        $studentName = isset($_POST['studentName']) ? trim($_POST['studentName']) : '';
        if ($studentName) {
            $get_customer_sql = "SELECT id, CONCAT(first_name, ' ', last_name) as full_name FROM account WHERE CONCAT(first_name, ' ', last_name) = ?";
            $get_customer_stmt = $conn->prepare($get_customer_sql);
            $get_customer_stmt->execute([$studentName]);
            if ($customer_row = $get_customer_stmt->fetch(PDO::FETCH_ASSOC)) {
                $customerId = $customer_row['id'];
                $customerName = $customer_row['full_name'];
            }
        }
    }
    
    if ($customerId && $customerName) {
        if (!$customer_stmt->execute([$transactionNumber, $customerId, $customerName])) {
            throw new Exception("Error recording customer transaction");
        }
    }

    // Note: Walk-in orders are now handled through the unified orders table
    // and marked as completed via orders.php "Mark as Completed" button
    // This legacy Sales Entry process is kept for backward compatibility with manual entries

    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => 'All sales recorded successfully with customer tracking'
    ]);

} catch (Exception $e) {
    if ($conn instanceof PDO && $conn->inTransaction()) { $conn->rollBack(); }
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?> 