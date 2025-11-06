<?php
date_default_timezone_set('Asia/Manila');
// Prevent any output before headers
ob_start();
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

// Start session first, then set headers
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// Clean any previous output and set headers
ob_clean();
header('Content-Type: application/json');

require_once __DIR__ . '/../Includes/connection.php';
require_once __DIR__ . '/../Includes/notifications.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('POST required');

    $itemName = trim($_POST['item_name'] ?? '');
    $baseCode = trim($_POST['base_item_code'] ?? '');
    $categoryId = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? intval($_POST['category_id']) : null;
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
    $subcategoryIds = isset($_POST['subcategory_ids']) && is_array($_POST['subcategory_ids']) ? $_POST['subcategory_ids'] : [];

    if ($itemName === '' || $baseCode === '') {
        throw new Exception('item_name and base_item_code are required');
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

    $stmt = $conn->prepare('INSERT INTO preorder_items (base_item_code, item_name, category_id, price, image_path) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$baseCode, $itemName, $categoryId, $price, $imagePath]);
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
        
        $categoryName = null;
        if (!empty($categoryId)) {
            $catStmt = $conn->prepare('SELECT name FROM categories WHERE id = ?');
            $catStmt->execute([$categoryId]);
            $categoryName = $catStmt->fetchColumn();
        }
        
        $desc = sprintf('New pre-order item created → Item: %s, Code: %s, Category: %s, Price: ₱%.2f', 
                       $itemName, $baseCode, $categoryName ?: 'None', $price);
        
        $log = $conn->prepare('INSERT INTO activities (action_type, description, item_code, user_id) VALUES (?, ?, ?, ?)');
        $log->execute(['PreOrder Created', $desc, null, $userId]);
    } catch (Throwable $e) { 
        // Silent logging - don't break the process
    }

    // Send notification to all customers about new pre-order item
    try {
        // Get all active customers (students + employees, excluding PAMO/Admin staff)
        $customerStmt = $conn->prepare('
            SELECT id as user_id, CONCAT(first_name, " ", last_name) as name 
            FROM account 
            WHERE status = ? 
            AND role_category IN (?, ?, ?)
            AND (program_abbreviation IS NULL OR program_abbreviation NOT IN (?, ?))
        ');
        $customerStmt->execute(['active', 'COLLEGE STUDENT', 'SHS', 'EMPLOYEE', 'PAMO', 'ADMIN']);
        $customers = $customerStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($customers)) {
            $notificationMessage = "🆕 New Pre-Order Available: {$itemName} - ₱{$price}! Place your pre-order now.";
            
            foreach ($customers as $customer) {
                createNotification($conn, $customer['user_id'], $notificationMessage, $baseCode, 'preorder_available');
            }
        }
    } catch (Throwable $e) { 
        // Silent notification error - don't break the process
    }

    echo json_encode(['success' => true, 'id' => $preId, 'image_path' => $imagePath]);
    exit;
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>


