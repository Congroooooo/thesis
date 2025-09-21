<?php
require_once '../Includes/connection.php';
include_once 'includes/config_functions.php';

$lowStockThreshold = getLowStockThreshold($conn);

$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$size = isset($_GET['size']) ? trim($_GET['size']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$normalized_search = preg_replace('/\s+/', '', $search);

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

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
    }
    else if ($status == 'Low Stock') {
        $where_conditions[] = "actual_quantity > 0 AND actual_quantity <= ?";
        $params[] = $lowStockThreshold;
    }
    else if ($status == 'Out of Stock') {
        $where_conditions[] = "actual_quantity <= 0";
    }
}
if ($normalized_search) {
    $search_condition = "(" .
        "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(item_name, ' ', ''), '\t', ''), '\n', ''), '\r', ''), '\f', ''), '\v', ''), '\u00A0', ''), '\u200B', ''), '\u202F', ''), '\u3000', '') LIKE ? OR " .
        "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(item_code, ' ', ''), '\t', ''), '\n', ''), '\r', ''), '\f', ''), '\v', ''), '\u00A0', ''), '\u200B', ''), '\u202F', ''), '\u3000', '') LIKE ? OR " .
        "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(category, ' ', ''), '\t', ''), '\n', ''), '\r', ''), '\f', ''), '\v', ''), '\u00A0', ''), '\u200B', ''), '\u202F', ''), '\u3000', '') LIKE ?)";
    $where_conditions[] = $search_condition;
    $params[] = "%$normalized_search%";
    $params[] = "%$normalized_search%";
    $params[] = "%$normalized_search%";
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    $total_sql = "SELECT COUNT(*) as total FROM inventory $where_clause";
    $total_stmt = $conn->prepare($total_sql);
    $total_stmt->execute($params);
    $total_row = $total_stmt->fetch(PDO::FETCH_ASSOC);
    $total_items = $total_row['total'];
    $total_pages = ($total_items > 0) ? ceil($total_items / $limit) : 1;

    $sql = "SELECT * FROM inventory $where_clause ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_start();
    if ($result) {
        foreach ($result as $row) {
            $statusClass = '';
            if ($row['actual_quantity'] <= 0) {
                $status = 'Out of Stock';
                $statusClass = 'status-out-of-stock';
            } else if ($row['actual_quantity'] <= $lowStockThreshold) {
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
        if (count($result) === 0) {
            echo "<tr class='empty-row'><td colspan='7'>No items found.</td></tr>";
        }
    } else {
        echo "<tr class='empty-row'><td colspan='7'>No items found.</td></tr>";
    }

} catch (PDOException $e) {
    echo "<tr><td colspan='7'>Database error: " . $e->getMessage() . "</td></tr>";
}
$tbody = ob_get_clean();
ob_start();
if ($total_items > 0 && $total_pages > 1) {
    echo '<div class="pagination">';
    if ($page > 1) {
        echo '<a href="?page=' . ($page-1) . '" class="ajax-page-link">&laquo;</a>';
    }
    if ($page == 1) {
        echo '<a href="?page=1" class="ajax-page-link active">1</a>';
    } else {
        echo '<a href="?page=1" class="ajax-page-link">1</a>';
    }
    if ($page > 4) {
        echo '<span class="pagination-ellipsis">...</span>';
    }
    $window = 1;
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

header('Content-Type: application/json');
echo json_encode([
    'tbody' => $tbody,
    'pagination' => $pagination,
    'total_items' => $total_items,
    'total_pages' => $total_pages,
    'page' => $page,
    'limit' => $limit
]); 