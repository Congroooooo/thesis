<?php
/**
 * API: Get Pre-Order Requests with Customer Details
 * Returns all pre-order requests grouped by item with full customer information
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../Includes/connection.php';
session_start();

try {
    // Check if user has PAMO access
    $role = strtoupper($_SESSION['role_category'] ?? '');
    $program = strtoupper($_SESSION['program_abbreviation'] ?? '');
    
    if ($role !== 'EMPLOYEE' || $program !== 'PAMO') {
        throw new Exception('Access denied. PAMO access required.');
    }
    
    $status = $_GET['status'] ?? 'pending';
    $limit = max(1, intval($_GET['limit'] ?? 100));
    $offset = max(0, intval($_GET['offset'] ?? 0));
    
    // Get all pre-order orders with customer details
    $sql = "
        SELECT 
            po.id,
            po.preorder_number,
            po.preorder_item_id,
            po.user_id,
            po.customer_name,
            po.customer_email,
            po.customer_id_number,
            po.customer_role,
            po.items,
            po.total_amount,
            po.status,
            po.payment_date,
            po.created_at,
            po.delivered_at,
            po.validation_deadline,
            po.voided_at,
            po.converted_to_order_id,
            pi.item_name,
            pi.base_item_code,
            pi.price as item_price,
            pi.image_path,
            pi.status as item_status,
            a.email as user_email,
            a.role_category,
            a.program_or_position
        FROM preorder_orders po
        INNER JOIN preorder_items pi ON po.preorder_item_id = pi.id
        INNER JOIN account a ON po.user_id = a.id
        WHERE po.status = ?
        ORDER BY po.created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$status]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $countSql = "SELECT COUNT(*) FROM preorder_orders WHERE status = ?";
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute([$status]);
    $totalCount = $countStmt->fetchColumn();
    
    // Process orders to decode items JSON
    foreach ($orders as &$order) {
        $order['items_decoded'] = json_decode($order['items'], true);
        $order['formatted_total'] = number_format(floatval($order['total_amount']), 2);
        $order['formatted_date'] = date('M d, Y h:i A', strtotime($order['created_at']));
        
        if ($order['delivered_at']) {
            $order['formatted_delivered_at'] = date('M d, Y h:i A', strtotime($order['delivered_at']));
        }
        if ($order['validation_deadline']) {
            $order['formatted_validation_deadline'] = date('M d, Y h:i A', strtotime($order['validation_deadline']));
        }
    }
    
    // Group by preorder_item_id for summary view
    $groupedByItem = [];
    foreach ($orders as $order) {
        $itemId = $order['preorder_item_id'];
        if (!isset($groupedByItem[$itemId])) {
            $groupedByItem[$itemId] = [
                'preorder_item_id' => $itemId,
                'item_name' => $order['item_name'],
                'base_item_code' => $order['base_item_code'],
                'item_price' => $order['item_price'],
                'image_path' => $order['image_path'],
                'item_status' => $order['item_status'],
                'total_orders' => 0,
                'total_quantity' => 0,
                'total_revenue' => 0,
                'sizes_breakdown' => [],
                'orders' => []
            ];
        }
        
        $groupedByItem[$itemId]['total_orders']++;
        $groupedByItem[$itemId]['total_revenue'] += floatval($order['total_amount']);
        $groupedByItem[$itemId]['orders'][] = $order;
        
        // Calculate size breakdown
        if (!empty($order['items_decoded'])) {
            foreach ($order['items_decoded'] as $item) {
                $size = $item['size'] ?? 'N/A';
                $qty = $item['quantity'] ?? 0;
                
                if (!isset($groupedByItem[$itemId]['sizes_breakdown'][$size])) {
                    $groupedByItem[$itemId]['sizes_breakdown'][$size] = 0;
                }
                $groupedByItem[$itemId]['sizes_breakdown'][$size] += $qty;
                $groupedByItem[$itemId]['total_quantity'] += $qty;
            }
        }
    }
    
    // Convert to indexed array
    $groupedByItem = array_values($groupedByItem);
    
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'grouped_by_item' => $groupedByItem,
        'total_count' => $totalCount,
        'status' => $status
    ]);
    exit;
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
