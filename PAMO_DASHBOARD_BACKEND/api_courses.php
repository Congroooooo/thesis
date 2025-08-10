<?php
include '../Includes/connection.php';

// Return subcategory names under "Tertiary Uniform" category as the course list
$sql = "SELECT sc.name
        FROM subcategories sc
        JOIN categories c ON c.id = sc.category_id
        WHERE c.name = 'Tertiary Uniform'
        ORDER BY sc.name ASC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo json_encode($courses);