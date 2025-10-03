<?php
include '../Includes/connection.php';

$category = $_GET['category'] ?? '';
$subcategory = $_GET['subcategory'] ?? '';
$period = $_GET['period'] ?? 'daily';

// Determine group by clause based on period
if ($period === 'monthly') {
    $dateSelect = "DATE_FORMAT(s.sale_date, '%Y-%m')";
    $groupBy = "DATE_FORMAT(s.sale_date, '%Y-%m')";
} elseif ($period === 'yearly') {
    $dateSelect = "YEAR(s.sale_date)";
    $groupBy = "YEAR(s.sale_date)";
} else { // daily
    $dateSelect = "DATE(s.sale_date)";
    $groupBy = "DATE(s.sale_date)";
}

if ($subcategory) {
    // Filter by specific subcategory
    $query = "SELECT $dateSelect as date, SUM(s.quantity) as total_sales, c.name as category, sc.name as subcategory
              FROM sales s
              LEFT JOIN inventory i ON s.item_code = i.item_code
              LEFT JOIN inventory_subcategory isub ON isub.inventory_id = i.id
              LEFT JOIN subcategories sc ON sc.id = isub.subcategory_id
              LEFT JOIN categories c ON c.id = sc.category_id
              WHERE sc.id = :subcategory";
    $params = [':subcategory' => $subcategory];
    $query .= " GROUP BY $groupBy ORDER BY date ASC";
} elseif ($category) {
    // Filter by category
    $query = "SELECT $dateSelect as date, SUM(s.quantity) as total_sales, c.name as category, NULL as subcategory
              FROM sales s
              LEFT JOIN inventory i ON s.item_code = i.item_code
              LEFT JOIN inventory_subcategory isub ON isub.inventory_id = i.id
              LEFT JOIN subcategories sc ON sc.id = isub.subcategory_id
              LEFT JOIN categories c ON c.id = sc.category_id
              WHERE c.id = :category";
    $params = [':category' => $category];
    $query .= " GROUP BY $groupBy ORDER BY date ASC";
} else {
    // No filters, show all sales
    $query = "SELECT $dateSelect as date, SUM(s.quantity) as total_sales, c.name as category, NULL as subcategory
              FROM sales s
              LEFT JOIN inventory i ON s.item_code = i.item_code
              LEFT JOIN inventory_subcategory isub ON isub.inventory_id = i.id
              LEFT JOIN subcategories sc ON sc.id = isub.subcategory_id
              LEFT JOIN categories c ON c.id = sc.category_id
              WHERE 1";
    $params = [];
    $query .= " GROUP BY $groupBy ORDER BY date ASC";
}

$stmt = $conn->prepare($query);
$stmt->execute($params);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($data);