<?php
try {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    require_once '../Includes/connection.php'; // PDO $conn
    $conn->beginTransaction();

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
    if ($quantity < 0) throw new Exception("Quantity cannot be negative");
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