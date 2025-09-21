<?php
header('Content-Type: application/json');
$type = isset($_GET['type']) ? $_GET['type'] : 'inventory';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$size = isset($_GET['size']) ? trim($_GET['size']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$startDate = isset($_GET['startDate']) ? trim($_GET['startDate']) : '';
$endDate = isset($_GET['endDate']) ? trim($_GET['endDate']) : '';

require_once '../../Includes/connection.php';
if (!$conn) {
    echo json_encode([
        'table' => '<div class="error">PDO connection not available</div>',
        'pagination' => ''
    ]);
    exit;
}

include_once __DIR__ . '/config_functions.php';
$lowStockThreshold = getLowStockThreshold($conn);

function render_pagination($type, $page, $total_pages, $query_params) {
    if ($total_pages <= 1) return '';
    $window = 2;
    $start = max(1, $page - $window);
    $end = min($total_pages, $page + $window);
    if ($total_pages <= 5) {
        $start = 1;
        $end = $total_pages;
    }
    $query_string = http_build_query($query_params);
    $html = '<div class="pagination">';
    if ($page > 1) {
        $html .= '<a href="?' . http_build_query(array_merge($query_params, ['type'=>$type, 'page'=>$page-1])) . '" class="ajax-page-link">&laquo; Prev</a>';
    }
    for ($i = $start; $i <= $end; $i++) {
        $active = ($i == $page) ? ' active' : '';
        $html .= '<a href="?' . http_build_query(array_merge($query_params, ['type'=>$type, 'page'=>$i])) . '" class="ajax-page-link' . $active . '">' . $i . '</a>';
    }
    if ($page < $total_pages) {
        $html .= '<a href="?' . http_build_query(array_merge($query_params, ['type'=>$type, 'page'=>$page+1])) . '" class="ajax-page-link">Next &raquo;</a>';
    }
    $html .= '</div>';
    return $html;
}

$tableHtml = '';
$paginationHtml = '';

if ($type === 'inventory') {
    $where_conditions = [];
    $params = [];
    
    if ($category) {
        $where_conditions[] = "category = ?";
        $params[] = $category;
    }
    if ($size) {
        $where_conditions[] = "sizes = ?";
        $params[] = $size;
    }
    if ($status) {
        if ($status == 'In Stock') {
            $where_conditions[] = "actual_quantity > ?";
            $params[] = $lowStockThreshold;
        } else if ($status == 'Low Stock') {
            $where_conditions[] = "actual_quantity > 0 AND actual_quantity <= ?";
            $params[] = $lowStockThreshold;
        } else if ($status == 'Out of Stock') {
            $where_conditions[] = "actual_quantity <= 0";
        }
    }
    if ($search) {
        $where_conditions[] = "(item_name LIKE ? OR item_code LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($startDate) {
        $where_conditions[] = "DATE(created_at) >= ?";
        $params[] = $startDate;
    }
    if ($endDate) {
        $where_conditions[] = "DATE(created_at) <= ?";
        $params[] = $endDate;
    }
    
    $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    try {
        // Get total count
        $total_sql = "SELECT COUNT(*) as total FROM inventory $where_clause";
        $total_stmt = $conn->prepare($total_sql);
        $total_stmt->execute($params);
        $total_row = $total_stmt->fetch(PDO::FETCH_ASSOC);
        $total_items = $total_row['total'];
        $total_pages = ceil($total_items / $limit);
        
        // Get inventory data
        $sql = "SELECT * FROM inventory $where_clause ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug: Log query results (commented out to prevent JSON corruption)
        // error_log("Inventory query returned " . count($result) . " rows");
        // error_log("Total items: " . $total_items);
        
        $tableHtml .= '<h3>Inventory Report</h3>';
        $tableHtml .= '<table><thead><tr>';
        $tableHtml .= '<th>Item Code</th><th>Item Name</th><th>Category</th><th>Quantity</th><th>Size</th><th>Price</th><th>Status</th><th>Created Date</th>';
        $tableHtml .= '</tr></thead><tbody>';
        $rowCount = 0;
        
        foreach ($result as $row) {
        $rowCount++;
        // Calculate status based on actual_quantity and threshold
        if ($row['actual_quantity'] <= 0) {
            $status = 'out of stock';
        } else if ($row['actual_quantity'] <= $lowStockThreshold) {
            $status = 'low stock';
        } else {
            $status = 'in stock';
        }
        $tableHtml .= '<tr>';
        $tableHtml .= '<td>' . $row['item_code'] . '</td>';
        $tableHtml .= '<td>' . $row['item_name'] . '</td>';
        $tableHtml .= '<td>' . $row['category'] . '</td>';
        $tableHtml .= '<td>' . $row['actual_quantity'] . '</td>';
        $tableHtml .= '<td>' . $row['sizes'] . '</td>';
        $tableHtml .= '<td>₱' . number_format($row['price'], 2) . '</td>';
        $tableHtml .= '<td>' . $status . '</td>';
        $tableHtml .= '<td>' . date('M d, Y', strtotime($row['created_at'])) . '</td>';
        $tableHtml .= '</tr>';
    }
    if ($rowCount === 0) {
        $tableHtml .= '<tr><td colspan="8" style="text-align:center; background:#fffbe7; color:#bdb76b; font-size:1.1em; font-style:italic;">No results found.</td></tr>';
    }
    $tableHtml .= '</tbody></table>';
    $params = [];
    if ($search) $params['search'] = $search;
    if ($category) $params['category'] = $category;
    if ($size) $params['size'] = $size;
    if ($status) $params['status'] = $status;
    $paginationHtml = render_pagination('inventory', $page, $total_pages, $params);
    
    } catch (PDOException $e) {
        $tableHtml = '<div class="error">Database error: ' . $e->getMessage() . '</div>';
        $paginationHtml = '';
    }
} elseif ($type === 'sales') {
    $where_conditions = [];
    $params = [];
    
    if ($search) {
        $where_conditions[] = "(s.transaction_number LIKE ? OR s.item_code LIKE ? OR i.item_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($startDate) {
        $where_conditions[] = "DATE(s.sale_date) >= ?";
        $params[] = $startDate;
    }
    if ($endDate) {
        $where_conditions[] = "DATE(s.sale_date) <= ?";
        $params[] = $endDate;
    }
    
    $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    try {
        $total_sql = "SELECT COUNT(*) as total FROM sales s LEFT JOIN inventory i ON s.item_code = i.item_code $where_clause";
        $total_stmt = $conn->prepare($total_sql);
        $total_stmt->execute($params);
        $total_row = $total_stmt->fetch(PDO::FETCH_ASSOC);
        $total_items = $total_row['total'];
        $total_pages = ceil($total_items / $limit);

        $sql = "SELECT s.*, i.item_name FROM sales s LEFT JOIN inventory i ON s.item_code = i.item_code $where_clause ORDER BY s.sale_date DESC LIMIT $limit OFFSET $offset";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grand_total_sql = "SELECT SUM(s.total_amount) as grand_total FROM sales s LEFT JOIN inventory i ON s.item_code = i.item_code $where_clause";
        $grand_total_stmt = $conn->prepare($grand_total_sql);
        $grand_total_stmt->execute($params);
        $grand_total_row = $grand_total_stmt->fetch(PDO::FETCH_ASSOC);
        $grand_total = $grand_total_row['grand_total'] ? $grand_total_row['grand_total'] : 0;
        
        $tableHtml .= '<h3>Sales Report</h3>';
        if ($startDate || $endDate || $search) {
            $tableHtml .= '<div class="total-amount-display" style="display: none;"><h4>Total Sales Amount: <span id="totalSalesAmount">₱0.00</span></h4></div>';
        }
        $tableHtml .= '<table><thead><tr>';
        $tableHtml .= '<th>Order Number</th><th>Item Code</th><th>Item Name</th><th>Size</th><th>Quantity</th><th>Price Per Item</th><th>Total Amount</th><th>Sale Date</th>';
        $tableHtml .= '</tr></thead><tbody>';
        $rowCount = 0;
        
        foreach ($result as $row) {
        $rowCount++;
        $tableHtml .= '<tr>';
        $tableHtml .= '<td>' . $row['transaction_number'] . '</td>';
        $tableHtml .= '<td>' . $row['item_code'] . '</td>';
        $tableHtml .= '<td>' . $row['item_name'] . '</td>';
        $tableHtml .= '<td>' . $row['size'] . '</td>';
        $tableHtml .= '<td>' . $row['quantity'] . '</td>';
        $tableHtml .= '<td>₱' . number_format($row['price_per_item'], 2) . '</td>';
        $tableHtml .= '<td>₱' . number_format($row['total_amount'], 2) . '</td>';
        $tableHtml .= '<td>' . $row['sale_date'] . '</td>';
        $tableHtml .= '</tr>';
    }
    if ($rowCount === 0) {
        $tableHtml .= '<tr><td colspan="8" style="text-align:center; background:#fffbe7; color:#bdb76b; font-size:1.1em; font-style:italic;">No results found.</td></tr>';
    }
    $tableHtml .= '</tbody></table>';
    $params = [];
    if ($search) $params['search'] = $search;
    if ($startDate) $params['startDate'] = $startDate;
    if ($endDate) $params['endDate'] = $endDate;
    $paginationHtml = render_pagination('sales', $page, $total_pages, $params);
    
    } catch (PDOException $e) {
        $tableHtml = '<div class="error">Database error: ' . $e->getMessage() . '</div>';
        $paginationHtml = '';
    }
} elseif ($type === 'audit') {
    $where_conditions = [];
    $params = [];
    
    if ($search) {
        $where_conditions[] = "(action_type LIKE ? OR item_code LIKE ? OR description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($startDate) {
        $where_conditions[] = "DATE(timestamp) >= ?";
        $params[] = $startDate;
    }
    if ($endDate) {
        $where_conditions[] = "DATE(timestamp) <= ?";
        $params[] = $endDate;
    }
    
    $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    try {
        // Get total count
        $total_sql = "SELECT COUNT(*) as total FROM activities $where_clause";
        $total_stmt = $conn->prepare($total_sql);
        $total_stmt->execute($params);
        $total_row = $total_stmt->fetch(PDO::FETCH_ASSOC);
        $total_items = $total_row['total'];
        $total_pages = ceil($total_items / $limit);
        
        // Get audit data
        $sql = "SELECT * FROM activities $where_clause ORDER BY timestamp DESC LIMIT $limit OFFSET $offset";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $tableHtml .= '<h3>Audit Trail</h3>';
        $tableHtml .= '<table><thead><tr>';
        $tableHtml .= '<th>Date/Time</th><th>Action Type</th><th>Description</th>';
        $tableHtml .= '</tr></thead><tbody>';
        $rowCount = 0;
        
        foreach ($result as $row) {
            $rowCount++;
            $tableHtml .= '<tr>';
            $tableHtml .= '<td>' . $row['timestamp'] . '</td>';
            $tableHtml .= '<td>' . $row['action_type'] . '</td>';
            $tableHtml .= '<td>' . $row['description'] . '</td>';
            $tableHtml .= '</tr>';
        }
        if ($rowCount === 0) {
            $tableHtml .= '<tr><td colspan="3" style="text-align:center; background:#fffbe7; color:#bdb76b; font-size:1.1em; font-style:italic;">No results found.</td></tr>';
        }
        $tableHtml .= '</tbody></table>';
        $params = [];
        if ($search) $params['search'] = $search;
        if ($startDate) $params['startDate'] = $startDate;
        if ($endDate) $params['endDate'] = $endDate;
        $paginationHtml = render_pagination('audit', $page, $total_pages, $params);
        
    } catch (PDOException $e) {
        $tableHtml = '<div class="error">Database error: ' . $e->getMessage() . '</div>';
        $paginationHtml = '';
    }
}

// PDO connections close automatically, no need for explicit close
echo json_encode([
    'table' => $tableHtml,
    'pagination' => $paginationHtml,
    'grand_total' => isset($grand_total) ? $grand_total : 0
]); 