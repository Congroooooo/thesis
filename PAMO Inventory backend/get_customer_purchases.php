<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../Includes/connection.php'; // PDO $conn

$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

if (!$customer_id) {
    echo json_encode(['success' => false, 'message' => 'Customer ID is required']);
    exit;
}

try {
    $sql = "SELECT 
                s.id as sales_id,
                s.transaction_number,
                tc.customer_id,
                s.item_code,
                s.size,
                s.quantity,
                s.price_per_item,
                s.total_amount,
                s.sale_date,
                i.item_name,
                i.category
            FROM sales s
            INNER JOIN inventory i ON s.item_code = i.item_code
            INNER JOIN transaction_customers tc ON s.transaction_number = tc.transaction_number
            WHERE tc.customer_id = ? 
            AND s.sale_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY s.sale_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$customer_id]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $purchases = [];
    foreach ($result as $row) {
        $purchases[] = [
            'sales_id' => $row['sales_id'],
            'transaction_number' => $row['transaction_number'],
            'item_code' => $row['item_code'],
            'item_name' => $row['item_name'],
            'category' => $row['category'],
            'size' => $row['size'],
            'quantity' => $row['quantity'],
            'price_per_item' => $row['price_per_item'],
            'total_amount' => $row['total_amount'],
            'sale_date' => $row['sale_date']
        ];
    }

    echo json_encode([
        'success' => true,
        'purchases' => $purchases,
        'customer_filtered' => true,
        'note' => 'Showing customer-specific purchases within 24 hours.'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching purchases: ' . $e->getMessage()
    ]);
}
// PDO closes automatically
?> 