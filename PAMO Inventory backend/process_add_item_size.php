<?php
session_start();
file_put_contents(__DIR__ . '/debug_add_item_size.txt', print_r($_POST, true));
header('Content-Type: application/json');

// Database connection
$conn = mysqli_connect("localhost", "root", "", "proware");

if (!$conn) {
    die(json_encode(['success' => false, 'message' => 'Connection failed: ' . mysqli_connect_error()]));
}

// Get form data
$existingItem = isset($_POST['existingItem']) ? $_POST['existingItem'] : '';
$newSizes = isset($_POST['newSize']) ? $_POST['newSize'] : [];
$newItemCodes = isset($_POST['newItemCode']) ? $_POST['newItemCode'] : [];
$newQuantities = isset($_POST['newQuantity']) ? $_POST['newQuantity'] : [];
$newDamages = isset($_POST['newDamage']) ? $_POST['newDamage'] : [];
$deliveryOrderNumber = isset($_POST['deliveryOrderNumber']) ? $_POST['deliveryOrderNumber'] : '';

// Validate inputs
if (empty($existingItem) || empty($newSizes) || empty($newItemCodes) || empty($newQuantities)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Get the original item details
$sql = "SELECT item_name, category, price FROM inventory WHERE item_code LIKE ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
$prefix = $existingItem . '%';
mysqli_stmt_bind_param($stmt, "s", $prefix);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$originalItem = mysqli_fetch_assoc($result);

if (!$originalItem) {
    echo json_encode(['success' => false, 'message' => 'Original item not found']);
    exit;
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    $success = true;
    $errors = [];

    // Process each size entry
    for ($i = 0; $i < count($newSizes); $i++) {
        $newSize = $newSizes[$i];
        $newItemCode = $newItemCodes[$i];
        $newQuantity = $newQuantities[$i];
        $newDamage = isset($newDamages[$i]) ? $newDamages[$i] : 0;

        // Check if the new item code already exists
        $sql = "SELECT item_code FROM inventory WHERE item_code = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $newItemCode);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {
            $errors[] = "Item code {$newItemCode} already exists";
            continue;
        }

        // Insert the new item size
        $sql = "INSERT INTO inventory (item_code, item_name, category, sizes, actual_quantity, damage, price, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssssiid", 
            $newItemCode,
            $originalItem['item_name'],
            $originalItem['category'],
            $newSize,
            $newQuantity,
            $newDamage,
            $originalItem['price']
        );

        if (!mysqli_stmt_execute($stmt)) {
            $errors[] = "Error adding size {$newSize}: " . mysqli_error($conn);
            continue;
        }

        // Get the new inventory ID
        $new_inventory_id = mysqli_insert_id($conn);

        // Find the parent inventory ID (the first item with the same prefix)
        $parent_sql = "SELECT id FROM inventory WHERE item_code LIKE ? ORDER BY id ASC LIMIT 1";
        $parent_stmt = mysqli_prepare($conn, $parent_sql);
        mysqli_stmt_bind_param($parent_stmt, "s", $prefix);
        mysqli_stmt_execute($parent_stmt);
        $parent_result = mysqli_stmt_get_result($parent_stmt);
        $parent_row = mysqli_fetch_assoc($parent_result);
        $parent_inventory_id = $parent_row ? $parent_row['id'] : null;
        mysqli_stmt_close($parent_stmt);

        // If parent found, copy all course links
        if ($parent_inventory_id) {
            $course_sql = "SELECT course_id FROM course_item WHERE inventory_id = ?";
            $course_stmt = mysqli_prepare($conn, $course_sql);
            mysqli_stmt_bind_param($course_stmt, "i", $parent_inventory_id);
            mysqli_stmt_execute($course_stmt);
            $course_result = mysqli_stmt_get_result($course_stmt);
            $insert_course_sql = "INSERT INTO course_item (course_id, inventory_id) VALUES (?, ?)";
            $insert_course_stmt = mysqli_prepare($conn, $insert_course_sql);
            while ($course_row = mysqli_fetch_assoc($course_result)) {
                $course_id = $course_row['course_id'];
                mysqli_stmt_bind_param($insert_course_stmt, "ii", $course_id, $new_inventory_id);
                mysqli_stmt_execute($insert_course_stmt);
            }
            mysqli_stmt_close($course_stmt);
            mysqli_stmt_close($insert_course_stmt);

            // Shirt types no longer use a dedicated table; links are via subcategories
        }

        // Log the activity
        $activity_description = "New size added for {$originalItem['item_name']} ({$newItemCode}) - Size: {$newSize}, Initial stock: {$newQuantity}, Damage: {$newDamage}";
        $log_activity_query = "INSERT INTO activities (action_type, description, item_code, user_id, timestamp) VALUES ('Add Item Size', ?, ?, ?, NOW())";
        $stmt = mysqli_prepare($conn, $log_activity_query);
        $user_id = $_SESSION['user_id'] ?? null;
        mysqli_stmt_bind_param($stmt, "ssi", $activity_description, $newItemCode, $user_id);
        mysqli_stmt_execute($stmt);
    }

    if (!empty($errors)) {
        throw new Exception(implode("\n", $errors));
    }

    // If we got here, commit the transaction
    mysqli_commit($conn);
    echo json_encode(['success' => true, 'message' => 'New sizes added successfully']);

} catch (Exception $e) {
    // If there was an error, rollback the transaction
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

mysqli_close($conn);
?> 