<?php
ini_set('zlib.output_compression', 0);
if (ob_get_level()) ob_end_clean();

require __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../Includes/connection.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Use the PDO connection from connection.php

include_once __DIR__ . '/config_functions.php';
$lowStockThreshold = getLowStockThreshold($conn);

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$size = isset($_GET['size']) ? trim($_GET['size']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$startDate = isset($_GET['startDate']) ? trim($_GET['startDate']) : '';
$endDate = isset($_GET['endDate']) ? trim($_GET['endDate']) : '';

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

$sql = "SELECT item_code, item_name, category, beginning_quantity, new_delivery, actual_quantity, damage, sold_quantity, IFNULL(date_delivered, created_at) AS display_date FROM inventory $where_clause ORDER BY display_date DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$headers = ['Item Code', 'Item Name', 'Category', 'Beginning Quantity', 'New Delivery', 'Actual Quantity', 'Damage', 'Sold Quantity', 'Status', 'Date Delivered'];
$sheet->fromArray($headers, NULL, 'A1');

$sheet->getStyle('A1:J1')->getFont()->setBold(true);

$rowNum = 2;
foreach ($result as $row) {
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
        $row['beginning_quantity'],
        $row['new_delivery'],
        $row['actual_quantity'],
        $row['damage'],
        $row['sold_quantity'],
        $status,
        $row['display_date']
    ], NULL, 'A' . $rowNum);

    $statusCell = 'I' . $rowNum;
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
$sheet->getStyle('D2:H' . $lastDataRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

foreach (range('A', $sheet->getHighestColumn()) as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="inventory_report_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit; 