<?php
header('Content-Type: application/json');

require_once '../Includes/connection.php'; // PDO $conn

$prefix = isset($_GET['prefix']) ? trim($_GET['prefix']) : '';
$size = isset($_GET['size']) ? trim($_GET['size']) : '';

if (empty($prefix) || empty($size)) {
    die(json_encode([
        'success' => false,
        'message' => 'Prefix and size are required'
    ]));
}

// Find the item with the matching prefix and size (case/space insensitive)
$sql = "SELECT price FROM inventory WHERE item_code LIKE ? AND TRIM(UPPER(sizes)) = TRIM(UPPER(?)) LIMIT 1";
$stmt = $conn->prepare($sql);
$prefix_pattern = $prefix . '-%';
$stmt->execute([$prefix_pattern, $size]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    echo json_encode([
        'success' => true,
        'price' => $row['price']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Item not found'
    ]);
}

?> 