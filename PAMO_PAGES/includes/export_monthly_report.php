<?php
session_start();
ini_set('zlib.output_compression', 0);
if (ob_get_level()) ob_end_clean();

require __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

require_once __DIR__ . '/../../Includes/connection.php';
require_once __DIR__ . '/../../Includes/MonthlyInventoryManager.php';
require_once __DIR__ . '/config_functions.php';

$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$selectedMonth = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$monthName = date('F Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear));

$stmt = $conn->prepare("SELECT * FROM monthly_inventory_periods WHERE year = ? AND month = ?");
$stmt->execute([$selectedYear, $selectedMonth]);
$periodInfo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$periodInfo) {
    die("No data available for the selected period.");
}

$periodId = $periodInfo['id'];

$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_items,
        SUM(beginning_quantity) as total_beginning,
        SUM(new_delivery_total) as total_deliveries,
        SUM(sales_total) as total_sales,
        SUM(removals_total) as total_removals,
        SUM(ending_quantity) as total_ending
    FROM monthly_inventory_snapshots 
    WHERE period_id = ?
");
$stmt->execute([$periodId]);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Get detailed inventory data
$stmt = $conn->prepare("
    SELECT 
        mis.item_code,
        i.item_name,
        i.category,
        mis.beginning_quantity,
        mis.new_delivery_total,
        mis.sales_total,
        mis.removals_total,
        mis.ending_quantity,
        i.price,
        (mis.ending_quantity * i.price) as ending_value
    FROM monthly_inventory_snapshots mis
    JOIN inventory i ON mis.item_code = i.item_code
    WHERE mis.period_id = ?
    ORDER BY i.category, i.item_name
");
$stmt->execute([$periodId]);
$inventoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get removal details for this period
$stmt = $conn->prepare("
    SELECT 
        ir.id,
        ir.item_code,
        i.item_name,
        ir.quantity_removed,
        ir.removal_reason,
        ir.removed_at,
        ir.pullout_order_number
    FROM inventory_removals ir
    JOIN inventory i ON ir.item_code COLLATE utf8mb4_unicode_ci = i.item_code COLLATE utf8mb4_unicode_ci
    WHERE ir.period_id = ?
    ORDER BY ir.removed_at DESC
");
$stmt->execute([$periodId]);
$removalDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create new spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Monthly Inventory');

// Set report title
$sheet->setCellValue('A1', 'Monthly Inventory Report');
$sheet->mergeCells('A1:H1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Set period information
$sheet->setCellValue('A2', 'Period: ' . $monthName);
$sheet->mergeCells('A2:H2');
$sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A3', 'Date Range: ' . date('F j, Y', strtotime($periodInfo['period_start'])) . ' - ' . date('F j, Y', strtotime($periodInfo['period_end'])));
$sheet->mergeCells('A3:H3');
$sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Add summary section
$rowNum = 5;
$sheet->setCellValue('A' . $rowNum, 'Summary Statistics');
$sheet->mergeCells('A' . $rowNum . ':B' . $rowNum);
$sheet->getStyle('A' . $rowNum)->getFont()->setBold(true);
$rowNum++;

$summaryData = [
    ['Total Items:', number_format($summary['total_items'])],
    ['Beginning Stock:', number_format($summary['total_beginning'])],
    ['Total Deliveries:', number_format($summary['total_deliveries'])],
    ['Total Sales:', number_format($summary['total_sales'])],
    ['Total Removed:', number_format($summary['total_removals'])],
    ['Ending Stock:', number_format($summary['total_ending'])]
];

foreach ($summaryData as $summaryRow) {
    $sheet->setCellValue('A' . $rowNum, $summaryRow[0]);
    $sheet->setCellValue('B' . $rowNum, $summaryRow[1]);
    $sheet->getStyle('A' . $rowNum)->getFont()->setBold(true);
    $rowNum++;
}

// Add spacing
$rowNum += 2;

// Set table headers
$headers = ['Item Code', 'Item Name', 'Category', 'Beginning Qty', 'Deliveries', 'Sales', 'Removed', 'Ending Qty'];
$sheet->fromArray($headers, NULL, 'A' . $rowNum);

// Style the header row
$headerRange = 'A' . $rowNum . ':H' . $rowNum;
$sheet->getStyle($headerRange)->getFont()->setBold(true);
$sheet->getStyle($headerRange)->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setRGB('4472C4');
$sheet->getStyle($headerRange)->getFont()->getColor()->setRGB('FFFFFF');
$sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle($headerRange)->getBorders()->getAllBorders()
    ->setBorderStyle(Border::BORDER_THIN);

$rowNum++;

// Add data rows grouped by category
$currentCategory = '';
$dataStartRow = $rowNum;

foreach ($inventoryData as $item) {
    // Add category header if category changes
    if ($currentCategory != $item['category']) {
        $currentCategory = $item['category'];
        $sheet->setCellValue('A' . $rowNum, $currentCategory);
        $sheet->mergeCells('A' . $rowNum . ':H' . $rowNum);
        $sheet->getStyle('A' . $rowNum)->getFont()->setBold(true);
        $sheet->getStyle('A' . $rowNum)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E7E6E6');
        $rowNum++;
    }
    
    // Add item data
    $sheet->fromArray([
        $item['item_code'],
        $item['item_name'],
        $item['category'], // Show the actual category value
        $item['beginning_quantity'],
        $item['new_delivery_total'],
        $item['sales_total'],
        $item['removals_total'],
        $item['ending_quantity']
    ], NULL, 'A' . $rowNum);
    
    // Format numeric columns
    $sheet->getStyle('D' . $rowNum . ':H' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // Color code removed quantity (red if > 0)
    $removedQty = $item['removals_total'];
    if ($removedQty > 0) {
        $sheet->getStyle('G' . $rowNum)->getFont()->getColor()->setRGB('d32f2f');
        $sheet->getStyle('G' . $rowNum)->getFont()->setBold(true);
    }
    
    // Color code ending quantity
    $endingQty = $item['ending_quantity'];
    if ($endingQty > 0) {
        $sheet->getStyle('H' . $rowNum)->getFont()->getColor()->setRGB('28a745');
        $sheet->getStyle('H' . $rowNum)->getFont()->setBold(true);
    } else {
        $sheet->getStyle('H' . $rowNum)->getFont()->getColor()->setRGB('dc3545');
        $sheet->getStyle('H' . $rowNum)->getFont()->setBold(true);
    }
    
    // Add borders
    $sheet->getStyle('A' . $rowNum . ':H' . $rowNum)->getBorders()->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN);
    
    $rowNum++;
}

// Add Removal Details Section if there are any removals
if (!empty($removalDetails)) {
    // Add spacing
    $rowNum += 2;
    
    // Add section title
    $sheet->setCellValue('A' . $rowNum, 'Removed Items Details');
    $sheet->mergeCells('A' . $rowNum . ':G' . $rowNum);
    $sheet->getStyle('A' . $rowNum)->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A' . $rowNum)->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB('FFEBEE');
    $sheet->getStyle('A' . $rowNum)->getFont()->getColor()->setRGB('d32f2f');
    $sheet->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $rowNum++;
    
    // Add removal table headers
    $removalHeaders = ['Item Code', 'Item Name', 'Quantity', 'Status', 'Reason', 'Date Removed', 'Pullout Order #'];
    $sheet->fromArray($removalHeaders, NULL, 'A' . $rowNum);
    
    // Style removal header row
    $removalHeaderRange = 'A' . $rowNum . ':G' . $rowNum;
    $sheet->getStyle($removalHeaderRange)->getFont()->setBold(true);
    $sheet->getStyle($removalHeaderRange)->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB('d32f2f');
    $sheet->getStyle($removalHeaderRange)->getFont()->getColor()->setRGB('FFFFFF');
    $sheet->getStyle($removalHeaderRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle($removalHeaderRange)->getBorders()->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN);
    $rowNum++;
    
    // Add removal data rows
    $totalRemovedQty = 0;
    foreach ($removalDetails as $removal) {
        $sheet->fromArray([
            $removal['item_code'],
            $removal['item_name'],
            $removal['quantity_removed'],
            'Removed',
            $removal['removal_reason'] ?? 'N/A',
            date('M j, Y g:i A', strtotime($removal['removed_at'])),
            $removal['pullout_order_number']
        ], NULL, 'A' . $rowNum);
        
        // Format quantity column (bold red)
        $sheet->getStyle('C' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C' . $rowNum)->getFont()->getColor()->setRGB('d32f2f');
        $sheet->getStyle('C' . $rowNum)->getFont()->setBold(true);
        
        // Center status column
        $sheet->getStyle('D' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('D' . $rowNum)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FFEBEE');
        $sheet->getStyle('D' . $rowNum)->getFont()->getColor()->setRGB('d32f2f');
        $sheet->getStyle('D' . $rowNum)->getFont()->setBold(true);
        
        // Center date column
        $sheet->getStyle('F' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Add borders
        $sheet->getStyle('A' . $rowNum . ':G' . $rowNum)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        
        $totalRemovedQty += $removal['quantity_removed'];
        $rowNum++;
    }
    
    // Add summary row
    $rowNum++;
    $sheet->setCellValue('A' . $rowNum, 'Total Items Removed: ' . count($removalDetails) . '  |  Total Quantity: ' . number_format($totalRemovedQty) . ' units');
    $sheet->mergeCells('A' . $rowNum . ':G' . $rowNum);
    $sheet->getStyle('A' . $rowNum)->getFont()->setBold(true);
    $sheet->getStyle('A' . $rowNum)->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB('F8F9FA');
    $sheet->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A' . $rowNum)->getBorders()->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN);
}

// Auto-size columns
foreach (range('A', 'H') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Set column widths for better appearance
$sheet->getColumnDimension('A')->setWidth(15);
$sheet->getColumnDimension('B')->setWidth(35);
$sheet->getColumnDimension('C')->setWidth(20);

// Log the export activity
$user_id = $_SESSION['user_id'] ?? null;
$totalItems = count($inventoryData);
$monthYearDisplay = date('F Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear));
logActivity($conn, 'Monthly Inventory Report Exported', "Monthly inventory report for $monthYearDisplay exported to Excel with $totalItems items", $user_id);

// Generate filename
$filename = 'Monthly_Inventory_Report_' . date('F_Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear)) . '.xlsx';

// Set headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Write to output
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
