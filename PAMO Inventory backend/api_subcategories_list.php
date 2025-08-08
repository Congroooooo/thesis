<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../Includes/connection.php';

try {
    $categoryId = (int)($_GET['category_id'] ?? 0);
    if ($categoryId <= 0) { throw new Exception('category_id is required'); }
    $stmt = $conn->prepare('SELECT id, name FROM subcategories WHERE category_id = ? ORDER BY name ASC');
    $stmt->execute([$categoryId]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

