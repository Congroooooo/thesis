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
    $where_conditions[] = "(action_type LIKE ? OR description LIKE ?)";
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

$sql = "SELECT timestamp, action_type, description
        FROM activities $where_clause
        ORDER BY timestamp DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set header
$headers = ['Date/Time', 'Action Type', 'Description'];
$sheet->fromArray($headers, NULL, 'A1');

// Make header row bold
$sheet->getStyle('A1:C1')->getFont()->setBold(true);

$rowNum = 2;
foreach ($result as $row) {
    $sheet->fromArray([
        $row['timestamp'],
        $row['action_type'],
        $row['description']
    ], NULL, 'A' . $rowNum);
    $rowNum++;
}

// Center-align Date/Time column (A)
$lastDataRow = $rowNum - 1;
$sheet->getStyle('A2:A' . $lastDataRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

// Auto-size columns
foreach (range('A', $sheet->getHighestColumn()) as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="audit_report_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit; 