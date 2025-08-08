<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../Includes/connection.php';

try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) { throw new Exception('Invalid JSON'); }
    $name = trim($data['name'] ?? '');
    $has = (int)($data['has_subcategories'] ?? 0);
    if ($name === '') { throw new Exception('Name required'); }

    $stmt = $conn->prepare('INSERT INTO categories (name, has_subcategories) VALUES (?, ?)');
    $stmt->execute([$name, $has]);
    echo json_encode(['success' => true, 'id' => $conn->lastInsertId()]);
    exit;
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

