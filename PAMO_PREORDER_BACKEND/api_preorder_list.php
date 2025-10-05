<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../Includes/connection.php';

try {
    $status = $_GET['status'] ?? '';
    $limit = max(1, intval($_GET['limit'] ?? 50));
    $offset = max(0, intval($_GET['offset'] ?? 0));

    $where = [];
    $params = [];
    if ($status !== '') {
        $where[] = 'pi.status = ?';
        $params[] = $status;
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql = "
        SELECT 
            pi.id,
            pi.base_item_code,
            pi.item_name,
            pi.category_id,
            pi.price,
            pi.status,
            pi.image_path,
            pi.created_at,
            COALESCE(SUM(CASE WHEN pr.status = 'active' THEN pr.quantity ELSE 0 END), 0) AS total_requests
        FROM preorder_items pi
        LEFT JOIN preorder_requests pr ON pr.preorder_item_id = pi.id
        $whereSql
        GROUP BY pi.id
        ORDER BY pi.created_at DESC
        LIMIT $limit OFFSET $offset
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add all standard sizes to each preorder item since they're all available for pre-order
    $standardSizes = 'XS,S,M,L,XL,XXL,3XL,4XL,5XL,6XL,7XL,One Size';
    foreach ($rows as &$row) {
        $row['sizes'] = $standardSizes;
    }

    echo json_encode(['success' => true, 'items' => $rows]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>


