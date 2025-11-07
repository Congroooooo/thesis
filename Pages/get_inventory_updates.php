<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in JSON response

try {
    require_once '../Includes/connection.php';
    require_once '../Includes/inventory_update_notifier.php';
    
    // Get current timestamp
    $currentTime = date('Y-m-d H:i:s');
    
    // Check if client provided their last check timestamp
    $clientLastCheck = isset($_GET['last_check']) ? floatval($_GET['last_check']) : 0;
    
    // Get last inventory update info
    $lastUpdate = getLastInventoryUpdate();
    $hasUpdates = false;
    
    if ($clientLastCheck > 0 && $lastUpdate) {
        $hasUpdates = ($lastUpdate['microtime'] > $clientLastCheck);
    } else {
        // First check or no timestamp provided, always return data
        $hasUpdates = true;
    }
    
    // Get all current inventory with complete product information
    $query = "
        SELECT 
            i.item_code,
            i.item_name,
            i.category,
            i.image_path,
            i.actual_quantity as stock,
            i.sizes as size,
            i.price
        FROM inventory i
        WHERE i.actual_quantity >= 0
        ORDER BY i.item_code ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $inventoryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by base item code (without size suffix)
    $groupedInventory = [];
    
    foreach ($inventoryItems as $item) {
        $itemCode = $item['item_code'];
        $baseItemCode = strtok($itemCode, '-');
        
        // Process image path
        $imagePath = $item['image_path'] ?? '';
        $itemImage = '';
        if (!empty($imagePath)) {
            if (strpos($imagePath, 'uploads/') === false) {
                $itemImage = '../uploads/itemlist/' . $imagePath;
            } else {
                $itemImage = '../' . ltrim($imagePath, '/');
            }
        } else {
            $itemImage = '../uploads/itemlist/default.png';
        }
        
        if (!isset($groupedInventory[$baseItemCode])) {
            $groupedInventory[$baseItemCode] = [
                'base_item_code' => $baseItemCode,
                'name' => $item['item_name'] ?? '',
                'category' => $item['category'] ?? '',
                'image' => $itemImage,
                'variants' => [],
                'total_stock' => 0
            ];
        }
        
        $groupedInventory[$baseItemCode]['variants'][] = [
            'item_code' => $itemCode,
            'stock' => (int)$item['stock'],
            'size' => $item['size'] ?? '',
            'price' => $item['price'] ?? 0
        ];
        
        $groupedInventory[$baseItemCode]['total_stock'] += (int)$item['stock'];
    }
    
    // Build response
    $response = [
        'success' => true,
        'last_update' => $currentTime,
        'server_time' => microtime(true),
        'has_updates' => $hasUpdates,
        'count' => count($groupedInventory)
    ];
    
    // Include update source if available
    if ($lastUpdate && isset($lastUpdate['source'])) {
        $response['update_source'] = $lastUpdate['source'];
        $response['update_details'] = $lastUpdate['details'] ?? '';
    }
    
    // Only include full inventory data if there are updates or it's first check
    if ($hasUpdates) {
        $response['inventory'] = array_values($groupedInventory);
    } else {
        $response['inventory'] = []; // No changes
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
