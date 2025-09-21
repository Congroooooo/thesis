<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../Includes/connection.php'; // PDO $conn

$item_code = isset($_GET['item_code']) ? trim($_GET['item_code']) : '';

if (!$item_code) {
    echo json_encode(['success' => false, 'message' => 'Item code is required']);
    exit;
}

try {
    $prefix = explode('-', $item_code)[0];
    
    $sql = "SELECT 
                item_code,
                sizes as size,
                actual_quantity,
                item_name,
                category,
                price
            FROM inventory 
            WHERE item_code LIKE ? 
            AND actual_quantity > 0
            ORDER BY sizes";

    $stmt = $conn->prepare($sql);
    $prefix_pattern = $prefix . '-%';
    $stmt->execute([$prefix_pattern]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sizes = [];
    foreach ($rows as $row) {
        $sizes[] = [
            'item_code' => $row['item_code'],
            'size' => $row['size'],
            'actual_quantity' => intval($row['actual_quantity']),
            'item_name' => $row['item_name'],
            'category' => $row['category'],
            'price' => $row['price']
        ];
    }

    echo json_encode([
        'success' => true,
        'sizes' => $sizes
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching available sizes: ' . $e->getMessage()
    ]);
}
?> 