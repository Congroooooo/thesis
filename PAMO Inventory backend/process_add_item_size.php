<?php
session_start();
file_put_contents(__DIR__ . '/debug_add_item_size.txt', print_r($_POST, true));
header('Content-Type: application/json');

require_once '../Includes/connection.php'; // PDO $conn

$existingItem = isset($_POST['existingItem']) ? $_POST['existingItem'] : '';
$newSizes = isset($_POST['newSize']) ? $_POST['newSize'] : [];
$newItemCodes = isset($_POST['newItemCode']) ? $_POST['newItemCode'] : [];
$newQuantities = isset($_POST['newQuantity']) ? $_POST['newQuantity'] : [];
$newDamages = isset($_POST['newDamage']) ? $_POST['newDamage'] : [];
$deliveryOrderNumber = isset($_POST['deliveryOrderNumber']) ? $_POST['deliveryOrderNumber'] : '';

if (empty($existingItem) || empty($newSizes) || empty($newItemCodes) || empty($newQuantities)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

$sql = "SELECT item_name, category, price FROM inventory WHERE item_code LIKE ? LIMIT 1";
$stmt = $conn->prepare($sql);
$prefix = $existingItem . '%';
$stmt->execute([$prefix]);
$originalItem = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$originalItem) {
    echo json_encode(['success' => false, 'message' => 'Original item not found']);
    exit;
}

$conn->beginTransaction();

try {
    $success = true;
    $errors = [];

    for ($i = 0; $i < count($newSizes); $i++) {
        $newSize = $newSizes[$i];
        $newItemCode = $newItemCodes[$i];
        $newQuantity = $newQuantities[$i];
        $newDamage = isset($newDamages[$i]) ? $newDamages[$i] : 0;

        $sql = "SELECT item_code FROM inventory WHERE item_code = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$newItemCode]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $errors[] = "Item code {$newItemCode} already exists";
            continue;
        }

        $sql = "INSERT INTO inventory (item_code, item_name, category, sizes, actual_quantity, damage, price, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        if (!$stmt->execute([
            $newItemCode,
            $originalItem['item_name'],
            $originalItem['category'],
            $newSize,
            $newQuantity,
            $newDamage,
            $originalItem['price']
        ])) {
            $errors[] = "Error adding size {$newSize}";
            continue;
        }

        $new_inventory_id = (int)$conn->lastInsertId();

        $parent_sql = "SELECT id FROM inventory WHERE item_code LIKE ? ORDER BY id ASC LIMIT 1";
        $parent_stmt = $conn->prepare($parent_sql);
        $parent_stmt->execute([$prefix]);
        $parent_row = $parent_stmt->fetch(PDO::FETCH_ASSOC);
        $parent_inventory_id = $parent_row ? $parent_row['id'] : null;

        if ($parent_inventory_id) {
            $course_sql = "SELECT course_id FROM course_item WHERE inventory_id = ?";
            $course_stmt = $conn->prepare($course_sql);
            $course_stmt->execute([$parent_inventory_id]);
            $insert_course_stmt = $conn->prepare("INSERT INTO course_item (course_id, inventory_id) VALUES (?, ?)");
            while ($course_row = $course_stmt->fetch(PDO::FETCH_ASSOC)) {
                $insert_course_stmt->execute([$course_row['course_id'], $new_inventory_id]);
            }

        }

        $activity_description = "New size added for {$originalItem['item_name']} ({$newItemCode}) - Size: {$newSize}, Initial stock: {$newQuantity}, Damage: {$newDamage}";
        $log_activity_query = "INSERT INTO activities (action_type, description, item_code, user_id, timestamp) VALUES ('Add Item Size', ?, ?, ?, NOW())";
        $stmt = $conn->prepare($log_activity_query);
        $user_id = $_SESSION['user_id'] ?? null;
        $stmt->execute([$activity_description, $newItemCode, $user_id]);
    }

    if (!empty($errors)) {
        throw new Exception(implode("\n", $errors));
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'New sizes added successfully']);

} catch (Exception $e) {
    if ($conn instanceof PDO && $conn->inTransaction()) { $conn->rollBack(); }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// PDO will close automatically
?> 