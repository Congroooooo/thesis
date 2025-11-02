<?php
include '../Includes/connection.php';

$category = $_GET['category'] ?? '';
$subcategory = $_GET['subcategory'] ?? '';

if ($subcategory) {
    // If subcategory is selected, group by subcategory
    // Use DISTINCT to count each inventory item only once
    $query = "SELECT sc.name as category, 
              SUM(DISTINCT i.actual_quantity) as quantity 
              FROM (
                  SELECT DISTINCT i.id, i.actual_quantity
                  FROM inventory i
                  JOIN inventory_subcategory isub ON isub.inventory_id = i.id
                  WHERE isub.subcategory_id = :subcategory
              ) as i
              JOIN inventory_subcategory isub ON isub.inventory_id = i.id
              JOIN subcategories sc ON sc.id = isub.subcategory_id
              WHERE sc.id = :subcategory
              GROUP BY sc.name";
    $params = [':subcategory' => $subcategory];
} else if ($category) {
    // If category is selected but no subcategory, get totals for that category
    // Use DISTINCT to count each inventory item only once
    $query = "SELECT c.name as category, 
              SUM(DISTINCT_INV.quantity) as quantity
              FROM (
                  SELECT DISTINCT i.id, i.actual_quantity as quantity, sc.category_id
                  FROM inventory i
                  JOIN inventory_subcategory isub ON isub.inventory_id = i.id
                  JOIN subcategories sc ON sc.id = isub.subcategory_id
                  WHERE sc.category_id = :category
              ) as DISTINCT_INV
              JOIN categories c ON c.id = DISTINCT_INV.category_id
              GROUP BY c.name";
    $params = [':category' => $category];
} else {
    // No filters, group by category
    // Use DISTINCT to count each inventory item only once, even if it has multiple subcategories
    $query = "SELECT c.name as category, 
              SUM(DISTINCT_QTY.quantity) as quantity
              FROM (
                  SELECT i.id, c.id as category_id, c.name as category_name, i.actual_quantity as quantity
                  FROM inventory i
                  JOIN inventory_subcategory isub ON isub.inventory_id = i.id
                  JOIN subcategories sc ON sc.id = isub.subcategory_id
                  JOIN categories c ON c.id = sc.category_id
                  GROUP BY i.id, c.id, c.name, i.actual_quantity
              ) as DISTINCT_QTY
              JOIN categories c ON c.id = DISTINCT_QTY.category_id
              GROUP BY c.id, c.name";
    $params = [];
}

$stmt = $conn->prepare($query);
$stmt->execute($params);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($data);