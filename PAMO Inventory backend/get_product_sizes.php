<?php
header('Content-Type: application/json');

require_once '../Includes/connection.php'; // PDO $conn

// Get prefix parameter
$prefix = $_GET['prefix'] ?? '';

if (empty($prefix)) {
    echo json_encode([
        'success' => false,
        'message' => 'Product prefix is required'
    ]);
    exit;
}

try {
    // Get available sizes for this specific product
    $sizes_sql = "SELECT sizes, actual_quantity, item_code, category 
                  FROM inventory 
                  WHERE item_code LIKE ? AND actual_quantity > 0
                  ORDER BY 
                    CASE 
                        WHEN sizes = 'XS' THEN 1
                        WHEN sizes = 'S' THEN 2
                        WHEN sizes = 'M' THEN 3
                        WHEN sizes = 'L' THEN 4
                        WHEN sizes = 'XL' THEN 5
                        WHEN sizes = 'XXL' THEN 6
                        WHEN sizes = '3XL' THEN 7
                        WHEN sizes = '4XL' THEN 8
                        WHEN sizes = '5XL' THEN 9
                        WHEN sizes = '6XL' THEN 10
                        WHEN sizes = '7XL' THEN 11
                        WHEN sizes = 'One Size' THEN 12
                        ELSE 13
                    END ASC";
    
    $stmt = $conn->prepare($sizes_sql);
    $prefix_pattern = $prefix . '-%';
    $stmt->execute([$prefix_pattern]);
    $sizes_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $available_sizes = [];
    foreach ($sizes_result as $size_row) {
        $available_sizes[] = [
            'size' => $size_row['sizes'],
            'quantity' => $size_row['actual_quantity'],
            'item_code' => $size_row['item_code'],
            'category' => $size_row['category']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'prefix' => $prefix,
        'available_sizes' => $available_sizes
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
