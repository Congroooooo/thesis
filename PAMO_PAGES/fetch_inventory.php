<?php
ob_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Pages/login.php?redirect=../PAMO_PAGES/fetch_inventory.php");
    exit();
}
$role = strtoupper($_SESSION['role_category'] ?? '');
$programAbbr = strtoupper($_SESSION['program_abbreviation'] ?? '');
if (!($role === 'EMPLOYEE' && $programAbbr === 'PAMO')) {
    header("Location: ../Pages/home.php");
    exit();
}

require_once '../Includes/connection.php';
require_once 'includes/config_functions.php';

$lowStockThreshold = getLowStockThreshold($conn);

$category = isset($_GET['category']) && $_GET['category'] !== '' ? $_GET['category'] : '';
$size = isset($_GET['size']) && $_GET['size'] !== '' ? $_GET['size'] : '';
$status = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : '';
$search = isset($_GET['search']) && $_GET['search'] !== '' ? trim($_GET['search']) : '';
$normalized_search = preg_replace('/\s+/', '', $search);
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$sort = isset($_GET['sort']) && in_array($_GET['sort'], ['asc', 'desc']) ? $_GET['sort'] : '';
$limit = 15;
$offset = ($page - 1) * $limit;
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
    if ($status == 'In Stock') {
        $where[] = "actual_quantity > 10";
    } else if ($status == 'Low Stock') {
        $where[] = "actual_quantity > 0 AND actual_quantity <= ?";
        $params[] = $lowStockThreshold;
    } else if ($status == 'Out of Stock') {
        $where[] = "actual_quantity <= 0";
    }
}
if ($search) {
    $keywords = array_filter(explode(' ', trim($search)));
    
    if (!empty($keywords)) {
        $search_conditions = [];
        
        foreach ($keywords as $keyword) {
            $search_conditions[] = "(CONCAT(item_name, ' ', item_code, ' ', category, ' ', sizes) LIKE ?)";
            $params[] = "%$keyword%";
        }

        $where[] = "(" . implode(" AND ", $search_conditions) . ")";
    }
}
$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Pagination
try {
    // Use unified enhanced search logic for all cases
    $total_sql = "SELECT COUNT(*) as total FROM inventory $where_clause";
    $total_stmt = $conn->prepare($total_sql);
    $total_stmt->execute($params);
    $total_row = $total_stmt->fetch(PDO::FETCH_ASSOC);
    $total_items = $total_row['total'];
    
    $total_pages = ($total_items > 0) ? ceil($total_items / $limit) : 1;

    // Handle sorting
    $order_by = "ORDER BY created_at DESC";
    if ($sort === 'asc') {
        $order_by = "ORDER BY actual_quantity ASC, created_at DESC";
    } elseif ($sort === 'desc') {
        $order_by = "ORDER BY actual_quantity DESC, created_at DESC";
    }

    // Use unified enhanced search logic for main query
    $sql = "SELECT * FROM inventory $where_clause $order_by LIMIT $limit OFFSET $offset";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error in fetch_inventory: " . $e->getMessage());
    $result = [];
    $total_pages = ($total_items > 0) ? ceil($total_items / $limit) : 1;
}
// Build tbody
ob_start();
if (!empty($result)) {
    foreach ($result as $row) {
        $statusClass = '';
        if ($row['actual_quantity'] <= 0) {
            $status = 'Out of Stock';
            $statusClass = 'status-out-of-stock';
        } else if ($row['actual_quantity'] <= 10) {
            $status = 'Low Stock';
            $statusClass = 'status-low-stock';
        } else {
            $status = 'In Stock';
            $statusClass = 'status-in-stock';
        }
        echo "<tr data-item-code='" . htmlspecialchars($row['item_code']) . "' data-created-at='" . htmlspecialchars($row['created_at']) . "' data-category='" . strtolower(htmlspecialchars($row['category'])) . "' onclick='selectRow(this, \"" . htmlspecialchars($row['item_code']) . "\", " . $row['price'] . ")'>";
        echo "<td>" . htmlspecialchars($row['item_code']) . "</td>";
        echo "<td>" . htmlspecialchars($row['item_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['category']) . "</td>";
        echo "<td>" . (isset($row['actual_quantity']) ? $row['actual_quantity'] : '0') . "</td>";
        echo "<td>" . htmlspecialchars($row['sizes']) . "</td>";
        echo "<td>₱" . number_format($row['price'], 2) . "</td>";
        echo "<td class='" . $statusClass . "'>" . $status . "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr class='empty-row'><td colspan='7'>No items found.</td></tr>";
}
$tbody = ob_get_clean();
// Build pagination
ob_start();
if ($total_items > 0 && $total_pages > 1) {
    echo '<div class="pagination">';
    if ($page > 1) {
        echo '<a href="?page=' . ($page-1) . '" class="ajax-page-link">&laquo;</a>';
    }
    // Always show first page
    if ($page == 1) {
        echo '<a href="?page=1" class="ajax-page-link active">1</a>';
    } else {
        echo '<a href="?page=1" class="ajax-page-link">1</a>';
    }
    // Show ellipsis if needed before the window
    if ($page > 4) {
        echo '<span class="pagination-ellipsis">...</span>';
    }
    // Determine window of pages to show around current page
    $window = 1; // Number of pages before/after current
    $start = max(2, $page - $window);
    $end = min($total_pages - 1, $page + $window);
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $page) {
            echo '<a href="?page=' . $i . '" class="ajax-page-link active">' . $i . '</a>';
        } else {
            echo '<a href="?page=' . $i . '" class="ajax-page-link">' . $i . '</a>';
        }
    }

    if ($page < $total_pages - 3) {
        echo '<span class="pagination-ellipsis">...</span>';
    }

    if ($total_pages > 1) {
        if ($page == $total_pages) {
            echo '<a href="?page=' . $total_pages . '" class="ajax-page-link active">' . $total_pages . '</a>';
        } else {
            echo '<a href="?page=' . $total_pages . '" class="ajax-page-link">' . $total_pages . '</a>';
        }
    }
    if ($page < $total_pages) {
        echo '<a href="?page=' . ($page+1) . '" class="ajax-page-link">&raquo;</a>';
    }
    echo '</div>';
}
$pagination = ob_get_clean();
$response = [
    'tbody' => $tbody,
    'pagination' => $pagination,
    'total_items' => $total_items,
    'total_pages' => $total_pages,
    'page' => $page,
    'limit' => $limit
];

echo json_encode($response);

// Flush output buffer for faster response
if (ob_get_level()) {
    ob_end_flush();
}
flush(); 