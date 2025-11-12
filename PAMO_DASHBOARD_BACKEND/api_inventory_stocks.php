<?php
include '../Includes/connection.php';

$category = $_GET['category'] ?? '';
$subcategory = $_GET['subcategory'] ?? '';

if ($subcategory) {
    // If subcategory is selected, group by subcategory
    // Sum actual_quantity for items that belong to this subcategory
    $query = "SELECT sc.name as category, 
              SUM(i.actual_quantity) as quantity 
              FROM inventory i
              JOIN inventory_subcategory isub ON isub.inventory_id = i.id
              JOIN subcategories sc ON sc.id = isub.subcategory_id
              WHERE sc.id = :subcategory
              GROUP BY sc.id, sc.name";
    $params = [':subcategory' => $subcategory];
} else if ($category) {
    // If category is selected but no subcategory, get totals for subcategories in that category
    // Each item's quantity should only be counted once, so we use a subquery to get distinct items per subcategory
    $query = "SELECT sc.name as category, 
              SUM(i.actual_quantity) as quantity
              FROM inventory i
              JOIN inventory_subcategory isub ON isub.inventory_id = i.id
              JOIN subcategories sc ON sc.id = isub.subcategory_id
              WHERE sc.category_id = :category
              GROUP BY sc.id, sc.name
              ORDER BY sc.name";
    $params = [':category' => $category];
} else {
    // No filters, group by category
    // For items with multiple subcategories across different categories,
    // we need to ensure each item's quantity is only counted once per category
    $query = "SELECT c.name as category, 
              SUM(i.actual_quantity) as quantity
              FROM inventory i
              JOIN inventory_subcategory isub ON isub.inventory_id = i.id
              JOIN subcategories sc ON sc.id = isub.subcategory_id
              JOIN categories c ON c.id = sc.category_id
              GROUP BY c.id, c.name
              ORDER BY c.name";
    $params = [];
}

$stmt = $conn->prepare($query);
$stmt->execute($params);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If showing all categories, also include the total for validation
if (!$category && !$subcategory) {
    // Get the actual total from inventory table (single source of truth)
    $totalStmt = $conn->prepare("SELECT SUM(actual_quantity) as total FROM inventory");
    $totalStmt->execute();
    $totalResult = $totalStmt->fetch(PDO::FETCH_ASSOC);
    $actualTotal = intval($totalResult['total'] ?? 0);
    
    // Add metadata to response
    header('Content-Type: application/json');
    echo json_encode([
        'data' => $data,
        'total' => $actualTotal
    ]);
} else {
    echo json_encode($data);
}
