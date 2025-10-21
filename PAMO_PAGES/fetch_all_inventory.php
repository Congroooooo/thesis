<?php
ob_start();
header('Content-Type: application/json');
header('Cache-Control: public, max-age=300'); // Cache for 5 minutes

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}
$role = strtoupper($_SESSION['role_category'] ?? '');
$programAbbr = strtoupper($_SESSION['program_abbreviation'] ?? '');
if (!($role === 'EMPLOYEE' && $programAbbr === 'PAMO')) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once '../Includes/connection.php';
require_once 'includes/config_functions.php';

$lowStockThreshold = getLowStockThreshold($conn);

try {
    // Fetch ALL inventory items - no pagination
    // We'll do pagination on the client side
    $sql = "SELECT 
                item_code, 
                item_name, 
                category, 
                actual_quantity, 
                sizes, 
                price, 
                created_at 
            FROM inventory 
            ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process items and add status
    foreach ($items as &$item) {
        if ($item['actual_quantity'] <= 0) {
            $item['status'] = 'Out of Stock';
            $item['statusClass'] = 'status-out-of-stock';
        } else if ($item['actual_quantity'] <= $lowStockThreshold) {
            $item['status'] = 'Low Stock';
            $item['statusClass'] = 'status-low-stock';
        } else {
            $item['status'] = 'In Stock';
            $item['statusClass'] = 'status-in-stock';
        }
        
        // Format price for display
        $item['priceFormatted'] = '₱' . number_format($item['price'], 2);
        
        // Create searchable text (lowercase for case-insensitive search)
        $item['searchText'] = strtolower(
            $item['item_code'] . ' ' . 
            $item['item_name'] . ' ' . 
            $item['category'] . ' ' . 
            $item['sizes']
        );
    }
    
    echo json_encode([
        'success' => true,
        'items' => $items,
        'total' => count($items),
        'lowStockThreshold' => $lowStockThreshold
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in fetch_all_inventory: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'items' => []
    ]);
}

ob_end_flush();
?>
