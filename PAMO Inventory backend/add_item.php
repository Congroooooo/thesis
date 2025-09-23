<?php
try {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    require_once '../Includes/connection.php'; // PDO $conn
    $conn->beginTransaction();

    // Check if this is a multi-size product request
    $isMultiSize = isset($_POST['sizes']) && is_array($_POST['sizes']);

    if ($isMultiSize) {
        // New multi-size flow
        $required_fields = ['baseItemCode', 'category_id', 'newItemName', 'deliveryOrderNumber'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || $_POST[$field] === '') {
                throw new Exception("Missing required field: $field");
            }
        }

        $base_item_code = (string)$_POST['baseItemCode'];
        $category_id = intval($_POST['category_id'] ?? 0);
        if ($category_id <= 0) {
            throw new Exception("Category is required");
        }

        // Resolve category name from ID
        $cat_stmt = $conn->prepare("SELECT name, has_subcategories FROM categories WHERE id = ?");
        $cat_stmt->execute([$category_id]);
        [$category_name, $category_has_sub] = $cat_stmt->fetch(PDO::FETCH_NUM) ?: [null, null];
        if (!$category_name) {
            throw new Exception("Invalid category");
        }
        $category = $category_name;
        $item_name = (string)$_POST['newItemName'];
        $delivery_order = (string)$_POST['deliveryOrderNumber'];

        // Validate that sizes array is not empty
        if (empty($_POST['sizes'])) {
            throw new Exception("At least one size must be selected");
        }

        // Check if base item code prefix already exists
        // For multi-size, we're using the full base code as prefix, not splitting on dash
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM inventory WHERE item_code LIKE CONCAT(?, '-%')");
        $check_stmt->execute([$base_item_code]);
        $prefix_count = (int)$check_stmt->fetchColumn();
        if ($prefix_count > 0) {
            throw new Exception('An item with this code prefix already exists. Please use the "Add Item Size" modal for adding new sizes.');
        }

        // Handle image upload once for all sizes
        $dbFilePath = null;
        if (isset($_FILES['newImage']) && $_FILES['newImage']['error'] === UPLOAD_ERR_OK) {
            $imageTmpPath = $_FILES['newImage']['tmp_name'];
            $imageName = $_FILES['newImage']['name'];
            $imageType = $_FILES['newImage']['type'];

            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($imageType, $allowed_types)) {
                throw new Exception('Invalid image type. Allowed types: JPG, PNG, GIF');
            }

            $uploadDir = '../uploads/itemlist/';
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    throw new Exception('Failed to create upload directory');
                }
            }

            $imageExtension = pathinfo($imageName, PATHINFO_EXTENSION);
            $uniqueName = uniqid('img_', true) . '.' . $imageExtension;
            $imagePath = $uploadDir . $uniqueName;
            $dbFilePath = 'uploads/itemlist/' . $uniqueName;

            if (!move_uploaded_file($imageTmpPath, $imagePath)) {
                throw new Exception('Error moving uploaded file');
            }
        } else {
            throw new Exception('Image upload is required');
        }

        $course_ids = isset($_POST['course_id']) ? (is_array($_POST['course_id']) ? $_POST['course_id'] : [$_POST['course_id']]) : [];
        $RTW = (count($course_ids) > 1) ? 1 : 0;

        include_once '../PAMO PAGES/includes/config_functions.php';
        $lowStockThreshold = getLowStockThreshold($conn);

        $inserted_items = [];
        $total_items_added = 0;

        // Process each size
        foreach ($_POST['sizes'] as $size => $sizeData) {
            if (!isset($sizeData['item_code'], $sizeData['price'], $sizeData['quantity'])) {
                throw new Exception("Incomplete data for size: $size");
            }

            $item_code = (string)$sizeData['item_code'];
            $price = floatval($sizeData['price']);
            $quantity = intval($sizeData['quantity']);
            $damage = intval($sizeData['damage'] ?? 0);

            if ($price <= 0) throw new Exception("Price must be greater than zero for size: $size");
            if ($quantity <= 0) throw new Exception("Initial stock must be 1 or more for size: $size");
            if ($damage < 0) throw new Exception("Damage count cannot be negative for size: $size");

            $beginning_quantity = 0;
            $new_delivery = $quantity;
            $actual_quantity = $beginning_quantity + $new_delivery - $damage;
            $sold_quantity = 0;
            $status = ($actual_quantity <= 0) ? 'Out of Stock' : (($actual_quantity <= $lowStockThreshold) ? 'Low Stock' : 'In Stock');

            // Insert inventory item
            $stmt = $conn->prepare("INSERT INTO inventory (
                item_code, category_id, category, item_name, sizes, price,
                actual_quantity, new_delivery, beginning_quantity,
                damage, sold_quantity, status, image_path, RTW, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $item_code,
                $category_id,
                $category,
                $item_name,
                $size,
                $price,
                $actual_quantity,
                $new_delivery,
                $beginning_quantity,
                $damage,
                $sold_quantity,
                $status,
                $dbFilePath,
                $RTW
            ]);

            $new_inventory_id = (int)$conn->lastInsertId();
            $inserted_items[] = $item_code;
            $total_items_added++;

            // Link subcategories, if provided
            $subcategory_ids = isset($_POST['subcategory_ids']) ? (array)$_POST['subcategory_ids'] : [];
            if (!empty($subcategory_ids)) {
                $pivot_stmt = $conn->prepare("INSERT IGNORE INTO inventory_subcategory (inventory_id, subcategory_id) VALUES (?, ?)");
                foreach ($subcategory_ids as $sid) {
                    $sid = intval($sid);
                    if ($sid > 0) {
                        $pivot_stmt->execute([$new_inventory_id, $sid]);
                    }
                }
            }
        }

        // Create activity log for the multi-size addition - use the first generated item code for foreign key reference
        $first_item_code = !empty($inserted_items) ? $inserted_items[0] : $base_item_code;
        $description = "Multi-size product added: {$item_name} - Base Code: {$base_item_code}, Sizes: " . implode(', ', array_keys($_POST['sizes'])) . 
                     " - Delivery Order #: {$delivery_order}, Total items: {$total_items_added}";
        $stmt = $conn->prepare("INSERT INTO activities (action_type, description, item_code, user_id, timestamp) VALUES ('New Item', ?, ?, ?, NOW())");
        $user_id = $_SESSION['user_id'] ?? null;
        $stmt->execute([$description, $first_item_code, $user_id]);

        // Send notifications to students
        $students_stmt = $conn->query("SELECT id FROM account WHERE role_category = 'COLLEGE STUDENT' OR role_category = 'SHS'");
        $notif_message = "A new product has been added: $item_name with multiple sizes. Check the Item List page for details!";
        $insert_notif = $conn->prepare("INSERT INTO notifications (user_id, message, order_number, type, is_read, created_at) VALUES (?, ?, NULL, 'New Item', 0, NOW())");
        while ($student = $students_stmt->fetch(PDO::FETCH_ASSOC)) {
            $insert_notif->execute([$student['id'], $notif_message]);
        }

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => "Successfully added {$total_items_added} product variations"
        ]);

    } else {
        // Legacy single-size flow (fallback)
        $required_fields = ['newItemCode', 'category_id', 'newItemName', 'newSize', 'newItemPrice', 'newItemQuantity', 'deliveryOrderNumber'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || $_POST[$field] === '') {
                throw new Exception("Missing required field: $field");
            }
        }

        $item_code = (string)$_POST['newItemCode'];
        $category_id = intval($_POST['category_id'] ?? 0);
        if ($category_id <= 0) {
            throw new Exception("Category is required");
        }
        // Resolve category name from ID (keep legacy `inventory.category` string filled during transition)
        $cat_stmt = $conn->prepare("SELECT name, has_subcategories FROM categories WHERE id = ?");
        $cat_stmt->execute([$category_id]);
        [$category_name, $category_has_sub] = $cat_stmt->fetch(PDO::FETCH_NUM) ?: [null, null];
        if (!$category_name) {
            throw new Exception("Invalid category");
        }
        $category = $category_name;
        $item_name = (string)$_POST['newItemName'];
        $sizes = (string)$_POST['newSize'];
        $price = floatval($_POST['newItemPrice']);
        $quantity = intval($_POST['newItemQuantity']);
        $damage = intval($_POST['newItemDamage'] ?? 0);
        $delivery_order = (string)$_POST['deliveryOrderNumber'];

        if ($price <= 0) throw new Exception("Price must be greater than zero");
        if ($quantity <= 0) throw new Exception("Initial stock must be 1 or more");
        if ($damage < 0) throw new Exception("Damage count cannot be negative");

        $beginning_quantity = 0;
        $new_delivery = $quantity;
        $actual_quantity = $beginning_quantity + $new_delivery - $damage;
        $sold_quantity = 0;
        include_once '../PAMO PAGES/includes/config_functions.php';
        $lowStockThreshold = getLowStockThreshold($conn);
        $status = ($actual_quantity <= 0) ? 'Out of Stock' : (($actual_quantity <= $lowStockThreshold) ? 'Low Stock' : 'In Stock');

        $dbFilePath = null;
        if (isset($_FILES['newImage']) && $_FILES['newImage']['error'] === UPLOAD_ERR_OK) {
            $imageTmpPath = $_FILES['newImage']['tmp_name'];
            $imageName = $_FILES['newImage']['name'];
            $imageType = $_FILES['newImage']['type'];

            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($imageType, $allowed_types)) {
                throw new Exception('Invalid image type. Allowed types: JPG, PNG, GIF');
            }

            $uploadDir = '../uploads/itemlist/';
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    throw new Exception('Failed to create upload directory');
                }
            }

            $imageExtension = pathinfo($imageName, PATHINFO_EXTENSION);
            $uniqueName = uniqid('img_', true) . '.' . $imageExtension;
            $imagePath = $uploadDir . $uniqueName;
            $dbFilePath = 'uploads/itemlist/' . $uniqueName;

            if (!move_uploaded_file($imageTmpPath, $imagePath)) {
                throw new Exception('Error moving uploaded file');
            }
        } else {
            throw new Exception('Image upload is required');
        }

        $prefix = explode('-', $item_code)[0];
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM inventory WHERE item_code LIKE CONCAT(?, '-%')");
        $check_stmt->execute([$prefix]);
        $prefix_count = (int)$check_stmt->fetchColumn();
        if ($prefix_count > 0) {
            throw new Exception('An item with this code prefix already exists. Please use the "Add Item Size" modal for adding new sizes.');
        }

        $course_ids = isset($_POST['course_id']) ? (is_array($_POST['course_id']) ? $_POST['course_id'] : [$_POST['course_id']]) : [];
        $RTW = (count($course_ids) > 1) ? 1 : 0;

        $stmt = $conn->prepare("INSERT INTO inventory (
            item_code, category_id, category, item_name, sizes, price,
            actual_quantity, new_delivery, beginning_quantity,
            damage, sold_quantity, status, image_path, RTW, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $item_code,
            $category_id,
            $category,
            $item_name,
            $sizes,
            $price,
            $actual_quantity,
            $new_delivery,
            $beginning_quantity,
            $damage,
            $sold_quantity,
            $status,
            $dbFilePath,
            $RTW
        ]);

        $new_inventory_id = (int)$conn->lastInsertId();

        $description = "New item added: {$item_name} ({$item_code}) - Delivery Order #: {$delivery_order}, Initial delivery: {$new_delivery}, Damage: {$damage}, Actual quantity: {$actual_quantity}";
        $stmt = $conn->prepare("INSERT INTO activities (action_type, description, item_code, user_id, timestamp) VALUES ('New Item', ?, ?, ?, NOW())");
        $user_id = $_SESSION['user_id'] ?? null;
        $stmt->execute([$description, $item_code, $user_id]);

        // Link subcategories, if provided
        $subcategory_ids = isset($_POST['subcategory_ids']) ? (array)$_POST['subcategory_ids'] : [];
        if (!empty($subcategory_ids)) {
            $pivot_stmt = $conn->prepare("INSERT IGNORE INTO inventory_subcategory (inventory_id, subcategory_id) VALUES (?, ?)");
            foreach ($subcategory_ids as $sid) {
                $sid = intval($sid);
                if ($sid > 0) {
                    $pivot_stmt->execute([$new_inventory_id, $sid]);
                }
            }
        }

        $students_stmt = $conn->query("SELECT id FROM account WHERE role_category = 'COLLEGE STUDENT' OR role_category = 'SHS'");
        $notif_message = "A new product has been added: $item_name. Check the Item List page for details!";
        $insert_notif = $conn->prepare("INSERT INTO notifications (user_id, message, order_number, type, is_read, created_at) VALUES (?, ?, NULL, 'New Item', 0, NOW())");
        while ($student = $students_stmt->fetch(PDO::FETCH_ASSOC)) {
            $insert_notif->execute([$student['id'], $notif_message]);
        }

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Item added successfully'
        ]);
    }

} catch (Exception $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>