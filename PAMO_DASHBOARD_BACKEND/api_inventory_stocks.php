<?php
include '../Includes/connection.php';

$category = $_GET['category'] ?? '';
$subcategory = $_GET['subcategory'] ?? '';

if ($subcategory) {
    // If subcategory is selected, group by subcategory
    $query = "SELECT sc.name as category, SUM(i.actual_quantity) as quantity 
              FROM inventory i
              JOIN inventory_subcategory isub ON isub.inventory_id = i.id
              JOIN subcategories sc ON sc.id = isub.subcategory_id
              WHERE sc.id = :subcategory
              GROUP BY sc.name";
    $params = [':subcategory' => $subcategory];
} else if ($category) {
    // If category is selected but no subcategory, get totals for that category
    $query = "SELECT c.name as category, SUM(i.actual_quantity) as quantity 
              FROM inventory i
              JOIN inventory_subcategory isub ON isub.inventory_id = i.id
              JOIN subcategories sc ON sc.id = isub.subcategory_id
              JOIN categories c ON c.id = sc.category_id
              WHERE c.id = :category
              GROUP BY c.name";
    $params = [':category' => $category];
} else {
    // No filters, group by category
    $query = "SELECT c.name as category, SUM(i.actual_quantity) as quantity 
              FROM inventory i
              JOIN inventory_subcategory isub ON isub.inventory_id = i.id
              JOIN subcategories sc ON sc.id = isub.subcategory_id
              JOIN categories c ON c.id = sc.category_id
              GROUP BY c.id, c.name";
    $params = [];
}

$stmt = $conn->prepare($query);
$stmt->execute($params);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($data);