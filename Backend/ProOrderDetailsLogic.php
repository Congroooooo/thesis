<?php
date_default_timezone_set('Asia/Manila');
session_start();
require_once '../Includes/connection.php';

// Check if user is blocked (has strikes or cooldown restrictions)
if (isset($_SESSION['user_id'])) {
    require_once '../Includes/strike_management.php';
    
    try {
        checkUserStrikeStatus($conn, $_SESSION['user_id'], false);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

$firstName = $_POST['firstName'] ?? '';
$lastName = $_POST['lastName'] ?? '';
$course = $_POST['course'] ?? '';
$email = $_POST['email'] ?? '';
$cart_items = json_decode($_POST['cart_items'] ?? '[]', true);
if (!is_array($cart_items)) {
    $cart_items = [];
}
$included_items = json_decode($_POST['included_items'] ?? '[]', true);
if (!is_array($included_items)) {
    $included_items = [];
}
$total_amount = $_POST['total_amount'] ?? 0;

$selected_items = array_filter($cart_items, function($item) use ($included_items) {
    return in_array($item['id'], $included_items);
});
$selected_items = array_values($selected_items);

$order_number = '';

try {
    $conn->beginTransaction();
    
    if (!empty($selected_items)) {
        $prefix = 'SI';
        $date_part = date('md');
        $date_key = $prefix . '-' . $date_part;
        $like_pattern = $date_key . '-%';
        
        // Check max sequence from BOTH orders and sales tables (online + physical shop)
        $checkStmt = $conn->prepare("
            SELECT MAX(seq) AS max_seq FROM (
                SELECT CAST(SUBSTRING(order_number, 10) AS UNSIGNED) AS seq
                FROM orders
                WHERE order_number LIKE ?
                UNION ALL
                SELECT CAST(SUBSTRING(transaction_number, 10) AS UNSIGNED) AS seq
                FROM sales
                WHERE transaction_number LIKE ?
            ) AS all_transactions
        ");
        $checkStmt->execute([$like_pattern, $like_pattern]);
        $checkRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
        $max_from_tables = $checkRow && $checkRow['max_seq'] ? (int)$checkRow['max_seq'] : 0;
        
        // Get or create sequence counter with row lock
        $stmt = $conn->prepare("
            INSERT INTO order_sequence (date_key, last_sequence, updated_at) 
            VALUES (?, 0, NOW())
            ON DUPLICATE KEY UPDATE updated_at = NOW()
        ");
        $stmt->execute([$date_key]);
        
        $stmt = $conn->prepare("
            SELECT last_sequence 
            FROM order_sequence 
            WHERE date_key = ? 
            FOR UPDATE
        ");
        $stmt->execute([$date_key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $seq_from_table = $row ? (int)$row['last_sequence'] : 0;
        
        // Use the higher value to ensure no conflicts with physical shop sales
        // This handles cases where physical sales were recorded but sequence wasn't updated
        $new_seq = max($seq_from_table, $max_from_tables) + 1;
        
        // Update sequence counter to the new value
        $updateStmt = $conn->prepare("
            UPDATE order_sequence 
            SET last_sequence = ?, updated_at = NOW() 
            WHERE date_key = ?
        ");
        $updateStmt->execute([$new_seq, $date_key]);
        
        $order_number = sprintf('%s-%s-%06d', $prefix, $date_part, $new_seq);
    }
    foreach ($selected_items as &$item) {
        if (isset($item['image_path'])) {
            $item['image_path'] = basename($item['image_path']);
        }
    }
    unset($item);
    $stmt = $conn->prepare("
        INSERT INTO orders (order_number, user_id, items, total_amount, status, payment_date, created_at, updated_at) 
        VALUES (?, ?, ?, ?, 'pending', NULL, NOW(), NOW())
    ");
    $stmt->execute([
        $order_number,
        $_SESSION['user_id'],
        json_encode($selected_items),
        $total_amount
    ]);
    if (!empty($included_items)) {
        $chunk_size = 500;
        $user_id = $_SESSION['user_id'];
        foreach (array_chunk($included_items, $chunk_size) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND id IN ($placeholders)");
            $params = array_merge([$user_id], $chunk);
            $stmt->execute($params);
        }
    }
    $conn->commit();
} catch (PDOException $e) {
    $conn->rollBack();
} 