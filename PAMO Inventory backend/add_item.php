<?php
try {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $conn = mysqli_connect("localhost", "root", "", "proware");
    if (!$conn) {
        throw new Exception("Connection failed: " . mysqli_connect_error());
    }

    mysqli_begin_transaction($conn);

    $required_fields = ['newItemCode', 'category_id', 'newItemName', 'newSize', 'newItemPrice', 'newItemQuantity', 'deliveryOrderNumber'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $item_code = mysqli_real_escape_string($conn, $_POST['newItemCode']);
    $category_id = intval($_POST['category_id'] ?? 0);
    if ($category_id <= 0) {
        throw new Exception("Category is required");
    }
    // Resolve category name from ID (keep legacy `inventory.category` string filled during transition)
    $cat_sql = "SELECT name, has_subcategories FROM categories WHERE id = ?";
    $cat_stmt = mysqli_prepare($conn, $cat_sql);
    if (!$cat_stmt) {
        throw new Exception("Prepare failed: " . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($cat_stmt, "i", $category_id);
    mysqli_stmt_execute($cat_stmt);
    mysqli_stmt_bind_result($cat_stmt, $category_name, $category_has_sub);
    mysqli_stmt_fetch($cat_stmt);
    mysqli_stmt_close($cat_stmt);
    if (!$category_name) {
        throw new Exception("Invalid category");
    }
    $category = mysqli_real_escape_string($conn, $category_name);
    $item_name = mysqli_real_escape_string($conn, $_POST['newItemName']);
    $sizes = mysqli_real_escape_string($conn, $_POST['newSize']);
    $price = floatval($_POST['newItemPrice']);
    $quantity = intval($_POST['newItemQuantity']);
    $damage = intval($_POST['newItemDamage'] ?? 0);
    $delivery_order = mysqli_real_escape_string($conn, $_POST['deliveryOrderNumber']);

    if ($price <= 0) {
        throw new Exception("Price must be greater than zero");
    }
    if ($quantity < 0) {
        throw new Exception("Quantity cannot be negative");
    }
    if ($damage < 0) {
        throw new Exception("Damage count cannot be negative");
    }

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
        $imageSize = $_FILES['newImage']['size'];
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
    $check_sql = "SELECT COUNT(*) FROM inventory WHERE item_code LIKE CONCAT(?, '-%')";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    if (!$check_stmt) {
        throw new Exception("Prepare failed: " . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($check_stmt, "s", $prefix);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_bind_result($check_stmt, $prefix_count);
    mysqli_stmt_fetch($check_stmt);
    mysqli_stmt_close($check_stmt);

    if ($prefix_count > 0) {
        throw new Exception('An item with this code prefix already exists. Please use the "Add Item Size" modal for adding new sizes.');
    }

    $course_ids = isset($_POST['course_id']) ? (is_array($_POST['course_id']) ? $_POST['course_id'] : [$_POST['course_id']]) : [];
    $RTW = (count($course_ids) > 1) ? 1 : 0;

    $sql = "INSERT INTO inventory (
        item_code, category_id, category, item_name, sizes, price, 
        actual_quantity, new_delivery, beginning_quantity, 
        damage, sold_quantity, status, image_path, RTW, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param(
        $stmt,
        "sisssdiiiiissi",
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
    );

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error executing statement: " . mysqli_stmt_error($stmt));
    }

    $new_inventory_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    $description = "New item added: {$item_name} ({$item_code}) - Delivery Order #: {$delivery_order}, Initial delivery: {$new_delivery}, Damage: {$damage}, Actual quantity: {$actual_quantity}";
    $sql = "INSERT INTO activities (action_type, description, item_code, user_id, timestamp) VALUES ('New Item', ?, ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . mysqli_error($conn));
    }

    $user_id = $_SESSION['user_id'] ?? null;
    mysqli_stmt_bind_param($stmt, "ssi", $description, $item_code, $user_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error logging activity: " . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);

    // New: link subcategories, if provided
    $subcategory_ids = isset($_POST['subcategory_ids']) ? (array)$_POST['subcategory_ids'] : [];
    if (!empty($subcategory_ids)) {
        $pivot_sql = "INSERT IGNORE INTO inventory_subcategory (inventory_id, subcategory_id) VALUES (?, ?)";
        $pivot_stmt = mysqli_prepare($conn, $pivot_sql);
        if (!$pivot_stmt) {
            throw new Exception("Prepare failed: " . mysqli_error($conn));
        }
        foreach ($subcategory_ids as $sid) {
            $sid = intval($sid);
            if ($sid > 0) {
                mysqli_stmt_bind_param($pivot_stmt, "ii", $new_inventory_id, $sid);
                if (!mysqli_stmt_execute($pivot_stmt)) {
                    throw new Exception("Error linking subcategory: " . mysqli_stmt_error($pivot_stmt));
                }
            }
        }
        mysqli_stmt_close($pivot_stmt);
    }

    $student_query = "SELECT id FROM account WHERE role_category = 'COLLEGE STUDENT' OR role_category = 'SHS'";
    $students_result = mysqli_query($conn, $student_query);
    if ($students_result) {
        $notif_message = "A new product has been added: $item_name. Check the Item List page for details!";
        while ($student = mysqli_fetch_assoc($students_result)) {
            $student_id = $student['id'];
            $insert_notif = $conn->prepare("INSERT INTO notifications (user_id, message, order_number, type, is_read, created_at) VALUES (?, ?, NULL, 'New Item', 0, NOW())");
            if ($insert_notif) {
                $insert_notif->bind_param("is", $student_id, $notif_message);
                $insert_notif->execute();
                $insert_notif->close();
            }
        }
    }

    mysqli_commit($conn);

    echo json_encode([
        'success' => true,
        'message' => 'Item added successfully'
    ]);

} catch (Exception $e) {
    if (isset($conn)) {
        mysqli_rollback($conn);
    }

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