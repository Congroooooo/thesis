<?php
// fetch_reports.php: AJAX endpoint for paginated report tables
header('Content-Type: application/json');
$type = isset($_GET['type']) ? $_GET['type'] : 'inventory';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$size = isset($_GET['size']) ? trim($_GET['size']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$stockStatus = isset($_GET['stockStatus']) ? trim($_GET['stockStatus']) : '';
$startDate = isset($_GET['startDate']) ? trim($_GET['startDate']) : '';
$endDate = isset($_GET['endDate']) ? trim($_GET['endDate']) : '';

// Include the main connection file
require_once __DIR__ . '/../../Includes/connection.php';

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
    $where = [];
    $params = [];
    
    if ($category) {
        $where[] = "category = ?";
        $params[] = $category;
    }
    if ($size) {
        $where[] = "sizes = ?";
        $params[] = $size;
    }
    if ($status) {
        if ($status == 'In Stock') $where[] = "actual_quantity > 10";
        else if ($status == 'Low Stock') $where[] = "actual_quantity > 0 AND actual_quantity <= $lowStockThreshold";
        else if ($status == 'Out of Stock') $where[] = "actual_quantity <= 0";
    }
    // Apply stockStatus filter (new dedicated filter for inventory report)
    if ($stockStatus) {
        if ($stockStatus == 'In Stock') $where[] = "actual_quantity > $lowStockThreshold";
        else if ($stockStatus == 'Low Stock') $where[] = "actual_quantity > 0 AND actual_quantity <= $lowStockThreshold";
        else if ($stockStatus == 'Out of Stock') $where[] = "actual_quantity <= 0";
    }
    if ($search) {
        $where[] = "(item_name LIKE ? OR item_code LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    if ($startDate) {
        $where[] = "DATE(created_at) >= ?";
        $params[] = $startDate;
    }
    if ($endDate) {
        $where[] = "DATE(created_at) <= ?";
        $params[] = $endDate;
    }
    
    $where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Count total items
    $total_sql = "SELECT COUNT(*) as total FROM inventory $where_clause";
    $stmt = $conn->prepare($total_sql);
    $stmt->execute($params);
    $total_row = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_items = $total_row['total'];
    $total_pages = ceil($total_items / $limit);
    
    // Get paginated results
    $sql = "SELECT *, IFNULL(date_delivered, created_at) AS display_date FROM inventory $where_clause ORDER BY display_date DESC LIMIT $limit OFFSET $offset";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $tableHtml .= '<h3>Inventory Report</h3>';
    $tableHtml .= '<table><thead><tr>';
    $tableHtml .= '<th>Item Code</th><th>Item Name</th><th>Category</th><th>Beginning Quantity</th><th>New Delivery</th><th>Actual Quantity</th><th>Sold Quantity</th><th>Status</th><th>Date Delivered</th>';
    $tableHtml .= '</tr></thead><tbody>';
    $rowCount = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
        $tableHtml .= '<td>' . $row['beginning_quantity'] . '</td>';
        $tableHtml .= '<td>' . $row['new_delivery'] . '</td>';
        $tableHtml .= '<td>' . $row['actual_quantity'] . '</td>';
        $tableHtml .= '<td>' . $row['sold_quantity'] . '</td>';
        $tableHtml .= '<td>' . $status . '</td>';
        $tableHtml .= '<td>' . $row['display_date'] . '</td>';
        $tableHtml .= '</tr>';
    }
    if ($rowCount === 0) {
        $tableHtml .= '<tr><td colspan="9" style="text-align:center; background:#fffbe7; color:#bdb76b; font-size:1.1em; font-style:italic;">No results found.</td></tr>';
    }
    $tableHtml .= '</tbody></table>';
    $params = [];
    if ($search) $params['search'] = $search;
    if ($category) $params['category'] = $category;
    if ($size) $params['size'] = $size;
    if ($status) $params['status'] = $status;
    if ($stockStatus) $params['stockStatus'] = $stockStatus;
    if ($startDate) $params['startDate'] = $startDate;
    if ($endDate) $params['endDate'] = $endDate;
    $paginationHtml = render_pagination('inventory', $page, $total_pages, $params);
} elseif ($type === 'sales') {
    $where = [];
    $params = [];
    
    if ($search) {
        $where[] = "(s.transaction_number LIKE ? OR s.item_code LIKE ? OR i.item_name LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    if ($startDate) {
        $where[] = "DATE(s.sale_date) >= ?";
        $params[] = $startDate;
    }
    if ($endDate) {
        $where[] = "DATE(s.sale_date) <= ?";
        $params[] = $endDate;
    }
    
    $where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Count total items
    $total_sql = "SELECT COUNT(*) as total FROM sales s LEFT JOIN inventory i ON s.item_code = i.item_code $where_clause";
    $stmt = $conn->prepare($total_sql);
    $stmt->execute($params);
    $total_row = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_items = $total_row['total'];
    $total_pages = ceil($total_items / $limit);
    
    // Get paginated results - Group exchanges with originals (Original first, then Exchange)
    // Include ALL transaction types including Voided, Cancelled, and Rejected for sequence continuity
    $sql = "SELECT s.*, i.item_name FROM sales s LEFT JOIN inventory i ON s.item_code = i.item_code $where_clause 
            ORDER BY s.transaction_number DESC, 
                     CASE WHEN s.transaction_type = 'Original' OR s.transaction_type IS NULL THEN 0
                          WHEN s.transaction_type = 'Exchange' THEN 1
                          WHEN s.transaction_type = 'Return' THEN 2
                          WHEN s.transaction_type = 'Voided' THEN 3
                          WHEN s.transaction_type = 'Cancelled' THEN 4
                          WHEN s.transaction_type = 'Rejected' THEN 5
                          ELSE 6 END ASC,
                     s.id ASC 
            LIMIT $limit OFFSET $offset";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    // Calculate grand total for all filtered data - EXCLUDE voided, cancelled, and rejected
    $grand_total_sql = "SELECT SUM(s.total_amount) as grand_total 
                        FROM sales s 
                        LEFT JOIN inventory i ON s.item_code = i.item_code 
                        $where_clause 
                        AND (s.transaction_type NOT IN ('Voided', 'Cancelled', 'Rejected') OR s.transaction_type IS NULL)";
    $grand_stmt = $conn->prepare($grand_total_sql);
    $grand_stmt->execute($params);
    $grand_total_row = $grand_stmt->fetch(PDO::FETCH_ASSOC);
    $grand_total = $grand_total_row['grand_total'] ? $grand_total_row['grand_total'] : 0;
    $tableHtml .= '<h3>Sales Report</h3>';
    if ($startDate || $endDate || $search) {
        $tableHtml .= '<div class="total-amount-display" style="display: none;"><h4>Total Sales Amount: <span id="totalSalesAmount">₱0.00</span></h4></div>';
    }
    $tableHtml .= '<table><thead><tr>';
    $tableHtml .= '<th>Order Number</th><th>Item Code</th><th>Item Name</th><th>Size</th><th>Quantity</th><th>Price Per Item</th><th>Total Amount</th><th>Transaction Type</th><th>Sale Date</th>';
    $tableHtml .= '</tr></thead><tbody>';
    $rowCount = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rowCount++;
        $transactionType = $row['transaction_type'] ?? 'Original';
        $rowClass = in_array($transactionType, ['Voided', 'Cancelled', 'Rejected']) ? ' style="background-color: #ffebee; color: #c62828;"' : '';
        $tableHtml .= '<tr' . $rowClass . '>';
        $tableHtml .= '<td>' . $row['transaction_number'] . '</td>';
        $tableHtml .= '<td>' . $row['item_code'] . '</td>';
        $tableHtml .= '<td>' . $row['item_name'] . '</td>';
        $tableHtml .= '<td>' . $row['size'] . '</td>';
        $tableHtml .= '<td>' . $row['quantity'] . '</td>';
        $tableHtml .= '<td>₱' . number_format($row['price_per_item'], 2) . '</td>';
        $tableHtml .= '<td>₱' . number_format($row['total_amount'], 2) . '</td>';
        $tableHtml .= '<td>' . $transactionType . '</td>';
        $tableHtml .= '<td>' . $row['sale_date'] . '</td>';
        $tableHtml .= '</tr>';
    }
    if ($rowCount === 0) {
        $tableHtml .= '<tr><td colspan="9" style="text-align:center; background:#fffbe7; color:#bdb76b; font-size:1.1em; font-style:italic;">No results found.</td></tr>';
    }
    $tableHtml .= '</tbody></table>';
    $params = [];
    if ($search) $params['search'] = $search;
    if ($startDate) $params['startDate'] = $startDate;
    if ($endDate) $params['endDate'] = $endDate;
    $paginationHtml = render_pagination('sales', $page, $total_pages, $params);
} elseif ($type === 'audit') {
    $where = [];
    $params = [];
    
    if ($search) {
        $where[] = "(action_type LIKE ? OR item_code LIKE ? OR description LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    if ($startDate) {
        $where[] = "DATE(timestamp) >= ?";
        $params[] = $startDate;
    }
    if ($endDate) {
        $where[] = "DATE(timestamp) <= ?";
        $params[] = $endDate;
    }
    
    $where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Count total items
    $total_sql = "SELECT COUNT(*) as total FROM activities $where_clause";
    $stmt = $conn->prepare($total_sql);
    $stmt->execute($params);
    $total_row = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_items = $total_row['total'];
    $total_pages = ceil($total_items / $limit);
    
    // Get paginated results
    $sql = "SELECT * FROM activities $where_clause ORDER BY id DESC, timestamp DESC LIMIT $limit OFFSET $offset";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $tableHtml .= '<h3>Audit Trail</h3>';
    $tableHtml .= '<table><thead><tr>';
    $tableHtml .= '<th>Date/Time</th><th>Action Type</th><th>Description</th>';
    $tableHtml .= '</tr></thead><tbody>';
    $rowCount = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
}

// PDO connections close automatically
echo json_encode([
    'table' => $tableHtml,
    'pagination' => $paginationHtml,
    'grand_total' => isset($grand_total) ? $grand_total : 0
]); 