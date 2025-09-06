<?php
session_start();
require_once '../Includes/connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get filter parameter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Fetch user's orders with filter
$query = "SELECT * FROM orders WHERE user_id = ?";
if ($status_filter !== 'all') {
    $query .= " AND status = ?";
}
$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
if ($status_filter !== 'all') {
    $stmt->execute([$_SESSION['user_id'], $status_filter]);
} else {
    $stmt->execute([$_SESSION['user_id']]);
}
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order_id'])) {
    $cancel_order_id = $_POST['cancel_order_id'];
    // Only allow cancel if the order is still pending and belongs to this user
    $stmt = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status = 'pending'");
    $stmt->execute([$cancel_order_id, $_SESSION['user_id']]);

    // Add strike and cooldown for cancellation (same as voided)
    $strikeStmt = $conn->prepare("UPDATE account SET pre_order_strikes = pre_order_strikes + 1, last_strike_time = NOW() WHERE id = ?");
    $strikeStmt->execute([$_SESSION['user_id']]);
    $checkStrikeStmt = $conn->prepare("SELECT pre_order_strikes FROM account WHERE id = ?");
    $checkStrikeStmt->execute([$_SESSION['user_id']]);
    $strikes = $checkStrikeStmt->fetchColumn();
    if ($strikes >= 3) {
        $blockStmt = $conn->prepare("UPDATE account SET is_strike = 1 WHERE id = ?");
        $blockStmt->execute([$_SESSION['user_id']]);
    }

    // Log activity for each item in the cancelled order
    $order_stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $order_stmt->execute([$cancel_order_id, $_SESSION['user_id']]);
    $order = $order_stmt->fetch(PDO::FETCH_ASSOC);
    if ($order) {
        $order_items = json_decode($order['items'], true);
        if ($order_items && is_array($order_items)) {
            foreach ($order_items as $item) {
                $activity_description = "Cancelled - Order #: {$order['order_number']}, Item: {$item['item_name']}, Quantity: {$item['quantity']}";
                $activityStmt = $conn->prepare(
                    "INSERT INTO activities (
                        action_type,
                        description,
                        item_code,
                        user_id,
                        timestamp
                    ) VALUES (?, ?, ?, ?, NOW())"
                );
                $activityStmt->execute([
                    'Cancelled',
                    $activity_description,
                    $item['item_code'],
                    $_SESSION['user_id']
                ]);
            }
        }
    }
    // Optionally, add a notification or message here
    header("Location: MyOrders.php?status=" . urlencode($status_filter)); // Refresh page
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders</title>
    <link rel="stylesheet" href="../CSS/MyOrders.css">
    <link rel="stylesheet" href="../CSS/header.css">
    <link rel="stylesheet" href="../CSS/global.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Smooch+Sans:wght@100..900&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include("../Includes/Header.php"); ?>

    <div class="orders-page">
        <div class="orders-header">
            <div class="header-content">
                <h1>My Orders</h1>
                <div class="filter-section">
                    <span>Filter by Status:</span>
                    <select id="statusFilter" onchange="filterOrders(this.value)">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Orders</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="voided" <?php echo $status_filter === 'voided' ? 'selected' : ''; ?>>Voided</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="orders-content">
            <?php if (!empty($orders)): ?>
                <div class="orders-grid">
                    <?php foreach ($orders as $order): 
                        $items = json_decode($order['items'], true);
                        $total_amount = $order['total_amount'];
                    ?>
                        <div class="order-card">
                            <div class="order-header">
                                <div class="order-info">
                                    <h3>Order #<?php echo htmlspecialchars($order['order_number']); ?></h3>
                                    <div class="order-date">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo date('F d, Y h:i A', strtotime($order['created_at'])); ?>
                                        <?php if ($order['status'] === 'completed' && isset($order['payment_date'])): ?>
                                            <br>
                                            <i class="fas fa-money-bill"></i>
                                            <span class="payment-date">Paid: <?php echo date('F d, Y h:i A', strtotime($order['payment_date'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="status-badge <?php echo $order['status']; ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                                <?php if ($order['status'] === 'rejected' && !empty($order['rejection_reason'])): ?>
                                    <span class="rejection-reason">
                                        Reason: <?php echo htmlspecialchars($order['rejection_reason']); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($order['status'] === 'pending'): ?>
                                    <form method="post" onsubmit="return confirm('Are you sure you want to cancel this order?');" style="display:inline;">
                                        <input type="hidden" name="cancel_order_id" value="<?php echo $order['id']; ?>">
                                        <button type="submit" class="cancel-btn">Cancel Order</button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <div class="order-details">
                                <?php foreach ($items as $item): 
                                    $clean_name = rtrim($item['item_name'], " SMLX234567");
                                ?>
                                    <div class="order-item">
                                        <div class="item-image">
                                            <?php
                                            $image_path = $item['image_path'] ?? '';
                                            $resolved = null;
                                            // 1) Try stored image path
                                            if (!empty($image_path)) {
                                                $name = basename($image_path);
                                                $candidateItemlist = __DIR__ . '/../uploads/itemlist/' . $name;
                                                $candidateRaw = __DIR__ . '/../' . ltrim($image_path, '/');
                                                if (is_file($candidateItemlist)) {
                                                    $resolved = 'uploads/itemlist/' . $name;
                                                } elseif (is_file($candidateRaw)) {
                                                    $resolved = ltrim($image_path, './');
                                                }
                                            }
                                            // 2) Try inventory by exact item_code + size
                                            if ($resolved === null) {
                                                try {
                                                    $sizeParam = $item['size'] ?? null;
                                                    $q = $conn->prepare("SELECT image_path FROM inventory WHERE item_code = ? AND (sizes = ? OR ? IS NULL) AND image_path IS NOT NULL AND image_path != '' LIMIT 1");
                                                    $q->execute([$item['item_code'], $sizeParam, $sizeParam]);
                                                    $rowImg = $q->fetch(PDO::FETCH_ASSOC);
                                                    if ($rowImg && !empty($rowImg['image_path'])) {
                                                        $name = basename($rowImg['image_path']);
                                                        $cand1 = __DIR__ . '/../uploads/itemlist/' . $name;
                                                        $cand2 = __DIR__ . '/../' . ltrim($rowImg['image_path'], '/');
                                                        if (is_file($cand1)) {
                                                            $resolved = 'uploads/itemlist/' . $name;
                                                        } elseif (is_file($cand2)) {
                                                            $resolved = ltrim($rowImg['image_path'], './');
                                                        }
                                                    }
                                                } catch (Throwable $e) {}
                                            }
                                            // 3) Last attempt: legacy prefix-suffixed item codes
                                            if ($resolved === null) {
                                                try {
                                                    $like = $item['item_code'] . '-%';
                                                    $q2 = $conn->prepare("SELECT image_path FROM inventory WHERE item_code LIKE ? AND image_path IS NOT NULL AND image_path != '' LIMIT 1");
                                                    $q2->execute([$like]);
                                                    $rowImg2 = $q2->fetch(PDO::FETCH_ASSOC);
                                                    if ($rowImg2 && !empty($rowImg2['image_path'])) {
                                                        $name = basename($rowImg2['image_path']);
                                                        $cand1 = __DIR__ . '/../uploads/itemlist/' . $name;
                                                        $cand2 = __DIR__ . '/../' . ltrim($rowImg2['image_path'], '/');
                                                        if (is_file($cand1)) {
                                                            $resolved = 'uploads/itemlist/' . $name;
                                                        } elseif (is_file($cand2)) {
                                                            $resolved = ltrim($rowImg2['image_path'], './');
                                                        }
                                                    }
                                                } catch (Throwable $e) {}
                                            }
                                            // 4) Fallback to default image
                                            if ($resolved === null) {
                                                $resolved = is_file(__DIR__ . '/../uploads/itemlist/default.png') ? 'uploads/itemlist/default.png' : 'uploads/itemlist/default.jpg';
                                            }
                                            ?>
                                            <img src="../<?php echo htmlspecialchars($resolved); ?>" alt="<?php echo htmlspecialchars($clean_name); ?>">
                                        </div>
                                        <div class="item-details">
                                            <span class="item-name"><?php echo htmlspecialchars($clean_name); ?></span>
                                        </div>
                                        <div class="item-size"><?php echo htmlspecialchars($item['size'] ?? 'N/A'); ?></div>
                                        <div class="item-quantity"><?php echo $item['quantity']; ?></div>
                                        <div class="item-price">₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="order-footer">
                                <div class="contact-info">
                                    <i class="fas fa-phone"></i>
                                    <?php echo htmlspecialchars($order['phone']); ?>
                                </div>
                                <div class="total-amount">
                                    <strong>Total Amount:</strong>
                                    <span>₱<?php echo number_format($total_amount, 2); ?></span>
                                </div>
                            </div>
                            <?php if (in_array($order['status'], ['approved', 'completed'])): ?>
                                <div style="margin-top: 1rem; text-align: right;">
                                    <a href="../Backend/generate_receipt.php?order_id=<?php echo $order['id']; ?>" class="download-receipt-btn" target="_blank" style="background: #007bff; color: #fff; padding: 0.5rem 1.2rem; border-radius: 4px; text-decoration: none; font-weight: 500; display: inline-block;">
                                        <i class="fas fa-file-pdf"></i> Download Receipt
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-orders">
                    <i class="fas fa-box-open"></i>
                    <p>You haven't placed any orders yet</p>
                    <a href="ProItemList.php" class="shop-now-btn">Start Shopping</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function filterOrders(status) {
            window.location.href = `MyOrders.php${status !== 'all' ? '?status=' + status : ''}`;
        }
    </script>

    <style>
        .status-badge.cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        .cancel-btn {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 0.4rem 1rem;
            font-weight: 500;
            cursor: pointer;
            margin-left: 1rem;
            transition: background 0.2s;
        }
        .cancel-btn:hover {
            background: #c82333;
        }
    </style>
</body>
</html> 