<?php
header('Content-Type: application/json');
require_once '../Includes/connection.php';

if (!isset($_GET['item_code'])) {
    echo json_encode(['success' => false, 'message' => 'Item code is required']);
    exit;
}

$item_code = $_GET['item_code'];

try {
    $stmt = $conn->prepare("SELECT price FROM inventory WHERE item_code = ? LIMIT 1");
    $stmt->execute([$item_code]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'price' => $result['price']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Item not found'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
