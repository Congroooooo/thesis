<?php
session_start();
ini_set('zlib.output_compression', 0);
if (ob_get_level()) ob_end_clean();

require __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Include the main connection file
require_once __DIR__ . '/../../Includes/connection.php';

include_once __DIR__ . '/config_functions.php';
$lowStockThreshold = getLowStockThreshold($conn);

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$size = isset($_GET['size']) ? trim($_GET['size']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$stockStatus = isset($_GET['stockStatus']) ? trim($_GET['stockStatus']) : '';
$startDate = isset($_GET['startDate']) ? trim($_GET['startDate']) : '';
$endDate = isset($_GET['endDate']) ? trim($_GET['endDate']) : '';

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
    if ($status == 'In Stock') $where[] = "actual_quantity > $lowStockThreshold";
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
$sql = "SELECT item_code, item_name, category, new_delivery, actual_quantity, IFNULL(date_delivered, created_at) AS display_date FROM inventory $where_clause ORDER BY display_date DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);

// Get total count for logging
$totalItems = $stmt->rowCount();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$headers = ['Item Code', 'Item Name', 'Category', 'New Delivery', 'Actual Quantity', 'Status'];
$sheet->fromArray($headers, NULL, 'A1');

$sheet->getStyle('A1:F1')->getFont()->setBold(true);

$rowNum = 2;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($row['actual_quantity'] <= 0) {
        $status = 'Out of Stock';
    } else if ($row['actual_quantity'] <= $lowStockThreshold) {
        $status = 'Low Stock';
    } else {
        $status = 'In Stock';
    }
    $sheet->fromArray([
        $row['item_code'],
        $row['item_name'],
        $row['category'],
        $row['new_delivery'],
        $row['actual_quantity'],
        $status
    ], NULL, 'A' . $rowNum);

    $statusCell = 'F' . $rowNum;
    $statusLower = strtolower($status);
    if ($statusLower === 'in stock') {
        $sheet->getStyle($statusCell)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('92D050');
    } elseif ($statusLower === 'low stock') {
        $sheet->getStyle($statusCell)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FFC000');
    } elseif ($statusLower === 'out of stock') {
        $sheet->getStyle($statusCell)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FF0000');
    }
    $rowNum++;
}

$lastDataRow = $rowNum - 1;
$sheet->getStyle('D2:E' . $lastDataRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

foreach (range('A', $sheet->getHighestColumn()) as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Log the export activity
$user_id = $_SESSION['user_id'] ?? null;
$filterDescription = '';
$filters = [];

if ($search) $filters[] = "Search: '$search'";
if ($category) $filters[] = "Category: '$category'";
if ($size) $filters[] = "Size: '$size'";
if ($status) $filters[] = "Status: '$status'";
if ($stockStatus) $filters[] = "Stock Status: '$stockStatus'";
if ($startDate) $filters[] = "Start Date: '$startDate'";
if ($endDate) $filters[] = "End Date: '$endDate'";

if (!empty($filters)) {
    $filterDescription = ' with filters: ' . implode(', ', $filters);
}

logActivity($conn, 'Inventory Exported', "Inventory report exported to Excel with $totalItems items" . $filterDescription, $user_id);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="inventory_report_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit; 