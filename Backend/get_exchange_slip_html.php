<?php
/**
 * Get Exchange Slip HTML for Preview
 * Returns the HTML content of the exchange slip (not PDF)
 */

require_once '../Includes/connection.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('Unauthorized');
}

$exchange_id = isset($_GET['exchange_id']) ? intval($_GET['exchange_id']) : 0;

if (!$exchange_id) {
    http_response_code(400);
    die('No exchange ID provided');
}

// Check if user is PAMO staff
$role = strtoupper($_SESSION['role_category'] ?? '');
$programAbbr = strtoupper($_SESSION['program_abbreviation'] ?? '');
if (!($role === 'EMPLOYEE' && $programAbbr === 'PAMO')) {
    http_response_code(403);
    die('Unauthorized - PAMO access required');
}

// Get exchange details
$stmt = $conn->prepare('
    SELECT oe.*, o.order_number, o.created_at as order_date,
           a.first_name, a.last_name, a.id_number, a.email
    FROM order_exchanges oe
    JOIN orders o ON oe.order_id = o.id
    JOIN account a ON oe.user_id = a.id
    WHERE oe.id = ?
');
$stmt->execute([$exchange_id]);
$exchange = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exchange) {
    http_response_code(404);
    die('Exchange not found');
}

// Get exchange items
$stmt = $conn->prepare('
    SELECT * FROM order_exchange_items 
    WHERE exchange_id = ?
    ORDER BY id
');
$stmt->execute([$exchange_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format dates
$exchange_date = date('F d, Y', strtotime($exchange['created_at']));
$order_date = date('F d, Y', strtotime($exchange['order_date']));

// Determine adjustment message
$adjustment_message = '';
$adjustment_amount = abs($exchange['total_price_difference']);

if ($exchange['adjustment_type'] === 'additional_payment') {
    $adjustment_message = "Customer needs to pay additional: ₱" . number_format($adjustment_amount, 2);
} elseif ($exchange['adjustment_type'] === 'refund') {
    $adjustment_message = "Customer will receive refund: ₱" . number_format($adjustment_amount, 2);
} else {
    $adjustment_message = "No payment adjustment needed.";
}

// Generate HTML
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Exchange Slip - <?php echo htmlspecialchars($exchange['exchange_number']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: white;
            font-size: 12px;
        }
        
        .slip-container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding: 15px;
            background: #fff;
        }
        
        /* Header similar to receipt */
        .slip-header-flex {
            width: 100%;
            display: flex;
            flex-direction: row;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #222;
        }
        
        .slip-header-logo img {
            height: 60px;
            width: auto;
            display: block;
        }
        
        .slip-header-logo {
            flex: 0 0 80px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
        }
        
        .slip-header-center {
            flex: 1 1 auto;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .sti-lucena {
            font-size: 1.5em;
            font-weight: bold;
            letter-spacing: 1px;
            margin-bottom: 2px;
        }
        
        .exchange-slip-title {
            font-size: 1.2em;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        
        .slip-header-type {
            flex: 0 0 100px;
            text-align: right;
            font-size: 1em;
            font-weight: bold;
            margin-top: 5px;
        }
        
        /* Info section */
        .info-section {
            margin-bottom: 15px;
        }
        
        .info-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 1em;
        }
        
        .info-table td {
            padding: 4px 8px;
            vertical-align: bottom;
            border-bottom: 1px solid #222;
        }
        
        .info-table td:nth-child(odd) {
            font-weight: bold;
            width: 150px;
        }
        
        .info-table tr:last-child td {
            border-bottom: none;
        }
        
        /* Items table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        .items-table th,
        .items-table td {
            border: 1px solid #222;
            padding: 8px;
            text-align: left;
        }
        
        .items-table th {
            background: #f2f2f2;
            font-weight: bold;
            text-align: center;
        }
        
        .items-table td {
            vertical-align: top;
            background: #fff;
        }
        
        .adjustment-box {
            border: 2px solid #222;
            padding: 12px 15px;
            margin: 15px 0;
            text-align: center;
            background: #fffef0;
        }
        
        .adjustment-box h3 {
            margin-bottom: 8px;
            font-size: 1.1em;
            color: #333;
        }
        
        .adjustment-box p {
            font-size: 1em;
            font-weight: bold;
            margin: 0;
        }
        
        .notes {
            margin: 15px 0;
            padding: 10px 12px;
            background: #f8f8f8;
            border-left: 3px solid #222;
            font-size: 0.95em;
        }
        
        .footer-section {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px solid #222;
        }
        
        .footer-note {
            text-align: center;
            font-size: 0.9em;
            margin-bottom: 20px;
            color: #555;
        }
        
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .signature-table td {
            border: 1px solid #222;
            padding: 8px;
            vertical-align: top;
        }
        
        .signature-table .sig-label {
            font-weight: bold;
            font-size: 0.95em;
            background: #f8f8f8;
            height: 30px;
        }
        
        .signature-table .sig-box {
            height: 50px;
            background: #fff;
        }
        
        .signature-table .sig-name {
            font-weight: bold;
            text-align: center;
            padding-top: 20px;
            font-size: 12px;
        }
        
        @media print {
            body {
                padding: 0;
            }
            .slip-container {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="slip-container">
        <!-- Header similar to receipt -->
        <div class="slip-header-flex">
            <div class="slip-header-logo">
                <?php
                $logo_path = realpath(__DIR__ . '/../Images/STI-LOGO.png');
                if ($logo_path && file_exists($logo_path)) {
                    $logo_data = base64_encode(file_get_contents($logo_path));
                    $logo_src = 'data:image/png;base64,' . $logo_data;
                    echo '<img src="' . $logo_src . '" alt="STI Logo" />';
                }
                ?>
            </div>
            <div class="slip-header-center">
                <div class="sti-lucena">STI LUCENA</div>
                <div class="exchange-slip-title">EXCHANGE SLIP</div>
            </div>
            <div class="slip-header-type">PAMO COPY</div>
        </div>
        
        <!-- Info section -->
        <div class="info-section">
            <table class="info-table">
                <tr>
                    <td>Exchange No:</td>
                    <td><?php echo htmlspecialchars($exchange['exchange_number']); ?></td>
                    <td>Date:</td>
                    <td><?php echo $exchange_date; ?></td>
                </tr>
                <tr>
                    <td>Student Name:</td>
                    <td><?php echo htmlspecialchars($exchange['first_name'] . ' ' . $exchange['last_name']); ?></td>
                    <td>Student No:</td>
                    <td><?php echo htmlspecialchars($exchange['id_number']); ?></td>
                </tr>
                <tr>
                    <td>Order Number:</td>
                    <td><?php echo htmlspecialchars($exchange['order_number']); ?></td>
                    <td>Order Date:</td>
                    <td><?php echo $order_date; ?></td>
                </tr>
            </table>
        </div>
        
        <h3 style="margin: 15px 0 10px 0; font-size: 1.1em;">Exchange Items:</h3>
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 25%;">Original Item</th>
                    <th style="width: 10%;">Size</th>
                    <th style="width: 8%;">Qty</th>
                    <th style="width: 12%;">Unit Price</th>
                    <th style="width: 10%;">New Size</th>
                    <th style="width: 12%;">New Price</th>
                    <th style="width: 13%;">Difference</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Group items by original item code for better readability
                $grouped_items = [];
                foreach ($items as $item) {
                    $key = $item['original_item_code'] . '|' . $item['original_size'];
                    if (!isset($grouped_items[$key])) {
                        $grouped_items[$key] = [
                            'original_item_name' => $item['original_item_name'],
                            'original_size' => $item['original_size'],
                            'original_price' => $item['original_price'],
                            'variants' => []
                        ];
                    }
                    $grouped_items[$key]['variants'][] = $item;
                }
                
                foreach ($grouped_items as $group):
                    $first_variant = true;
                    $total_original_qty = array_sum(array_column($group['variants'], 'exchange_quantity'));
                    foreach ($group['variants'] as $variant):
                ?>
                <tr>
                    <?php if ($first_variant): ?>
                    <td rowspan="<?php echo count($group['variants']); ?>" style="vertical-align: middle; text-align: left;">
                        <strong><?php echo htmlspecialchars($group['original_item_name']); ?></strong><br>
                        <small style="color: #666;">Total Qty: <?php echo $total_original_qty; ?> pc(s)</small>
                    </td>
                    <td rowspan="<?php echo count($group['variants']); ?>" style="vertical-align: middle; text-align: center;">
                        <strong><?php echo htmlspecialchars($group['original_size']); ?></strong>
                    </td>
                    <?php $first_variant = false; endif; ?>
                    <td style="text-align: center;"><?php echo $variant['exchange_quantity']; ?></td>
                    <td style="text-align: right;">₱<?php echo number_format($variant['original_price'], 2); ?></td>
                    <td style="text-align: center;"><strong><?php echo htmlspecialchars($variant['new_size']); ?></strong></td>
                    <td style="text-align: right;">₱<?php echo number_format($variant['new_price'], 2); ?></td>
                    <td style="text-align: right; <?php echo $variant['price_difference'] > 0 ? 'color: #c53030; font-weight: bold;' : ($variant['price_difference'] < 0 ? 'color: #38a169; font-weight: bold;' : ''); ?>">
                        <?php 
                        if ($variant['price_difference'] > 0) {
                            echo '+₱' . number_format($variant['price_difference'], 2);
                        } elseif ($variant['price_difference'] < 0) {
                            echo '₱' . number_format($variant['price_difference'], 2);
                        } else {
                            echo '₱0.00';
                        }
                        ?>
                    </td>
                </tr>
                <?php 
                    endforeach;
                endforeach; 
                ?>
            </tbody>
        </table>
        
        <div class="adjustment-box">
            <h3><?php echo strtoupper(str_replace('_', ' ', $exchange['adjustment_type'])); ?></h3>
            <p><?php echo $adjustment_message; ?></p>
        </div>
        
        <?php if (!empty($exchange['remarks'])): ?>
        <div class="notes">
            <strong>Remarks:</strong> <?php echo nl2br(htmlspecialchars($exchange['remarks'])); ?>
        </div>
        <?php endif; ?>
        
        <div class="footer-section">
            <div class="footer-note">
                <strong>Exchange Policy:</strong> Exchange must be processed within 24 hours of original purchase.<br>
                Items must be in original condition with tags attached.
            </div>
            
            <table class="signature-table">
                <tr>
                    <td class="sig-label" style="width: 50%;">Processed by (PAMO):</td>
                    <td class="sig-label" style="width: 50%;">Customer Received by:</td>
                </tr>
                <tr>
                    <td class="sig-box">
                        <div class="sig-name"><?php echo htmlspecialchars($exchange['processed_by'] ?? 'PAMO Staff'); ?></div>
                    </td>
                    <td class="sig-box">
                        <div class="sig-name"><?php echo htmlspecialchars($exchange['first_name'] . ' ' . $exchange['last_name']); ?></div>
                    </td>
                </tr>
                <tr>
                    <td class="sig-label">Signature & Date:</td>
                    <td class="sig-label">Signature & Date:</td>
                </tr>
                <tr>
                    <td class="sig-box" style="height: 60px;"></td>
                    <td class="sig-box" style="height: 60px;"></td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
