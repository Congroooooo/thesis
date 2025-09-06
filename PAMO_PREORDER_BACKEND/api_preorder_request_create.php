<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../Includes/connection.php';
session_start();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('POST required');
    $userId = intval($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) throw new Exception('Login required');

    $preId = intval($_POST['preorder_item_id'] ?? 0);
    $size = trim($_POST['size'] ?? '');
    $qty = max(1, intval($_POST['quantity'] ?? 1));
    if ($preId <= 0) throw new Exception('preorder_item_id required');

    $stmt = $conn->prepare('INSERT INTO preorder_requests (preorder_item_id, user_id, size, quantity) VALUES (?, ?, ?, ?)');
    $stmt->execute([$preId, $userId, $size ?: null, $qty]);

    echo json_encode(['success' => true]);
    exit;
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>


