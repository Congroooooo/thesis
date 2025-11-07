<?php
header('Content-Type: application/json');
require_once '../Includes/connection.php'; // PDO $conn

if (isset($_GET['item_code'])) {
    // Get the prefix from the item_code (before the dash)
    $item_code = $_GET['item_code'];
    $prefix = explode('-', $item_code)[0];
    $sql = "SELECT item_code, sizes FROM inventory WHERE item_code LIKE CONCAT(?, '-%')";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$prefix]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $sizes = [];
    foreach ($result as $row) {
        $sizes[] = [
            'size' => $row['sizes'],
            'item_code' => $row['item_code']
        ];
    }
    echo json_encode(['success' => true, 'sizes' => $sizes]);
    exit;
    exit;
}

// fallback for prefix param
$prefix = isset($_GET['prefix']) ? $_GET['prefix'] : '';
if (!$prefix) {
    echo json_encode(['success' => false, 'message' => 'No prefix provided']);
    exit;
}
$sql = "SELECT sizes FROM inventory WHERE item_code LIKE ?";
$stmt = $conn->prepare($sql);
$like = $prefix . '%';
$stmt->execute([$like]);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
$sizes = array_map(function($r){ return $r['sizes']; }, $result);
echo json_encode(['success' => true, 'sizes' => $sizes]);
?> 