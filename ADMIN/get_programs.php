<?php
header('Content-Type: application/json');
require_once '../Includes/connection.php';

try {
    $category = $_GET['category'] ?? '';
    
    if (empty($category)) {
        throw new Exception('Category parameter is required');
    }

    $tableCheck = $conn->query("SHOW TABLES LIKE 'programs_positions'");
    if ($tableCheck->rowCount() == 0) {
        echo json_encode([]);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, name, abbreviation FROM programs_positions WHERE category = ? AND is_active = 1 ORDER BY name ASC");
    $stmt->execute([$category]);
    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($programs);
    
} catch (Exception $e) {
    echo json_encode([]);
}
?>
