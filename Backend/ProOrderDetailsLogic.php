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
if (!empty($selected_items)) {
    $prefix = 'SI';
    $date_part = date('md');
    $like_pattern = $prefix . '-' . $date_part . '-%';
    $sql = "
        SELECT MAX(seq) AS max_seq FROM (
            SELECT CAST(SUBSTRING(order_number, 10) AS UNSIGNED) AS seq
            FROM `orders`
            WHERE order_number LIKE ?
            UNION ALL
            SELECT CAST(SUBSTRING(transaction_number, 10) AS UNSIGNED) AS seq
            FROM sales
            WHERE transaction_number LIKE ?
        ) AS all_orders
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$like_pattern, $like_pattern]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $last_seq = $row && $row['max_seq'] ? (int)$row['max_seq'] : 0;
    $new_seq = $last_seq + 1;
    $order_number = sprintf('%s-%s-%06d', $prefix, $date_part, $new_seq);
}

try {
    $conn->beginTransaction();
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