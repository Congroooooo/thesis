<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../Includes/connection.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('POST required');

    $itemName = trim($_POST['item_name'] ?? '');
    $baseCode = trim($_POST['base_item_code'] ?? '');
    $categoryId = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? intval($_POST['category_id']) : null;
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
    $sizesCsv = trim($_POST['sizes'] ?? '');
    $subcategoryIds = isset($_POST['subcategory_ids']) && is_array($_POST['subcategory_ids']) ? $_POST['subcategory_ids'] : [];

    if ($itemName === '' || $baseCode === '' || $sizesCsv === '') {
        throw new Exception('item_name, base_item_code and sizes are required');
    }

    $imagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', strtolower($baseCode));
        $fileName = $safeName . '_' . time() . '.' . $ext;
        $targetDir = __DIR__ . '/../uploads/preorder/';
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $targetPath = $targetDir . $fileName;
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            throw new Exception('Failed to save uploaded image');
        }
        $imagePath = 'uploads/preorder/' . $fileName;
    }

    $stmt = $conn->prepare('INSERT INTO preorder_items (base_item_code, item_name, category_id, price, sizes, image_path) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$baseCode, $itemName, $categoryId, $price, $sizesCsv, $imagePath]);
    $preId = intval($conn->lastInsertId());

    if (!empty($subcategoryIds)) {
        $ins = $conn->prepare('INSERT INTO preorder_item_subcategory (preorder_item_id, subcategory_id) VALUES (?, ?)');
        foreach ($subcategoryIds as $sid) {
            $sid = intval($sid);
            if ($sid > 0) $ins->execute([$preId, $sid]);
        }
    }

    // Audit trail: log pre-order creation
    try {
        $userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
        $desc = sprintf('Pre-order created → base_code=%s, name=%s, price=%.2f, sizes=%s', $baseCode, $itemName, $price, $sizesCsv);
        $log = $conn->prepare('INSERT INTO activities (action_type, description, item_code, user_id, timestamp) VALUES (?,?,?,?, NOW())');
        $log->execute(['PreOrder Created', $desc, $baseCode, $userId]);
    } catch (Throwable $e) { /* best-effort logging */ }

    echo json_encode(['success' => true, 'id' => $preId, 'image_path' => $imagePath]);
    exit;
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>


