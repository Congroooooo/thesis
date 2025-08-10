<?php
header('Content-Type: application/json');

require_once '../Includes/connection.php'; // PDO $conn

// Get unique products based on item code prefix
$sql = "SELECT DISTINCT 
        SUBSTRING_INDEX(item_code, '-', 1) as prefix,
        item_name,
        category
        FROM inventory 
        WHERE actual_quantity > 0
        ORDER BY item_name";

try {
    $stmt = $conn->query($sql);
} catch (Exception $e) {
    die(json_encode([
        'success' => false,
        'message' => 'Query failed: ' . $e->getMessage()
    ]));
}

$products = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Get available sizes for this product
    $sizes_sql = "SELECT DISTINCT sizes, actual_quantity, item_code 
                  FROM inventory 
                  WHERE item_code LIKE ? AND actual_quantity > 0
                  ORDER BY sizes";
    
    $stmt2 = $conn->prepare($sizes_sql);
    $prefix = $row['prefix'] . '-%';
    $stmt2->execute([$prefix]);
    $sizes_result = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    $available_sizes = [];
    foreach ($sizes_result as $size_row) {
        $available_sizes[] = [
            'size' => $size_row['sizes'],
            'quantity' => $size_row['actual_quantity'],
            'item_code' => $size_row['item_code'],
            'category' => $row['category']
        ];
    }
    
    $products[] = [
        'prefix' => $row['prefix'],
        'name' => $row['item_name'],
        'category' => $row['category'],
        'available_sizes' => $available_sizes
    ];
}

echo json_encode([
    'success' => true,
    'products' => $products
]);
?> 