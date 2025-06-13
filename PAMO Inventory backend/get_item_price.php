<?php
header('Content-Type: application/json');

$conn = mysqli_connect("localhost", "root", "", "proware");

if (!$conn) {
    die(json_encode([
        'success' => false,
        'message' => 'Connection failed: ' . mysqli_connect_error()
    ]));
}

$prefix = isset($_GET['prefix']) ? mysqli_real_escape_string($conn, $_GET['prefix']) : '';
$size = isset($_GET['size']) ? mysqli_real_escape_string($conn, $_GET['size']) : '';

if (empty($prefix) || empty($size)) {
    die(json_encode([
        'success' => false,
        'message' => 'Prefix and size are required'
    ]));
}

// Find the item with the matching prefix and size
$sql = "SELECT price FROM inventory WHERE item_code LIKE ? AND sizes = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$prefix_pattern = $prefix . '-%';
$stmt->bind_param("ss", $prefix_pattern, $size);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
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

mysqli_close($conn);
?> 