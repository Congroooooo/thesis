<?php
require __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Include the main connection file
require_once __DIR__ . '/../../Includes/connection.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$startDate = isset($_GET['startDate']) ? trim($_GET['startDate']) : '';
$endDate = isset($_GET['endDate']) ? trim($_GET['endDate']) : '';

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

$sql = "SELECT s.transaction_number, s.item_code, i.item_name, s.size, s.quantity, s.price_per_item, s.total_amount, s.sale_date, 
               s.transaction_type, s.linked_transaction_id, s.exchange_reference
        FROM sales s
        LEFT JOIN inventory i ON s.item_code = i.item_code
        $where_clause
        ORDER BY s.transaction_number DESC, 
                 CASE WHEN s.transaction_type = 'Original' OR s.transaction_type IS NULL THEN 0
                      WHEN s.transaction_type = 'Exchange' THEN 1
                      WHEN s.transaction_type = 'Return' THEN 2
                      WHEN s.transaction_type = 'Voided' THEN 3
                      WHEN s.transaction_type = 'Cancelled' THEN 4
                      WHEN s.transaction_type = 'Rejected' THEN 5
                      ELSE 6 END ASC,
                 s.id ASC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set header
$headers = ['Order Number', 'Item Code', 'Item Name', 'Size', 'Quantity', 'Price Per Item', 'Total Amount', 'Sale Date', 'Transaction Type', 'Linked Txn ID'];
$sheet->fromArray($headers, NULL, 'A1');

// Make header row bold
$sheet->getStyle('A1:J1')->getFont()->setBold(true);

$rowNum = 2;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $sheet->fromArray([
        $row['transaction_number'],
        $row['item_code'],
        $row['item_name'],
        $row['size'],
        $row['quantity'],
        $row['price_per_item'],
        $row['total_amount'],
        $row['sale_date'],
        $row['transaction_type'] ?: 'Original',
        $row['linked_transaction_id'] ?: '-'
    ], NULL, 'A' . $rowNum);
    $rowNum++;
}

// Center-align Quantity (E), Price Per Item (F), Total Amount (G), Transaction Type (I), and Linked ID (J)
$lastDataRow = $rowNum - 1;
$sheet->getStyle('E2:E' . $lastDataRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('F2:F' . $lastDataRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('G2:G' . $lastDataRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('I2:I' . $lastDataRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('J2:J' . $lastDataRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

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