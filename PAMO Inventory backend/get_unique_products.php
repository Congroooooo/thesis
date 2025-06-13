<?php
header('Content-Type: application/json');

$conn = mysqli_connect("localhost", "root", "", "proware");

if (!$conn) {
    die(json_encode([
        'success' => false,
        'message' => 'Connection failed: ' . mysqli_connect_error()
    ]));
}

// Get unique products based on item code prefix
$sql = "SELECT DISTINCT 
        SUBSTRING_INDEX(item_code, '-', 1) as prefix,
        item_name,
        category
        FROM inventory 
        WHERE actual_quantity > 0
        ORDER BY item_name";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die(json_encode([
        'success' => false,
        'message' => 'Query failed: ' . mysqli_error($conn)
    ]));
}

$products = [];
while ($row = mysqli_fetch_assoc($result)) {
    // Get available sizes for this product
    $sizes_sql = "SELECT DISTINCT sizes, actual_quantity, item_code 
                  FROM inventory 
                  WHERE item_code LIKE ? AND actual_quantity > 0
                  ORDER BY sizes";
    
    $stmt = $conn->prepare($sizes_sql);
    $prefix = $row['prefix'] . '-%';
    $stmt->bind_param("s", $prefix);
    $stmt->execute();
    $sizes_result = $stmt->get_result();
    
    $available_sizes = [];
    while ($size_row = $sizes_result->fetch_assoc()) {
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

mysqli_close($conn);
?> 