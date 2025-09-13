<?php
require __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$conn = mysqli_connect("localhost", "root", "", "proware");

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$startDate = isset($_GET['startDate']) ? trim($_GET['startDate']) : '';
$endDate = isset($_GET['endDate']) ? trim($_GET['endDate']) : '';

$where = [];
if ($search) {
    $s = mysqli_real_escape_string($conn, $search);
    $where[] = "(action_type LIKE '%$s%' OR description LIKE '%$s%')";
}
if ($startDate) {
    $where[] = "DATE(timestamp) >= '" . mysqli_real_escape_string($conn, $startDate) . "'";
}
if ($endDate) {
    $where[] = "DATE(timestamp) <= '" . mysqli_real_escape_string($conn, $endDate) . "'";
}
$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT timestamp, action_type, description
        FROM activities $where_clause
        ORDER BY timestamp DESC";

$result = mysqli_query($conn, $sql);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set header
$headers = ['Date/Time', 'Action Type', 'Description'];
$sheet->fromArray($headers, NULL, 'A1');

// Make header row bold
$sheet->getStyle('A1:C1')->getFont()->setBold(true);

$rowNum = 2;
while ($row = mysqli_fetch_assoc($result)) {
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