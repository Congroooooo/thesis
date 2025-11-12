<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../Includes/connection.php'; // PDO $conn

// Get POST data
$transactionNumber = isset($_POST['transactionNumber']) ? $_POST['transactionNumber'] : '';
$customerId = isset($_POST['customerId']) ? intval($_POST['customerId']) : 0;
$customerName = isset($_POST['customerName']) ? $_POST['customerName'] : '';
$cashierName = isset($_POST['cashierName']) ? $_POST['cashierName'] : '';
$items = isset($_POST['items']) ? $_POST['items'] : [];

if (!$transactionNumber || !$customerId || !$customerName || empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

try {
    // Start transaction
    $conn->beginTransaction();
    
    // First, record the transaction-customer relationship
    $customer_sql = "INSERT INTO transaction_customers (transaction_number, customer_id, customer_name, sale_date) 
                     VALUES (?, ?, ?, NOW()) 
                     ON DUPLICATE KEY UPDATE customer_name = VALUES(customer_name)";
    $customer_stmt = $conn->prepare($customer_sql);
    if (!$customer_stmt->execute([$transactionNumber, $customerId, $customerName])) {
        throw new Exception("Error recording customer transaction");
    }

    // Process each item
    foreach ($items as $item) {
        $itemId = $item['itemId'];
        $size = $item['size'];
        $quantityToDeduct = intval($item['quantityToDeduct']);
        $pricePerItem = floatval($item['pricePerItem']);
        $itemTotal = floatval($item['itemTotal']);

        // Get current item details
        $getItemStmt = $conn->prepare("SELECT actual_quantity FROM inventory WHERE item_code = ?");
        $getItemStmt->execute([$itemId]);
        $item = $getItemStmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            throw new Exception("Item not found or no longer exists in inventory: $itemId");
        }

        if ($item['actual_quantity'] < $quantityToDeduct) {
            throw new Exception("Insufficient stock for item $itemId. Current stock: " . $item['actual_quantity']);
        }

        // Calculate new quantities
        $new_actual_quantity = $item['actual_quantity'] - $quantityToDeduct;
        
        // Update inventory with optimistic locking
        $updateSql = "UPDATE inventory 
            SET actual_quantity = ?,
                status = CASE 
                    WHEN ? <= 0 THEN 'Out of Stock'
                    WHEN ? <= 10 THEN 'Low Stock'
                    ELSE 'In Stock'
                END
            WHERE item_code = ? AND actual_quantity = ?";
        $updateStockStmt = $conn->prepare($updateSql);
        if (!$updateStockStmt->execute([$new_actual_quantity, $new_actual_quantity, $new_actual_quantity, $itemId, $item['actual_quantity']])) {
            throw new Exception("Error updating item $itemId");
        }

    if ($updateStockStmt->rowCount() === 0) {
            throw new Exception("Item $itemId was modified by another transaction. Please try again.");
        }

        // Record the sale in sales table (unchanged)
        $sql = "INSERT INTO sales (transaction_number, item_code, size, quantity, price_per_item, total_amount, transaction_type, sale_date) 
                VALUES (?, ?, ?, ?, ?, ?, 'Original', NOW())";
        $stmt = $conn->prepare($sql);
        if (!$stmt->execute([$transactionNumber, $itemId, $size, $quantityToDeduct, $pricePerItem, $itemTotal])) {
            throw new Exception("Error recording sale for item $itemId");
        }

        // Log the activity
        $activity_description = "Sale recorded - Transaction #: $transactionNumber, Customer: $customerName, Item: $itemId, Size: $size, Quantity: $quantityToDeduct, Total: $itemTotal, Previous stock: {$item['actual_quantity']}, New stock: $new_actual_quantity";
        $log_activity_query = "INSERT INTO activities (action_type, description, item_code, user_id, timestamp) VALUES ('Sales', ?, ?, ?, NOW())";
        $stmt = $conn->prepare($log_activity_query);
        $user_id = $_SESSION['user_id'] ?? null;
        if (!$stmt->execute([$activity_description, $itemId, $user_id])) {
            throw new Exception("Error logging activity for item $itemId");
        }
    }

    // If we got here, everything succeeded
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