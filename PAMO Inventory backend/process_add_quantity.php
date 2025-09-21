<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

try {
    require_once '../Includes/connection.php';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $orderNumber = isset($_POST['orderNumber']) ? trim($_POST['orderNumber']) : '';
    if (empty($orderNumber)) {
        throw new Exception('Order number is required');
    }

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

    $conn->beginTransaction();

    foreach ($validatedItems as $item) {
        $sql = "SELECT actual_quantity, new_delivery, beginning_quantity FROM inventory WHERE item_code = ? FOR UPDATE";
        $stmt = $conn->prepare($sql);
        if (!$stmt->execute([$item['itemId']])) {
            throw new Exception("Database error");
        }
        $currentItem = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$currentItem) {
            throw new Exception("Item not found: {$item['itemId']}");
        }

        $new_delivery = $item['quantity'];
        $beginning_quantity = $currentItem['actual_quantity'];
        $actual_quantity = $beginning_quantity + $new_delivery;

        $updateSql = "UPDATE inventory 
            SET actual_quantity = ?,
                new_delivery = ?,
                beginning_quantity = ?,
                date_delivered = NOW(),
                status = CASE 
                    WHEN ? <= 0 THEN 'Out of Stock'
                    WHEN ? <= 10 THEN 'Low Stock'
                    ELSE 'In Stock'
                END
            WHERE item_code = ? AND actual_quantity = ?";
        $updateStockStmt = $conn->prepare($updateSql);
        if (!$updateStockStmt->execute([
            $actual_quantity,
            $new_delivery,
            $beginning_quantity,
            $actual_quantity,
            $actual_quantity,
            $item['itemId'],
            $beginning_quantity
        ])) {
            throw new Exception("Failed to update inventory");
        }

        $activity_description = "New delivery added - Order #: $orderNumber, Item: {$item['itemId']}, Quantity: {$item['quantity']}, Previous stock: $beginning_quantity, New total: $actual_quantity";
        $log_activity_query = "INSERT INTO activities (action_type, description, item_code, user_id, timestamp) VALUES ('Restock Item', ?, ?, ?, NOW())";
        $stmtLog = $conn->prepare($log_activity_query);
        $user_id = $_SESSION['user_id'] ?? null;
        if ($user_id === null) {
            throw new Exception("User not logged in");
        }
        if (!$stmtLog->execute([$activity_description, $item['itemId'], $user_id])) {
            throw new Exception("Failed to log activity");
        }
    }

    $student_query = "SELECT id, first_name FROM account WHERE role_category = 'COLLEGE STUDENT' OR role_category = 'SHS'";
    $students_stmt = $conn->query($student_query);
    if ($students_stmt) {
        $restocked_item_names = [];
        foreach ($validatedItems as $item) {
            $itemId = $item['itemId'];
            $name_stmt = $conn->prepare("SELECT item_name FROM inventory WHERE item_code = ?");
            $name_stmt->execute([$itemId]);
            if ($name_row = $name_stmt->fetch(PDO::FETCH_ASSOC)) {
                $restocked_item_names[] = $name_row['item_name'];
            } else {
                $restocked_item_names[] = $itemId;
            }
        }
        $restocked_items_str = implode(', ', $restocked_item_names);
        $notif_message = "New stock has arrived for the following product: $restocked_items_str. Check the Product Page for details!";
        $insert_notif = $conn->prepare("INSERT INTO notifications (user_id, message, order_number, type, is_read, created_at) VALUES (?, ?, NULL, 'restock', 0, NOW())");
        while ($student = $students_stmt->fetch(PDO::FETCH_ASSOC)) {
            $insert_notif->execute([$student['id'], $notif_message]);
        }
    }

    $conn->commit();
    die(json_encode([
        'success' => true,
        'message' => 'All items in delivery recorded successfully'
    ]));

} catch (Exception $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    die(json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]));
}
?> 