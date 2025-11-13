<?php
session_start();
require __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Include the main connection file
require_once __DIR__ . '/../../Includes/connection.php';
require_once __DIR__ . '/config_functions.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$startDate = isset($_GET['startDate']) ? trim($_GET['startDate']) : '';
$endDate = isset($_GET['endDate']) ? trim($_GET['endDate']) : '';

$where = [];
$params = [];

if ($search) {
    $where[] = "(action_type LIKE ? OR description LIKE ?)";
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

$sql = "SELECT timestamp, action_type, description
        FROM activities $where_clause
        ORDER BY timestamp DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);

// Get total count for logging
$totalRecords = $stmt->rowCount();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set header
$headers = ['Date/Time', 'Action Type', 'Description'];
$sheet->fromArray($headers, NULL, 'A1');

// Make header row bold
$sheet->getStyle('A1:C1')->getFont()->setBold(true);

$rowNum = 2;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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

// Log the export activity
$user_id = $_SESSION['user_id'] ?? null;
$filterDescription = '';
$filters = [];

if ($search) $filters[] = "Search: '$search'";
if ($startDate) $filters[] = "Start Date: '$startDate'";
if ($endDate) $filters[] = "End Date: '$endDate'";

if (!empty($filters)) {
    $filterDescription = ' with filters: ' . implode(', ', $filters);
}

logActivity($conn, 'Audit Report Exported', "Audit trail report exported to Excel with $totalRecords records" . $filterDescription, $user_id);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="audit_report_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit; 