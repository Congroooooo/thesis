<?php
header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host=localhost;dbname=proware", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $prefix = 'SI';
    $date_part = date('md');
    $like_pattern = $prefix . '-' . $date_part . '-%';

    // Use a single query to get the max suffix from both tables
    $sql = "
        SELECT MAX(seq) AS max_seq FROM (
            SELECT CAST(SUBSTRING(order_number, 10) AS UNSIGNED) AS seq
            FROM `orders`
            WHERE order_number LIKE ?
            UNION ALL
            SELECT CAST(SUBSTRING(transaction_number, 10) AS UNSIGNED) AS seq
            FROM sales
            WHERE transaction_number LIKE ?
        ) AS all_orders
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$like_pattern, $like_pattern]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $last_seq = $row && $row['max_seq'] ? (int)$row['max_seq'] : 0;
    $new_seq = $last_seq + 1;
    $next_order_number = sprintf('%s-%s-%06d', $prefix, $date_part, $new_seq);

    echo json_encode([
        'success' => true,
        'transaction_number' => $next_order_number
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} 