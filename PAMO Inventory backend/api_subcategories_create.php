<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../Includes/connection.php';

try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) { throw new Exception('Invalid JSON'); }
    $categoryId = (int)($data['category_id'] ?? 0);
    $name = trim($data['name'] ?? '');
    if ($categoryId <= 0 || $name === '') { throw new Exception('Invalid input'); }

    $stmt = $conn->prepare('INSERT INTO subcategories (category_id, name) VALUES (?, ?)');
    $stmt->execute([$categoryId, $name]);
    echo json_encode(['success' => true, 'id' => $conn->lastInsertId()]);
    exit;
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

