<?php
date_default_timezone_set('Asia/Manila');
session_start();
require_once '../Includes/connection.php';

$firstName = $_POST['firstName'] ?? '';
$lastName = $_POST['lastName'] ?? '';
$course = $_POST['course'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
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

// Generate order number in the format SI-<mmdd>-<sequential>
$order_number = '';
if (!empty($selected_items)) {
    $prefix = 'SI';
    $date_part = date('md');
    $today = date('Y-m-d');
    $like_pattern = $prefix . '-' . $date_part . '-%';
    // Get the last order_number from orders
    $stmt1 = $conn->prepare("SELECT order_number FROM orders WHERE order_number LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt1->execute([$like_pattern]);
    $last_order = $stmt1->fetch(PDO::FETCH_ASSOC);
    // Get the last transaction_number from sales
    $stmt2 = $conn->prepare("SELECT transaction_number FROM sales WHERE transaction_number LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt2->execute([$like_pattern]);
    $last_sales = $stmt2->fetch(PDO::FETCH_ASSOC);
    $last_seq = 0;
    if ($last_order && preg_match('/(\d{6})$/', $last_order['order_number'], $matches1)) {
        $last_seq = max($last_seq, (int)$matches1[1]);
    }
    if ($last_sales && preg_match('/(\d{6})$/', $last_sales['transaction_number'], $matches2)) {
        $last_seq = max($last_seq, (int)$matches2[1]);
    }
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
        INSERT INTO orders (order_number, user_id, items, phone, total_amount, status, payment_date) 
        VALUES (?, ?, ?, ?, ?, 'pending', NULL)
    ");
    $stmt->execute([
        $order_number,
        $_SESSION['user_id'],
        json_encode($selected_items),
        $phone,
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