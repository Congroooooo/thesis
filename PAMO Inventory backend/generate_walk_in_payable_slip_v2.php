<?php
session_start();
require_once '../Includes/connection.php';
require_once '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

header('Content-Type: application/json');
date_default_timezone_set('Asia/Manila');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

try {
    // Get form data
    $roleCategory = trim($_POST['roleCategory'] ?? '');
    $customerName = trim($_POST['customerName'] ?? '');
    $customerIdNumber = trim($_POST['customerIdNumber'] ?? '');
    $items = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];
    $totalAmount = floatval($_POST['totalAmount'] ?? 0);
    
    // Validation
    if (empty($roleCategory) || empty($customerName) || empty($customerIdNumber)) {
        throw new Exception('All customer fields are required');
    }
    
    if (empty($items) || !is_array($items)) {
        throw new Exception('At least one item is required');
    }
    
    if ($totalAmount <= 0) {
        throw new Exception('Total amount must be greater than zero');
    }
    
    // ============================================
    // GENERATE TRANSACTION NUMBER
    // ============================================
    $prefix = 'SI';
    $date_part = date('md');
    $like_pattern = $prefix . '-' . $date_part . '-%';
    
    // Get the max sequence number from orders and sales tables (unified system)
    $sql = "
        SELECT MAX(seq) AS max_seq FROM (
            SELECT CAST(SUBSTRING(order_number, 10) AS UNSIGNED) AS seq
            FROM orders
            WHERE order_number LIKE ?
            UNION ALL
            SELECT CAST(SUBSTRING(transaction_number, 10) AS UNSIGNED) AS seq
            FROM sales
            WHERE transaction_number LIKE ?
        ) AS all_orders
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$like_pattern, $like_pattern]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $last_seq = $row && $row['max_seq'] ? (int)$row['max_seq'] : 0;
    $new_seq = $last_seq + 1;
    $transactionNumber = sprintf('%s-%s-%06d', $prefix, $date_part, $new_seq);
    
    // ============================================
    // VALIDATE STOCK
    // ============================================
    foreach ($items as $item) {
        $stmt = $conn->prepare("SELECT actual_quantity, item_name FROM inventory WHERE item_code = ?");
        $stmt->execute([$item['item_code']]);
        $inventoryItem = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$inventoryItem) {
            throw new Exception("Item not found: " . $item['item_code']);
        }
        
        if ($inventoryItem['actual_quantity'] < $item['quantity']) {
            throw new Exception("Insufficient stock for " . $inventoryItem['item_name'] . ". Available: " . $inventoryItem['actual_quantity']);
        }
    }
    
    // ============================================
    // STORE IN DATABASE (orders table - unified system)
    // ============================================
    $conn->beginTransaction();
    
    try {
        // Get user_id from account table based on customer name and ID
        $userStmt = $conn->prepare("
            SELECT id FROM account 
            WHERE CONCAT(first_name, ' ', last_name) = ? 
            AND id_number = ? 
            AND role_category = ?
            LIMIT 1
        ");
        $userStmt->execute([$customerName, $customerIdNumber, $roleCategory]);
        $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        $userId = $userRow ? $userRow['id'] : null;
        
        if (!$userId) {
            throw new Exception("Customer not found in system. Please verify customer details.");
        }
        
        // Insert into orders table with order_type = 'walk-in' and status = 'approved'
        $stmt = $conn->prepare("
            INSERT INTO orders 
            (order_number, order_type, user_id, items, phone, total_amount, status, approved_by, created_at) 
            VALUES (?, 'walk-in', ?, ?, '', ?, 'approved', ?, NOW())
        ");
        
        $approvedBy = $_SESSION['full_name'] ?? 'PAMO Staff';
        
        $stmt->execute([
            $transactionNumber,
            $userId,
            json_encode($items),
            $totalAmount,
            $approvedBy
        ]);
        
        $orderId = $conn->lastInsertId();
        
        // Log activity
        $stmt = $conn->prepare("
            INSERT INTO activities (user_id, action_type, description, timestamp) 
            VALUES (?, 'Generate Walk-in Slip', ?, NOW())
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            "Generated Walk-in Slip: {$transactionNumber} for {$customerName} (₱" . number_format($totalAmount, 2) . ")"
        ]);
        
        $conn->commit();
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw new Exception("Database error: " . $e->getMessage());
    }
    
    // ============================================
    // GENERATE E-SLIP HTML (matching online order e-slip design)
    // ============================================
    
    $currentDate = date('F d, Y');
    
    // Get the actual PAMO staff name from session or database
    $preparedBy = 'PAMO Staff'; // Default fallback
    if (isset($_SESSION['user_id'])) {
        try {
            $userStmt = $conn->prepare('SELECT first_name, last_name FROM account WHERE id = ?');
            $userStmt->execute([$_SESSION['user_id']]);
            $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
            if ($userData) {
                $preparedBy = trim($userData['first_name'] . ' ' . $userData['last_name']);
            }
        } catch (Exception $e) {
            // Keep default fallback if query fails
        }
    }
    
    $isEmployee = ($roleCategory === 'EMPLOYEE');
    
    // Determine labels based on user type (role category)
    $userTypeLabel = $isEmployee ? 'Employee Name:' : 'Student Name:';
    $userNumberLabel = $isEmployee ? 'Employee No.:' : 'Student No.:';
    
    // Determine copy label based on role category
    $copyLabel = 'CUSTOMER COPY'; // Default
    if ($roleCategory === 'EMPLOYEE') {
        $copyLabel = 'EMPLOYEE COPY';
    } elseif ($roleCategory === 'COLLEGE STUDENT') {
        $copyLabel = 'STUDENT COPY';
    } elseif ($roleCategory === 'SHS') {
        $copyLabel = 'STUDENT COPY';
    }
    
    // Get logo as base64
    $logo_path = realpath(__DIR__ . '/../Images/STI-LOGO.png');
    $logo_data = $logo_path && file_exists($logo_path) ? base64_encode(file_get_contents($logo_path)) : '';
    $logo_src = $logo_data ? 'data:image/png;base64,' . $logo_data : '';
    
    // Build items rows matching e-slip table structure
    $dataRows = '';
    $rowspan = count($items);
    foreach ($items as $i => $item) {
        // Full item description: Item Name - Size
        $itemDescription = htmlspecialchars($item['item_name']);
        if (!empty($item['size'])) {
            $itemDescription .= ' - ' . htmlspecialchars($item['size']);
        }
        
        $row = '<tr>';
        $row .= '<td>' . $itemDescription . '</td>';
        $row .= '<td>' . htmlspecialchars($item['category'] ?? '') . '</td>';
        $row .= '<td style="text-align:center;">' . htmlspecialchars($item['quantity']) . '</td>';
        $row .= '<td style="text-align:right;">' . number_format($item['price'], 2) . '</td>';
        $row .= '<td style="text-align:right;">' . number_format($item['subtotal'], 2) . '</td>';
        
        // Add signature column only on first row
        if ($i === 0) {
            $row .= '<td class="signature-col" rowspan="' . $rowspan . '">
                <table class="signature-table">
                    <tr><td class="sig-label">Prepared by:</td></tr>
                    <tr><td class="sig-box">' . htmlspecialchars($preparedBy) . '</td></tr>
                    <tr><td class="sig-label">OR Issued by:</td></tr>
                    <tr><td class="sig-box"><br><span style="font-weight:bold;">Cashier</span></td></tr>
                    <tr><td class="sig-label">Released by & date:</td></tr>
                    <tr><td class="sig-box"></td></tr>
                    <tr><td class="sig-label">RECEIVED BY:</td></tr>
                    <tr><td class="sig-box" style="height:40px;vertical-align:bottom;">
                        <div style="height:24px;"></div>
                        <div class="sig-name" style="font-weight:bold;text-decoration:underline;text-align:center;">' . htmlspecialchars($customerName) . '</div>
                    </td></tr>
                </table>
            </td>';
        }
        $row .= '</tr>';
        $dataRows .= $row;
    }
    
    $footerRow = '<tr>
        <td colspan="5" class="receipt-footer-cell">
          <b>ALL ITEMS ARE RECEIVED IN GOOD CONDITION</b><br>
          <span>(Exchange is allowed only within 3 days from the invoice date. Strictly no refund)</span>
        </td>
        <td class="receipt-footer-total">
          TOTAL AMOUNT: <span>' . number_format($totalAmount, 2) . '</span>
        </td>
      </tr>';
    
    // CSS matching e-slip design
    $css = '<style>
@page { size: A4; margin: 0; }
body { font-family: Arial, sans-serif; font-size: 12px; margin: 0; padding: 0; }
@media print {
    body { margin: 0 !important; padding: 0 !important; }
    .no-print { display: none !important; }
}
.receipt-a4 { width: 210mm; min-height: 148.5mm; padding: 10px; margin: 0 auto; background: #fff; font-family: Arial, sans-serif; box-sizing: border-box; }
.receipt-header-flex { width: 100%; display: flex; flex-direction: row; align-items: flex-start; justify-content: space-between; margin-bottom: 6px; margin-top: 2px; min-height: 60px; }
.receipt-header-logo img { height: 60px; width: auto; display: block; }
.receipt-header-logo { flex: 0 0 80px; display: flex; align-items: center; justify-content: flex-start; }
.receipt-header-center { flex: 1 1 auto; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; min-width: 0; }
.sti-lucena { font-size: 1.35em; font-weight: bold; letter-spacing: 1px; margin-bottom: 2px; }
.sales-issuance-slip { font-size: 1.1em; font-weight: bold; letter-spacing: 0.5px; margin-top: 2px; }
.receipt-header-copy { flex: 0 0 100px; text-align: right; font-size: 1em; font-weight: bold; margin-top: 2px; margin-right: 2px; }
.receipt-header-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 1em; }
.receipt-header-table td { padding: 2px 6px 2px 0; vertical-align: bottom; border-bottom: 1px solid #222; }
.receipt-header-table tr:last-child td { border-bottom: none; }
.receipt-main-table { width: 100%; border-collapse: collapse; font-size: 1em; margin-bottom: 0px; table-layout: fixed; }
.receipt-main-table th, .receipt-main-table td { border: 1px solid #222; padding: 6px 8px; vertical-align: top; word-break: break-word; }
.receipt-main-table th { background: #f2f2f2; text-align: center; font-weight: bold; }
.receipt-main-table td { background: #fff; }
.signature-col { background: #fff; vertical-align: top; text-align: left; min-width: 180px; max-width: 220px; padding: 0 !important; }
.signature-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.signature-table .sig-label { font-weight: bold; font-size: 0.98em; border: 1px solid #222; border-bottom: none; padding: 4px 6px 2px 6px; background: #f8f8f8; }
.signature-table .sig-box { border: 1px solid #222; border-top: none; min-height: 28px; padding: 2px 6px; font-size: 0.97em; background: #fff; }
.signature-table .sig-name { font-size: 1em; margin-top: 2px; }
.receipt-footer-cell { text-align: left; font-size: 0.98em; padding-top: 10px; }
.receipt-footer-total { text-align: right; font-size: 1.05em; font-weight: bold; padding-top: 10px; }
</style>';

    $html = '<html><head><meta charset="UTF-8">' . $css . '</head><body>';
    $html .= '
      <div class="receipt-a4">
        <div class="receipt-header-flex">
          <div class="receipt-header-logo"><img src="' . $logo_src . '" alt="STI Logo" /></div>
          <div class="receipt-header-center">
            <div class="sti-lucena">STI LUCENA</div>
            <div class="sales-issuance-slip">SALES ISSUANCE SLIP</div>
          </div>
          <div class="receipt-header-copy">' . htmlspecialchars($copyLabel) . '</div>
        </div>
        <div class="receipt-section">
          <table class="receipt-header-table">
            <tr>
              <td><b>' . $userTypeLabel . '</b></td>
              <td>' . htmlspecialchars($customerName) . '</td>
              <td><b>' . $userNumberLabel . '</b></td>
              <td>' . htmlspecialchars($customerIdNumber) . '</td>
              <td><b>DATE:</b></td>
              <td>' . $currentDate . '</td>
            </tr>
            <tr>
              <td><b>Issuance Slip No.:</b></td>
              <td>' . htmlspecialchars($transactionNumber) . '</td>
              <td><b>Invoice No.:</b></td>
              <td></td>
              <td colspan="2"></td>
            </tr>
          </table>
          <table class="receipt-main-table">
            <thead>
              <tr>
                <th>Item Description</th>
                <th>Item Type</th>
                <th>Qty</th>
                <th>SRP</th>
                <th>Amount</th>
                <th>Prepared by:</th>
              </tr>
            </thead>
            <tbody>' . $dataRows . $footerRow . '</tbody>
          </table>
        </div>
      </div>';
    $html .= '</body></html>';
    
    // ============================================
    // RETURN SUCCESS WITH HTML FOR MODAL DISPLAY
    // ============================================
    echo json_encode([
        'success' => true,
        'message' => 'Walk-in order created and approved successfully',
        'transaction_number' => $transactionNumber,
        'order_id' => $orderId,
        'slip_html' => $html,
        'customer_name' => $customerName,
        'order_type' => 'walk-in',
        'status' => 'approved'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
