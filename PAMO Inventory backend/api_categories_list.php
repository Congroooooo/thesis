<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../Includes/connection.php';

try {
    $stmt = $conn->query("SELECT id, name, has_subcategories FROM categories ORDER BY name ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

