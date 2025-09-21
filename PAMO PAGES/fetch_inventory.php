<?php

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Pages/login.php?redirect=../PAMO PAGES/fetch_inventory.php");
    exit();
}
$role = strtoupper($_SESSION['role_category'] ?? '');
$programAbbr = strtoupper($_SESSION['program_abbreviation'] ?? '');
if (!($role === 'EMPLOYEE' && $programAbbr === 'PAMO')) {
    header("Location: ../Pages/home.php");
    exit();
}

$conn = mysqli_connect("localhost", "root", "", "proware");
if (!$conn) {
    http_response_code(500);
    echo json_encode([
        'tbody' => "<tr><td colspan='7'>Database connection failed.</td></tr>",
        'pagination' => ''
    ]);
    exit;
}
$category = isset($_GET['category']) ? mysqli_real_escape_string($conn, $_GET['category']) : '';
$size = isset($_GET['size']) ? mysqli_real_escape_string($conn, $_GET['size']) : '';
$status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
// Normalize search: remove all whitespace
$normalized_search = preg_replace('/\s+/', '', $search);
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$where = [];
if ($category) $where[] = "category = '$category'";
if ($size) $where[] = "sizes = '$size'";
if ($status) {
    if ($status == 'In Stock') $where[] = "actual_quantity > 10";
    else if ($status == 'Low Stock') $where[] = "actual_quantity > 0 AND actual_quantity <= $lowStockThreshold";
    else if ($status == 'Out of Stock') $where[] = "actual_quantity <= 0";
}
if ($normalized_search) {
    $where[] = "(" .
        "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(item_name, ' ', ''), '\t', ''), '\n', ''), '\r', ''), '\f', ''), '\v', ''), '\u00A0', ''), '\u200B', ''), '\u202F', ''), '\u3000', '') LIKE '%$normalized_search%' OR " .
        "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(item_code, ' ', ''), '\t', ''), '\n', ''), '\r', ''), '\f', ''), '\v', ''), '\u00A0', ''), '\u200B', ''), '\u202F', ''), '\u3000', '') LIKE '%$normalized_search%' OR " .
        "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(category, ' ', ''), '\t', ''), '\n', ''), '\r', ''), '\f', ''), '\v', ''), '\u00A0', ''), '\u200B', ''), '\u202F', ''), '\u3000', '') LIKE '%$normalized_search%')";
}
$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
// Pagination
$total_sql = "SELECT COUNT(*) as total FROM inventory $where_clause";
$total_result = mysqli_query($conn, $total_sql);
$total_row = mysqli_fetch_assoc($total_result);
$total_items = $total_row['total'];
$total_pages = ($total_items > 0) ? ceil($total_items / $limit) : 1;
$sql = "SELECT * FROM inventory $where_clause ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $sql);
// Build tbody
ob_start();
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
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
        echo "<tr data-item-code='" . $row['item_code'] . "' data-created-at='" . $row['created_at'] . "' data-category='" . strtolower($row['category']) . "' onclick='selectRow(this, \"" . $row['item_code'] . "\", " . $row['price'] . ")'>";
        echo "<td>" . $row['item_code'] . "</td>";
        echo "<td>" . $row['item_name'] . "</td>";
        echo "<td>" . $row['category'] . "</td>";
        echo "<td>" . (isset($row['actual_quantity']) ? $row['actual_quantity'] : '0') . "</td>";
        echo "<td>" . $row['sizes'] . "</td>";
        echo "<td>₱" . number_format($row['price'], 2) . "</td>";
        echo "<td class='" . $statusClass . "'>" . $status . "</td>";
        echo "</tr>";
    }
    if (mysqli_num_rows($result) === 0) {
        echo "<tr class='empty-row'><td colspan='7'>No items found.</td></tr>";
    }
} else {
    echo "<tr><td colspan='7'>Query failed.</td></tr>";
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
    // Show ellipsis if needed after the window
    if ($page < $total_pages - 3) {
        echo '<span class="pagination-ellipsis">...</span>';
    }
    // Always show last page (if more than 1 page)
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
mysqli_close($conn);
header('Content-Type: application/json');
echo json_encode([
    'tbody' => $tbody,
    'pagination' => $pagination,
    'total_items' => $total_items,
    'total_pages' => $total_pages,
    'page' => $page,
    'limit' => $limit
]); 