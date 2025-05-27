<?php
require_once '../Includes/connection.php';
require_once '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

session_start();
if (!isset($_SESSION['user_id'])) {
    die('Unauthorized');
}

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
if (!$order_id) die('No order ID');

// Fetch order and check ownership
$stmt = $conn->prepare('SELECT * FROM pre_orders WHERE id = ? AND user_id = ?');
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order || !in_array($order['status'], ['approved', 'completed'])) die('Order not found or not approved/completed');

// Fetch user info
$user_stmt = $conn->prepare('SELECT first_name, last_name, email, program_or_position, id_number FROM account WHERE id = ?');
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Prepare items and fetch category for each
$order_items = json_decode($order['items'], true);
foreach ($order_items as &$item) {
    $item_code = $item['item_code'] ?? '';
    if ($item_code) {
        $cat_stmt = $conn->prepare('SELECT category FROM inventory WHERE item_code = ? LIMIT 1');
        $cat_stmt->execute([$item_code]);
        $cat_row = $cat_stmt->fetch(PDO::FETCH_ASSOC);
        $item['category'] = $cat_row ? $cat_row['category'] : '';
    } else {
        $item['category'] = '';
    }
}
unset($item);

// Prepare receipt data
$studentName = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
$studentIdNumber = htmlspecialchars($user['id_number']);
$course = htmlspecialchars($user['program_or_position']);
$email = htmlspecialchars($user['email']);
$transactionNumber = htmlspecialchars($order['order_number']);
$orderDate = date('F d, Y', strtotime($order['created_at']));
$totalAmount = 0;
foreach ($order_items as $item) {
    $totalAmount += $item['price'] * $item['quantity'];
}
$preparedBy = isset($order['approved_by']) ? htmlspecialchars($order['approved_by']) : '';

// Load and encode the logo
$logo_path = realpath(__DIR__ . '/../Images/STI-LOGO.png');
$logo_data = $logo_path && file_exists($logo_path) ? base64_encode(file_get_contents($logo_path)) : '';
$logo_src = $logo_data ? 'data:image/png;base64,' . $logo_data : '';

// Inline the original modal/print CSS for receipt (from preorders.css)
$css = '<style>
@page { size: A4; margin: 0; }
body { font-family: Arial, sans-serif; font-size: 12px; }
.receipt-a4 { width: 210mm; height: 297mm; padding: 0; margin: 0 auto; background: #fff; font-family: Arial, sans-serif; position: relative; }
.receipt-half { height: 148.5mm; box-sizing: border-box; padding: 10px 10px 6px 10px; border-bottom: 2.5px dashed #333; page-break-inside: avoid; background: #fff; overflow: hidden; display: flex; flex-direction: column; justify-content: flex-start; }
.receipt-header-flex { width: 100%; display: flex; flex-direction: row; align-items: flex-start; justify-content: space-between; margin-bottom: 2px; margin-top: 2px; min-height: 60px; }
.receipt-header-logo img { height: 60px; width: auto; display: block; }
.receipt-header-logo { flex: 0 0 80px; display: flex; align-items: center; justify-content: flex-start; }
.receipt-header-center { flex: 1 1 auto; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; min-width: 0; }
.sti-lucena { font-size: 1.35em; font-weight: bold; letter-spacing: 1px; margin-bottom: 0px; }
.sales-issuance-slip { font-size: 1.1em; font-weight: bold; letter-spacing: 0.5px; margin-top: 0px; }
.receipt-header-copy { flex: 0 0 100px; text-align: right; font-size: 1em; font-weight: bold; margin-top: 2px; margin-right: 2px; }
.receipt-header-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 1em; }
.receipt-header-table td { padding: 2px 6px 2px 0; vertical-align: bottom; border-bottom: 1px solid #222; }
.receipt-header-table tr:last-child td { border-bottom: none; }
.receipt-main-table { width: 100%; border-collapse: collapse; font-size: 1em; margin-bottom: 0px; table-layout: fixed; }
.receipt-main-table th, .receipt-main-table td { border: 1px solid #222; padding: 6px 8px; vertical-align: top; word-break: break-word; }
.receipt-main-table th { background: #f2f2f2; text-align: center; }
.receipt-main-table td { background: #fff; }
.signature-col { background: #fff; vertical-align: top; text-align: left; min-width: 180px; max-width: 220px; padding: 0 !important; }
.signature-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.signature-table .sig-label { font-weight: bold; font-size: 0.98em; border: 1px solid #222; border-bottom: none; padding: 4px 6px 2px 6px; background: #f8f8f8; }
.signature-table .sig-box { border: 1px solid #222; border-top: none; height: 28px; padding: 2px 6px; font-size: 0.97em; background: #fff; }
.signature-table .sig-name { font-size: 1em; margin-top: 2px; }
.receipt-footer-cell { text-align: left; font-size: 0.98em; padding-top: 10px; }
.receipt-footer-total { text-align: right; font-size: 1.05em; font-weight: bold; padding-top: 10px; }
</style>';

function renderReceipt($copyLabel, $logo_src, $studentName, $studentIdNumber, $transactionNumber, $orderDate, $order_items, $totalAmount, $preparedBy) {
    $dataRows = '';
    $rowspan = count($order_items);
    foreach ($order_items as $i => $item) {
        $cleanName = preg_replace('/\s*\([^)]*\)/', '', $item['item_name']);
        $cleanName = preg_replace('/\s*-\s*[^-]*$/', '', $cleanName);
        $row = '<tr>';
        $row .= '<td>' . htmlspecialchars($cleanName . ' ' . ($item['size'] ?? '')) . '</td>';
        $row .= '<td>' . htmlspecialchars($item['category'] ?? '') . '</td>';
        $row .= '<td style="text-align:center;">' . htmlspecialchars($item['quantity']) . '</td>';
        $row .= '<td style="text-align:right;">' . number_format($item['price'], 2) . '</td>';
        $row .= '<td style="text-align:right;">' . number_format($item['price'] * $item['quantity'], 2) . '</td>';
        if ($i === 0) {
            $row .= '<td class="signature-col" rowspan="' . $rowspan . '">
                <table class="signature-table">
                    <tr><td class="sig-label">Prepared by:</td></tr>
                    <tr><td class="sig-box">' . $preparedBy . '</td></tr>
                    <tr><td class="sig-label">OR Issued by:</td></tr>
                    <tr><td class="sig-box"><br><span style="font-weight:bold;">Cashier</span></td></tr>
                    <tr><td class="sig-label">Released by & date:</td></tr>
                    <tr><td class="sig-box"></td></tr>
                    <tr><td class="sig-label">RECEIVED BY:</td></tr>
                    <tr><td class="sig-box" style="height:40px;vertical-align:bottom;">
                        <div style="height:24px;"></div>
                        <div class="sig-name" style="font-weight:bold;text-decoration:underline;text-align:center;">' . $studentName . '</div>
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
    return '
      <div class="receipt-a4">
        <div class="receipt-half">' .
        '<div class="receipt-header-flex">
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
              <td><b>Student Name:</b></td>
              <td>' . $studentName . '</td>
              <td><b>Student No.:</b></td>
              <td>' . $studentIdNumber . '</td>
              <td><b>DATE:</b></td>
              <td>' . $orderDate . '</td>
            </tr>
            <tr>
              <td><b>Issuance Slip No.:</b></td>
              <td>' . $transactionNumber . '</td>
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
      </div>
    </div>';
}

$html = '<html><head><meta charset="UTF-8">' . $css . '</head><body>';
$html .= renderReceipt('STUDENT COPY', $logo_src, $studentName, $studentIdNumber, $transactionNumber, $orderDate, $order_items, $totalAmount, $preparedBy);
$html .= '</body></html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('receipt_' . $transactionNumber . '.pdf', ['Attachment' => 1]);
exit; 