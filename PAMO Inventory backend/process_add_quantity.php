<?php
// Disable error display in output
error_reporting(0);
ini_set('display_errors', 0);

// Set JSON header
header('Content-Type: application/json');

try {
    // Connect to database
    $conn = mysqli_connect("localhost", "root", "", "proware");
    if (!$conn) {
        throw new Exception('Connection failed: ' . mysqli_connect_error());
    }

    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
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
        $itemId = trim(mysqli_real_escape_string($conn, $itemId));
        $quantity = intval($quantitiesToAdd[$i]);

        if (empty($itemId)) {
            throw new Exception("Empty item ID at position " . ($i + 1));
        }

        if ($quantity <= 0) {
            throw new Exception("Invalid quantity for item $itemId: must be greater than 0");
        }

        // Check if item exists
        $stmt = $conn->prepare("SELECT item_code FROM inventory WHERE item_code = ?");
        if (!$stmt) {
            throw new Exception("Database error");
        }

        $stmt->bind_param("s", $itemId);
        if (!$stmt->execute()) {
            throw new Exception("Database error");
        }

        $result = $stmt->get_result();
        if (!$result->fetch_assoc()) {
            throw new Exception("Item not found: $itemId");
        }

        $validatedItems[] = [
            'itemId' => $itemId,
            'quantity' => $quantity
        ];
    }

    // Start transaction after all validation is complete
    mysqli_begin_transaction($conn);

    // Process each validated item
    foreach ($validatedItems as $item) {
        // Get current quantities with row lock
        $sql = "SELECT actual_quantity, new_delivery, beginning_quantity FROM inventory WHERE item_code = ? FOR UPDATE";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Database error");
        }
        
        $stmt->bind_param("s", $item['itemId']);
        if (!$stmt->execute()) {
            throw new Exception("Database error");
        }
        
        $result = $stmt->get_result();
        $currentItem = $result->fetch_assoc();
        if (!$currentItem) {
            throw new Exception("Item not found: {$item['itemId']}");
        }

        // Calculate new quantities
        $new_delivery = $item['quantity'];
        $beginning_quantity = $currentItem['actual_quantity'];
        $actual_quantity = $beginning_quantity + $new_delivery;

        // Update inventory
        $updateStockStmt = $conn->prepare(
            "UPDATE inventory 
            SET actual_quantity = ?,
                new_delivery = ?,
                beginning_quantity = ?,
                date_delivered = NOW(),
                status = CASE 
                    WHEN ? <= 0 THEN 'Out of Stock'
                    WHEN ? <= 10 THEN 'Low Stock'
                    ELSE 'In Stock'
                END
            WHERE item_code = ? AND actual_quantity = ?"
        );
        if (!$updateStockStmt) {
            throw new Exception("Database error");
        }

        $updateStockStmt->bind_param("iiiiisi", 
            $actual_quantity,         // actual_quantity
            $new_delivery,            // new_delivery
            $beginning_quantity,      // beginning_quantity
            $actual_quantity,         // for status logic
            $actual_quantity,         // for status logic
            $item['itemId'],          // item_code
            $beginning_quantity       // previous actual_quantity
        );
        
        if (!$updateStockStmt->execute()) {
            throw new Exception("Failed to update inventory");
        }
        $updateStockStmt->close();

        // Log the activity
        $activity_description = "New delivery added - Order #: $orderNumber, Item: {$item['itemId']}, Quantity: {$item['quantity']}, Previous stock: $beginning_quantity, New total: $actual_quantity";
        $log_activity_query = "INSERT INTO activities (action_type, description, item_code, user_id, timestamp) VALUES ('Restock Item', ?, ?, ?, NOW())";
        $stmt = $conn->prepare($log_activity_query);
        if (!$stmt) {
            throw new Exception("Database error");
        }

        $user_id = $_SESSION['user_id'] ?? null;
        if ($user_id === null) {
            throw new Exception("User not logged in");
        }
        $stmt->bind_param("ssi", $activity_description, $item['itemId'], $user_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to log activity");
        }
        $stmt->close();
    }

    // Notify all students (COLLEGE STUDENT and SHS) about the restock
    $student_query = "SELECT id, first_name FROM account WHERE role_category = 'COLLEGE STUDENT' OR role_category = 'SHS'";
    $students_result = mysqli_query($conn, $student_query);
    if ($students_result) {
        // Build a message for the notification using item names
        $restocked_item_names = [];
        foreach ($validatedItems as $item) {
            $itemId = $item['itemId'];
            $name_result = mysqli_query($conn, "SELECT item_name FROM inventory WHERE item_code = '" . mysqli_real_escape_string($conn, $itemId) . "'");
            if ($name_row = mysqli_fetch_assoc($name_result)) {
                $restocked_item_names[] = $name_row['item_name'];
            } else {
                $restocked_item_names[] = $itemId; // fallback to code if name not found
            }
        }
        $restocked_items_str = implode(', ', $restocked_item_names);
        $notif_message = "New stock has arrived for the following product: $restocked_items_str. Check the Item List page for details!";
        while ($student = mysqli_fetch_assoc($students_result)) {
            $student_id = $student['id'];
            $insert_notif = $conn->prepare("INSERT INTO notifications (user_id, message, order_number, type, is_read, created_at) VALUES (?, ?, NULL, 'restock', 0, NOW())");
            if ($insert_notif) {
                $insert_notif->bind_param("is", $student_id, $notif_message);
                $insert_notif->execute();
                $insert_notif->close();
            }
        }
    }

    // If we got here, everything succeeded
    mysqli_commit($conn);
    die(json_encode([
        'success' => true,
        'message' => 'All items in delivery recorded successfully'
    ]));

} catch (Exception $e) {
    // Rollback transaction if it was started
    if (isset($conn) && $conn->ping()) {
        mysqli_rollback($conn);
    }
    die(json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]));
} finally {
    // Close connection if it exists
    if (isset($conn) && $conn->ping()) {
        mysqli_close($conn);
    }
}
?> 