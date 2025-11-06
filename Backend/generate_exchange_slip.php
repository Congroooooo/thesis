<?php
/**
 * Generate Exchange Slip
 * Creates a PDF slip for exchange transactions with price adjustments
 */

require_once '../Includes/connection.php';
require_once '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

session_start();
if (!isset($_SESSION['user_id'])) {
    die('Unauthorized');
}

$exchange_id = isset($_GET['exchange_id']) ? intval($_GET['exchange_id']) : 0;
$is_admin = isset($_GET['admin']) && $_GET['admin'] == '1';

if (!$exchange_id) die('No exchange ID');

// Get exchange details
if ($is_admin) {
    // Admin access - check if user is PAMO employee
    $role = strtoupper($_SESSION['role_category'] ?? '');
    $programAbbr = strtoupper($_SESSION['program_abbreviation'] ?? '');
    if (!($role === 'EMPLOYEE' && $programAbbr === 'PAMO')) {
        die('Unauthorized - PAMO access required');
    }
    
    // Admin can view any exchange
    $stmt = $conn->prepare('
        SELECT oe.*, o.created_at as order_date
        FROM order_exchanges oe
        JOIN orders o ON oe.order_id = o.id
        WHERE oe.id = ?
    ');
    $stmt->execute([$exchange_id]);
} else {
    // Customer access - only their own exchanges
    $stmt = $conn->prepare('
        SELECT oe.*, o.created_at as order_date
        FROM order_exchanges oe
        JOIN orders o ON oe.order_id = o.id
        WHERE oe.id = ? AND oe.user_id = ?
    ');
    $stmt->execute([$exchange_id, $_SESSION['user_id']]);
}

$exchange = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exchange || !in_array($exchange['status'], ['approved', 'completed'])) {
    die('Exchange not found or not approved/completed');
}

// Get user details
$user_stmt = $conn->prepare('SELECT first_name, last_name, email, program_or_position, id_number, role_category FROM account WHERE id = ?');
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Get exchange items
$items_stmt = $conn->prepare('SELECT * FROM order_exchange_items WHERE exchange_id = ?');
$items_stmt->execute([$exchange_id]);
$exchange_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

$studentName = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
$studentIdNumber = htmlspecialchars($user['id_number']);
$course = htmlspecialchars($user['program_or_position']);
$email = htmlspecialchars($user['email']);
$roleCategory = strtoupper(trim($user['role_category'] ?? ''));
$isEmployee = ($roleCategory === 'EMPLOYEE');
$exchangeNumber = htmlspecialchars($exchange['exchange_number']);
$orderNumber = htmlspecialchars($exchange['order_number']);
$exchangeDate = date('F d, Y', strtotime($exchange['exchange_date']));
$orderDate = date('F d, Y', strtotime($exchange['order_date']));
$totalPriceDifference = floatval($exchange['total_price_difference']);
$adjustmentType = $exchange['adjustment_type'];
$approvedBy = isset($exchange['approved_by']) ? htmlspecialchars($exchange['approved_by']) : '';

$logo_path = realpath(__DIR__ . '/../Images/STI-LOGO.png');
$logo_data = $logo_path && file_exists($logo_path) ? base64_encode(file_get_contents($logo_path)) : '';
$logo_src = $logo_data ? 'data:image/png;base64,' . $logo_data : '';

$css = '<style>
@page { size: A4; margin: 0; }
body { font-family: Arial, sans-serif; font-size: 12px; }
.slip-a4 { width: 210mm; height: 297mm; padding: 0; margin: 0 auto; background: #fff; }
.slip-half { height: 148.5mm; box-sizing: border-box; padding: 10px 10px 6px 10px; border-bottom: 2.5px dashed #333; page-break-inside: avoid; display: flex; flex-direction: column; }
.slip-header-flex { width: 100%; display: flex; flex-direction: row; align-items: flex-start; justify-content: space-between; margin-bottom: 2px; margin-top: 2px; min-height: 60px; }
.slip-header-logo img { height: 60px; width: auto; display: block; }
.slip-header-logo { flex: 0 0 80px; display: flex; align-items: center; }
.slip-header-center { flex: 1 1 auto; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.sti-lucena { font-size: 1.35em; font-weight: bold; letter-spacing: 1px; margin-bottom: 0px; }
.exchange-slip-title { font-size: 1.1em; font-weight: bold; letter-spacing: 0.5px; margin-top: 0px; color: #d32f2f; }
.slip-header-copy { flex: 0 0 100px; text-align: right; font-size: 1em; font-weight: bold; margin-top: 2px; }
.slip-header-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 0.95em; }
.slip-header-table td { padding: 2px 6px 2px 0; vertical-align: bottom; border-bottom: 1px solid #222; }
.slip-header-table tr:last-child td { border-bottom: none; }
.slip-main-table { width: 100%; border-collapse: collapse; font-size: 0.9em; margin-bottom: 4px; table-layout: fixed; }
.slip-main-table th, .slip-main-table td { border: 1px solid #222; padding: 4px 6px; vertical-align: top; }
.slip-main-table th { background: #f2f2f2; text-align: center; font-weight: bold; }
.slip-main-table .section-header { background: #e8e8e8; font-weight: bold; text-align: left; }
.slip-main-table .price-diff { font-weight: bold; }
.slip-main-table .positive { color: #d32f2f; }
.slip-main-table .negative { color: #388e3c; }
.adjustment-box { border: 2px solid #222; padding: 8px; margin: 8px 0; background: #fffef0; }
.adjustment-box .title { font-weight: bold; font-size: 1.1em; margin-bottom: 4px; }
.adjustment-box .amount { font-size: 1.3em; font-weight: bold; text-align: right; }
.adjustment-box .additional { color: #d32f2f; }
.adjustment-box .refund { color: #388e3c; }
.signature-section { display: flex; justify-content: space-between; margin-top: 10px; }
.signature-box { flex: 1; border: 1px solid #222; padding: 6px; margin: 0 4px; min-height: 60px; }
.signature-box .label { font-weight: bold; font-size: 0.9em; margin-bottom: 4px; }
.signature-box .line { border-top: 1px solid #222; margin-top: 30px; padding-top: 4px; text-align: center; font-weight: bold; }
.footer-note { font-size: 0.85em; margin-top: 8px; padding: 6px; background: #f5f5f5; border: 1px solid #ddd; }
</style>';

function renderExchangeSlip($copyLabel, $logo_src, $studentName, $studentIdNumber, $exchangeNumber, $orderNumber, $exchangeDate, $orderDate, $exchange_items, $totalPriceDifference, $adjustmentType, $approvedBy, $isEmployee = false) {
    
    // Build item rows
    $itemRows = '';
    
    // Original items section
    $itemRows .= '<tr><td colspan="5" class="section-header">ORIGINAL ITEMS (Returned)</td></tr>';
    $itemRows .= '<tr>
        <th style="width:35%">Item Description</th>
        <th style="width:15%">Size</th>
        <th style="width:15%">Qty</th>
        <th style="width:15%">Unit Price</th>
        <th style="width:20%">Subtotal</th>
    </tr>';
    
    foreach ($exchange_items as $item) {
        $itemRows .= '<tr>';
        $itemRows .= '<td>' . htmlspecialchars($item['original_item_name']) . '</td>';
        $itemRows .= '<td style="text-align:center;">' . htmlspecialchars($item['original_size']) . '</td>';
        $itemRows .= '<td style="text-align:center;">' . htmlspecialchars($item['exchange_quantity']) . '</td>';
        $itemRows .= '<td style="text-align:right;">₱' . number_format($item['original_price'], 2) . '</td>';
        $itemRows .= '<td style="text-align:right;">₱' . number_format($item['original_price'] * $item['exchange_quantity'], 2) . '</td>';
        $itemRows .= '</tr>';
    }
    
    // New items section
    $itemRows .= '<tr><td colspan="5" class="section-header">NEW ITEMS (Received)</td></tr>';
    $itemRows .= '<tr>
        <th style="width:35%">Item Description</th>
        <th style="width:15%">Size</th>
        <th style="width:15%">Qty</th>
        <th style="width:15%">Unit Price</th>
        <th style="width:20%">Subtotal</th>
    </tr>';
    
    foreach ($exchange_items as $item) {
        $itemRows .= '<tr>';
        $itemRows .= '<td>' . htmlspecialchars($item['new_item_name']) . '</td>';
        $itemRows .= '<td style="text-align:center;">' . htmlspecialchars($item['new_size']) . '</td>';
        $itemRows .= '<td style="text-align:center;">' . htmlspecialchars($item['exchange_quantity']) . '</td>';
        $itemRows .= '<td style="text-align:right;">₱' . number_format($item['new_price'], 2) . '</td>';
        $itemRows .= '<td style="text-align:right;">₱' . number_format($item['new_price'] * $item['exchange_quantity'], 2) . '</td>';
        $itemRows .= '</tr>';
    }
    
    // Price difference section
    $itemRows .= '<tr><td colspan="5" class="section-header">PRICE ADJUSTMENT</td></tr>';
    foreach ($exchange_items as $item) {
        $diffClass = $item['subtotal_adjustment'] > 0 ? 'positive' : ($item['subtotal_adjustment'] < 0 ? 'negative' : '');
        $diffSign = $item['subtotal_adjustment'] > 0 ? '+' : '';
        
        $itemRows .= '<tr>';
        $itemRows .= '<td colspan="4" style="text-align:right;">
            ' . htmlspecialchars($item['new_item_name']) . ' (' . htmlspecialchars($item['new_size']) . ') - 
            Qty: ' . $item['exchange_quantity'] . ' × 
            (₱' . number_format($item['new_price'], 2) . ' - ₱' . number_format($item['original_price'], 2) . ')
        </td>';
        $itemRows .= '<td style="text-align:right;" class="price-diff ' . $diffClass . '">' . 
                     $diffSign . '₱' . number_format(abs($item['subtotal_adjustment']), 2) . '</td>';
        $itemRows .= '</tr>';
    }
    
    // Adjustment box
    $adjustmentBox = '';
    if ($adjustmentType == 'additional_payment') {
        $adjustmentBox = '<div class="adjustment-box">
            <div class="title">ADDITIONAL PAYMENT REQUIRED:</div>
            <div class="amount additional">₱' . number_format(abs($totalPriceDifference), 2) . '</div>
            <div style="font-size:0.9em; margin-top:4px;">Please pay this amount to complete the exchange.</div>
        </div>';
    } elseif ($adjustmentType == 'refund') {
        $adjustmentBox = '<div class="adjustment-box">
            <div class="title">REFUND DUE TO CUSTOMER:</div>
            <div class="amount refund">₱' . number_format(abs($totalPriceDifference), 2) . '</div>
            <div style="font-size:0.9em; margin-top:4px;">This amount will be refunded to the customer.</div>
        </div>';
    } else {
        $adjustmentBox = '<div class="adjustment-box">
            <div class="title">EQUAL EXCHANGE - NO PRICE ADJUSTMENT</div>
            <div style="font-size:0.9em; margin-top:4px;">Original and new items have the same total value.</div>
        </div>';
    }
    
    // Determine labels based on user type
    $userTypeLabel = $isEmployee ? 'Employee Name:' : 'Student Name:';
    $userNumberLabel = $isEmployee ? 'Employee No.:' : 'Student No.:';
    $copyTypeLabel = $isEmployee ? 'EMPLOYEE COPY' : 'CUSTOMER COPY';
    
    return '
      <div class="slip-half">
        <div class="slip-header-flex">
          <div class="slip-header-logo"><img src="' . $logo_src . '" alt="STI Logo" /></div>
          <div class="slip-header-center">
            <div class="sti-lucena">STI LUCENA</div>
            <div class="exchange-slip-title">EXCHANGE SLIP WITH PRICE ADJUSTMENT</div>
          </div>
          <div class="slip-header-copy">' . htmlspecialchars($copyTypeLabel) . '</div>
        </div>
        
        <table class="slip-header-table">
          <tr>
            <td style="width:20%;"><b>' . $userTypeLabel . '</b></td>
            <td style="width:30%;">' . $studentName . '</td>
            <td style="width:18%;"><b>' . $userNumberLabel . '</b></td>
            <td style="width:32%;">' . $studentIdNumber . '</td>
          </tr>
          <tr>
            <td><b>Exchange No.:</b></td>
            <td>' . $exchangeNumber . '</td>
            <td><b>Original Order No.:</b></td>
            <td>' . $orderNumber . '</td>
          </tr>
          <tr>
            <td><b>Exchange Date:</b></td>
            <td>' . $exchangeDate . '</td>
            <td><b>Original Order Date:</b></td>
            <td>' . $orderDate . '</td>
          </tr>
        </table>
        
        <table class="slip-main-table">
          <tbody>' . $itemRows . '</tbody>
        </table>
        
        ' . $adjustmentBox . '
        
        <div class="signature-section">
          <div class="signature-box">
            <div class="label">Processed by:</div>
            <div class="line">' . $approvedBy . '</div>
          </div>
          <div class="signature-box">
            <div class="label">Approved by:</div>
            <div class="line">PAMO Staff</div>
          </div>
          <div class="signature-box">
            <div class="label">Customer Signature:</div>
            <div class="line">' . $studentName . '</div>
          </div>
        </div>
        
        <div class="footer-note">
          <b>EXCHANGE POLICY:</b> Exchanges are allowed within 24 hours from original purchase. 
          All items must be in original condition with tags intact. 
          Price adjustments will be processed according to current pricing.
        </div>
      </div>';
}

$html = '<html><head><meta charset="UTF-8">' . $css . '</head><body><div class="slip-a4">';
$html .= renderExchangeSlip('CUSTOMER COPY', $logo_src, $studentName, $studentIdNumber, $exchangeNumber, $orderNumber, $exchangeDate, $orderDate, $exchange_items, $totalPriceDifference, $adjustmentType, $approvedBy, $isEmployee);
$html .= renderExchangeSlip('SHOP COPY', $logo_src, $studentName, $studentIdNumber, $exchangeNumber, $orderNumber, $exchangeDate, $orderDate, $exchange_items, $totalPriceDifference, $adjustmentType, $approvedBy, $isEmployee);
$html .= '</div></body></html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('exchange_slip_' . $exchangeNumber . '.pdf', ['Attachment' => 1]);
exit;
