<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../Includes/connection.php';

try {
    // Get all categories with their subcategories
    $categoriesQuery = "
        SELECT 
            c.id, 
            c.name, 
            c.has_subcategories,
            GROUP_CONCAT(
                CONCAT(s.id, ':', s.name) 
                ORDER BY s.name ASC 
                SEPARATOR '|'
            ) as subcategories
        FROM categories c
        LEFT JOIN subcategories s ON c.id = s.category_id
        GROUP BY c.id, c.name, c.has_subcategories
        ORDER BY c.name ASC
    ";
    
    $stmt = $conn->query($categoriesQuery);
    $categories = [];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $subcategories = [];
        if ($row['subcategories']) {
            $subPairs = explode('|', $row['subcategories']);
            foreach ($subPairs as $pair) {
                $parts = explode(':', $pair, 2);
                if (count($parts) === 2) {
                    $subcategories[] = [
                        'id' => $parts[0],
                        'name' => $parts[1]
                    ];
                }
            }
        }
        
        $categories[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'has_subcategories' => (bool)$row['has_subcategories'],
            'subcategories' => $subcategories
        ];
    }
    
    // Also get legacy categories from inventory table that might not be in categories table yet
    $legacyCategoriesQuery = "
        SELECT DISTINCT category 
        FROM inventory 
        WHERE category NOT IN (SELECT name FROM categories)
        ORDER BY category ASC
    ";
    
    $legacyStmt = $conn->query($legacyCategoriesQuery);
    $legacyCategories = [];
    
    while ($row = $legacyStmt->fetch(PDO::FETCH_ASSOC)) {
        $legacyCategories[] = [
            'id' => null,
            'name' => $row['category'],
            'has_subcategories' => false,
            'subcategories' => [],
            'is_legacy' => true
        ];
    }
    
    echo json_encode([
        'success' => true,
        'categories' => array_merge($categories, $legacyCategories)
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>
