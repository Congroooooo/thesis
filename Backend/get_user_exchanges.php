<?php
/**
 * Get User Exchanges
 * Returns all exchange requests for the current user
 */

session_start();
header('Content-Type: application/json');
require_once '../Includes/connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

try {
    // Get user's exchanges
    $stmt = $conn->prepare("
        SELECT 
            oe.*,
            o.order_number,
            o.created_at as order_date,
            COUNT(oei.id) as items_count,
            SUM(oei.exchange_quantity) as total_quantity
        FROM order_exchanges oe
        JOIN orders o ON oe.order_id = o.id
        LEFT JOIN order_exchange_items oei ON oe.id = oei.exchange_id
        WHERE oe.user_id = ?
        GROUP BY oe.id
        ORDER BY oe.exchange_date DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $exchanges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get items for each exchange
    foreach ($exchanges as &$exchange) {
        $items_stmt = $conn->prepare("
            SELECT * FROM order_exchange_items WHERE exchange_id = ?
        ");
        $items_stmt->execute([$exchange['id']]);
        $exchange['items'] = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get activity log
        $activity_stmt = $conn->prepare("
            SELECT ea.*, a.first_name, a.last_name
            FROM exchange_activities ea
            LEFT JOIN account a ON ea.performed_by = a.id
            WHERE ea.exchange_id = ?
            ORDER BY ea.performed_at DESC
        ");
        $activity_stmt->execute([$exchange['id']]);
        $exchange['activities'] = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($exchange);
    
    echo json_encode([
        'success' => true,
        'exchanges' => $exchanges
    ]);
    
} catch (PDOException $e) {
    error_log("Get exchanges error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving exchanges'
    ]);
}
