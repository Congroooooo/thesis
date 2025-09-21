<?php
require __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../Includes/connection.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Use the PDO connection from connection.php

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$startDate = isset($_GET['startDate']) ? trim($_GET['startDate']) : '';
$endDate = isset($_GET['endDate']) ? trim($_GET['endDate']) : '';

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

$sql = "SELECT s.transaction_number, s.item_code, i.item_name, s.size, s.quantity, s.price_per_item, s.total_amount, s.sale_date
        FROM sales s
        LEFT JOIN inventory i ON s.item_code = i.item_code
        $where_clause
        ORDER BY s.sale_date DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set header
$headers = ['Order Number', 'Item Code', 'Item Name', 'Size', 'Quantity', 'Price Per Item', 'Total Amount', 'Sale Date'];
$sheet->fromArray($headers, NULL, 'A1');

// Make header row bold
$sheet->getStyle('A1:H1')->getFont()->setBold(true);

$rowNum = 2;
foreach ($result as $row) {
    $sheet->fromArray([
        $row['transaction_number'],
        $row['item_code'],
        $row['item_name'],
        $row['size'],
        $row['quantity'],
        $row['price_per_item'],
        $row['total_amount'],
        $row['sale_date']
    ], NULL, 'A' . $rowNum);
    $rowNum++;
}

// Center-align Quantity (E), Price Per Item (F), and Total Amount (G)
$lastDataRow = $rowNum - 1;
$sheet->getStyle('E2:E' . $lastDataRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('F2:F' . $lastDataRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('G2:G' . $lastDataRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

// Auto-size columns
foreach (range('A', $sheet->getHighestColumn()) as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="sales_report_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit; 