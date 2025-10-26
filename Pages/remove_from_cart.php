<?php
session_start();
require_once '../Includes/connection.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['item_id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$item_id = $_POST['item_id'];
$user_id = $_SESSION['user_id'];

try {
    // Prepare and execute the delete query by ID for precise removal
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND id = ?");
    $result = $stmt->execute([$user_id, $item_id]);

    if ($result && $stmt->rowCount() > 0) {
        // Get updated cart count - count unique items instead of total quantity
        $countStmt = $conn->prepare("SELECT COUNT(DISTINCT item_code) as total FROM cart WHERE user_id = ?");
        $countStmt->execute([$user_id]);
        $cartCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        // Update session
        $_SESSION['cart_count'] = $cartCount;
        
        echo json_encode(['success' => true, 'cart_count' => $cartCount]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Item not found in cart']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?> 