<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../Includes/connection.php';
session_start();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('POST required');
    $userId = intval($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) throw new Exception('Login required');

    // Check if user is blocked (has strikes or cooldown restrictions)
    require_once __DIR__ . '/../Includes/strike_management.php';
    checkUserStrikeStatus($conn, $userId, false);

    // Get pre-order request data
    $preId = intval($_POST['preorder_item_id'] ?? 0);
    $size = trim($_POST['size'] ?? '');
    $qty = max(1, intval($_POST['quantity'] ?? 1));
    
    if ($preId <= 0) throw new Exception('preorder_item_id required');
    if (empty($size)) throw new Exception('Size selection required');
    
    // Get user details from account table
    $userStmt = $conn->prepare("SELECT first_name, last_name, email, id_number, role_category FROM account WHERE id = ?");
    $userStmt->execute([$userId]);
    $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) throw new Exception('User data not found');
    
    $customerName = trim($userData['first_name'] . ' ' . $userData['last_name']);
    $customerEmail = $userData['email'];
    $customerIdNumber = $userData['id_number'] ?? '';
    $roleCategory = strtoupper($userData['role_category'] ?? 'STUDENT');
    
    // Check if user already has a pending pre-order for this item
    $checkDuplicateStmt = $conn->prepare("
        SELECT COUNT(*) FROM preorder_orders 
        WHERE user_id = ? 
        AND preorder_item_id = ? 
        AND status IN ('pending', 'delivered')
    ");
    $checkDuplicateStmt->execute([$userId, $preId]);
    $existingCount = $checkDuplicateStmt->fetchColumn();
    
    if ($existingCount > 0) {
        throw new Exception('You already have a pending pre-order for this item. Please wait for it to be processed.');
    }
    
    // Get pre-order item details
    $itemStmt = $conn->prepare("SELECT * FROM preorder_items WHERE id = ? AND status = 'pending'");
    $itemStmt->execute([$preId]);
    $itemData = $itemStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$itemData) throw new Exception('Pre-order item not found or no longer available');
    
    // Generate unique pre-order number (format: PRE-MMDD-XXXXXX)
    $date = date('md');
    $countStmt = $conn->prepare("SELECT COUNT(*) FROM preorder_orders WHERE DATE(created_at) = CURDATE()");
    $countStmt->execute();
    $dailyCount = $countStmt->fetchColumn();
    $preorderNumber = 'PRE-' . $date . '-' . str_pad($dailyCount + 1, 6, '0', STR_PAD_LEFT);
    
    // Prepare items array (JSON format similar to regular orders)
    $items = [[
        'preorder_item_id' => $preId,
        'item_code' => $itemData['base_item_code'],
        'item_name' => $itemData['item_name'],
        'size' => $size,
        'price' => floatval($itemData['price']),
        'quantity' => $qty,
        'image_path' => $itemData['image_path']
    ]];
    
    $totalAmount = floatval($itemData['price']) * $qty;
    $itemsJson = json_encode($items);
    
    // Insert into preorder_orders table
    $insertStmt = $conn->prepare("
        INSERT INTO preorder_orders (
            preorder_number,
            preorder_item_id,
            user_id,
            customer_name,
            customer_email,
            customer_id_number,
            customer_role,
            items,
            total_amount,
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    
    $insertStmt->execute([
        $preorderNumber,
        $preId,
        $userId,
        $customerName,
        $customerEmail,
        $customerIdNumber,
        $roleCategory,
        $itemsJson,
        $totalAmount
    ]);
    
    // Also keep the old preorder_requests for backwards compatibility
    $stmt = $conn->prepare('INSERT INTO preorder_requests (preorder_item_id, user_id, size, quantity) VALUES (?, ?, ?, ?)');
    $stmt->execute([$preId, $userId, $size, $qty]);
    
    // Send notification to user
    require_once __DIR__ . '/../Includes/notification_operations.php';
    $notificationMessage = "Your pre-order request for '{$itemData['item_name']}' (PRE-ORDER #: {$preorderNumber}) has been submitted successfully. You will be notified when the item is available for pickup.";
    createNotification($conn, $userId, $notificationMessage, $preorderNumber, 'preorder_available');
    
    // Log activity (item_code is NULL since pre-order items may not exist in inventory yet)
    $activityDesc = "Pre-order submitted: {$itemData['item_name']} - Size: {$size}, Qty: {$qty}, PRE-ORDER #: {$preorderNumber}";
    $activityStmt = $conn->prepare("INSERT INTO activities (action_type, description, item_code, user_id, timestamp) VALUES (?, ?, ?, ?, NOW())");
    $activityStmt->execute(['PreOrder Created', $activityDesc, null, $userId]);

    echo json_encode([
        'success' => true,
        'preorder_number' => $preorderNumber,
        'message' => 'Pre-order submitted successfully!'
    ]);
    exit;
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>
