<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Database connection
$conn = mysqli_connect("localhost", "root", "", "proware");

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get POST data
$transactionNumber = isset($_POST['transactionNumber']) ? mysqli_real_escape_string($conn, $_POST['transactionNumber']) : '';
$customerId = isset($_POST['customerId']) ? intval($_POST['customerId']) : 0;
$customerName = isset($_POST['customerName']) ? mysqli_real_escape_string($conn, $_POST['customerName']) : '';
$cashierName = isset($_POST['cashierName']) ? mysqli_real_escape_string($conn, $_POST['cashierName']) : '';
$items = isset($_POST['items']) ? $_POST['items'] : [];

if (!$transactionNumber || !$customerId || !$customerName || empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

try {
    // Start transaction
    mysqli_begin_transaction($conn);
    
    // First, record the transaction-customer relationship
    $customer_sql = "INSERT INTO transaction_customers (transaction_number, customer_id, customer_name, sale_date) 
                     VALUES (?, ?, ?, NOW()) 
                     ON DUPLICATE KEY UPDATE customer_name = VALUES(customer_name)";
    $customer_stmt = $conn->prepare($customer_sql);
    if (!$customer_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $customer_stmt->bind_param("sis", $transactionNumber, $customerId, $customerName);
    if (!$customer_stmt->execute()) {
        throw new Exception("Error recording customer transaction: " . $customer_stmt->error);
    }

    // Process each item
    foreach ($items as $item) {
        $itemId = mysqli_real_escape_string($conn, $item['itemId']);
        $size = mysqli_real_escape_string($conn, $item['size']);
        $quantityToDeduct = intval($item['quantityToDeduct']);
        $pricePerItem = floatval($item['pricePerItem']);
        $itemTotal = floatval($item['itemTotal']);

        // Get current item details
        $getItemStmt = $conn->prepare("SELECT actual_quantity FROM inventory WHERE item_code = ?");
        if (!$getItemStmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $getItemStmt->bind_param("s", $itemId);
        $getItemStmt->execute();
        $result = $getItemStmt->get_result();
        $item = $result->fetch_assoc();

        if (!$item) {
            throw new Exception("Item not found or no longer exists in inventory: $itemId");
        }

        if ($item['actual_quantity'] < $quantityToDeduct) {
            throw new Exception("Insufficient stock for item $itemId. Current stock: " . $item['actual_quantity']);
        }

        // Calculate new quantities
        $new_actual_quantity = $item['actual_quantity'] - $quantityToDeduct;
        
        // Update inventory with optimistic locking
        $updateStockStmt = $conn->prepare(
            "UPDATE inventory 
            SET actual_quantity = ?,
                status = CASE 
                    WHEN ? <= 0 THEN 'Out of Stock'
                    WHEN ? <= 10 THEN 'Low Stock'
                    ELSE 'In Stock'
                END
            WHERE item_code = ? AND actual_quantity = ?"
        );
        if (!$updateStockStmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $updateStockStmt->bind_param("iiisi", 
            $new_actual_quantity,
            $new_actual_quantity,
            $new_actual_quantity,
            $itemId,
            $item['actual_quantity']
        );
        
        if (!$updateStockStmt->execute()) {
            throw new Exception("Error updating item $itemId: " . $updateStockStmt->error);
        }

        if ($updateStockStmt->affected_rows === 0) {
            throw new Exception("Item $itemId was modified by another transaction. Please try again.");
        }

        // Record the sale in sales table (unchanged)
        $sql = "INSERT INTO sales (transaction_number, item_code, size, quantity, price_per_item, total_amount, sale_date) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $stmt->bind_param("sssidd", 
            $transactionNumber,
            $itemId,
            $size,
            $quantityToDeduct,
            $pricePerItem,
            $itemTotal
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error recording sale for item $itemId: " . $stmt->error);
        }

        // Log the activity
        $activity_description = "Sale recorded - Transaction #: $transactionNumber, Customer: $customerName, Item: $itemId, Size: $size, Quantity: $quantityToDeduct, Total: $itemTotal, Previous stock: {$item['actual_quantity']}, New stock: $new_actual_quantity";
        $log_activity_query = "INSERT INTO activities (action_type, description, item_code, user_id, timestamp) VALUES ('Sales', ?, ?, ?, NOW())";
        $stmt = $conn->prepare($log_activity_query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $user_id = $_SESSION['user_id'] ?? null;
        $stmt->bind_param("ssi", $activity_description, $itemId, $user_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error logging activity for item $itemId: " . $stmt->error);
        }
    }

    // If we got here, everything succeeded
    mysqli_commit($conn);
    echo json_encode([
        'success' => true,
        'message' => 'All sales recorded successfully with customer tracking'
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        mysqli_close($conn);
    }
}
?> 