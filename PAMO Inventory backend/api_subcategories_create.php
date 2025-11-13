<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../Includes/connection.php';
require_once __DIR__ . '/../PAMO_PAGES/includes/config_functions.php';

try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) { throw new Exception('Invalid JSON'); }
    $categoryId = (int)($data['category_id'] ?? 0);
    $name = trim($data['name'] ?? '');
    if ($categoryId <= 0 || $name === '') { throw new Exception('Invalid input'); }

    // Get category name for logging
    $catStmt = $conn->prepare('SELECT name FROM categories WHERE id = ?');
    $catStmt->execute([$categoryId]);
    $categoryName = $catStmt->fetchColumn();

    $stmt = $conn->prepare('INSERT INTO subcategories (category_id, name) VALUES (?, ?)');
    $stmt->execute([$categoryId, $name]);
    $subcategoryId = $conn->lastInsertId();
    
    // Log activity
    $user_id = $_SESSION['user_id'] ?? null;
    logActivity($conn, 'Subcategory Created', "Subcategory '$name' (ID: $subcategoryId) created under category '$categoryName'.", $user_id);
    
    echo json_encode(['success' => true, 'id' => $subcategoryId]);
    exit;
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

