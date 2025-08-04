<?php
header('Content-Type: application/json');

$conn = mysqli_connect("localhost", "root", "", "proware");

if (!$conn) {
    die(json_encode([
        'success' => false,
        'message' => 'Connection failed: ' . mysqli_connect_error()
    ]));
}

$transactionNumber = mysqli_real_escape_string($conn, $_POST['transactionNumber']);
$itemIds = is_array($_POST['itemId']) ? $_POST['itemId'] : [$_POST['itemId']];
$sizes = is_array($_POST['size']) ? $_POST['size'] : [$_POST['size']];
$quantitiesToDeduct = is_array($_POST['quantityToDeduct']) ? $_POST['quantityToDeduct'] : [$_POST['quantityToDeduct']];
$pricesPerItem = is_array($_POST['pricePerItem']) ? $_POST['pricePerItem'] : [$_POST['pricePerItem']];
$itemTotals = is_array($_POST['itemTotal']) ? $_POST['itemTotal'] : [$_POST['itemTotal']];
$totalAmount = floatval($_POST['totalAmount']);

if (count($itemIds) !== count($sizes) || 
    count($itemIds) !== count($quantitiesToDeduct) || 
    count($itemIds) !== count($pricesPerItem) || 
    count($itemIds) !== count($itemTotals)) {
    die(json_encode([
        'success' => false,
        'message' => 'Mismatched item data'
    ]));
}

mysqli_begin_transaction($conn);

try {
    $success = true;
    $errors = [];

    if (empty($transactionNumber)) {
        throw new Exception("Transaction number is required");
    }

    for ($i = 0; $i < count($itemIds); $i++) {
    for ($i = 0; $i < count($itemIds); $i++) {
        $itemId = mysqli_real_escape_string($conn, $itemIds[$i]);
        $size = mysqli_real_escape_string($conn, $sizes[$i]);
        $quantityToDeduct = intval($quantitiesToDeduct[$i]);
        $pricePerItem = floatval($pricesPerItem[$i]);
        $itemTotal = floatval($itemTotals[$i]);

        $sql = "SELECT actual_quantity, beginning_quantity FROM inventory WHERE item_code = ? FOR UPDATE";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("s", $itemId);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();

        if (!$item) {
            throw new Exception("Item not found or no longer exists in inventory: $itemId");
        }

        if ($item['actual_quantity'] < $quantityToDeduct) {
            throw new Exception("Insufficient stock for item $itemId. Current stock: " . $item['actual_quantity']);
        }

        $new_actual_quantity = $item['actual_quantity'] - $quantityToDeduct;
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

        $activity_description = "Sale recorded - Transaction #: $transactionNumber, Item: $itemId, Size: $size, Quantity: $quantityToDeduct, Total: $itemTotal, Previous stock: {$item['actual_quantity']}, New stock: $new_actual_quantity";
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

    $customer_sql = "INSERT INTO transaction_customers (transaction_number, customer_id, customer_name, sale_date) 
                     VALUES (?, ?, ?, NOW()) 
                     ON DUPLICATE KEY UPDATE customer_name = VALUES(customer_name)";
    $customer_stmt = $conn->prepare($customer_sql);
    if (!$customer_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $customerId = isset($_POST['customerId']) ? intval($_POST['customerId']) : 0;
    $customerName = isset($_POST['customerName']) ? mysqli_real_escape_string($conn, $_POST['customerName']) : '';
    
    if (!$customerId || !$customerName) {
        $studentName = isset($_POST['studentName']) ? mysqli_real_escape_string($conn, $_POST['studentName']) : '';
        if ($studentName) {
            $get_customer_sql = "SELECT id, CONCAT(first_name, ' ', last_name) as full_name FROM account WHERE CONCAT(first_name, ' ', last_name) = ?";
            $get_customer_stmt = $conn->prepare($get_customer_sql);
            $get_customer_stmt->bind_param("s", $studentName);
            $get_customer_stmt->execute();
            $customer_result = $get_customer_stmt->get_result();
            if ($customer_row = $customer_result->fetch_assoc()) {
                $customerId = $customer_row['id'];
                $customerName = $customer_row['full_name'];
            }
        }
    }
    
    if ($customerId && $customerName) {
        $customer_stmt->bind_param("sis", $transactionNumber, $customerId, $customerName);
        if (!$customer_stmt->execute()) {
            throw new Exception("Error recording customer transaction: " . $customer_stmt->error);
        }
    }

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