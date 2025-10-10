<?php
date_default_timezone_set('Asia/Manila');
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
                        <div class="order-card" data-order-id="<?php echo $order['id']; ?>">
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

        // Real-time order updates
        let lastOrderCheck = null;
        let currentStatusFilter = '<?php echo $status_filter; ?>';

        function formatTimeAgo(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            
            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return `${diffMins}m ago`;
            
            const diffHours = Math.floor(diffMins / 60);
            if (diffHours < 24) return `${diffHours}h ago`;
            
            const diffDays = Math.floor(diffHours / 24);
            if (diffDays < 7) return `${diffDays}d ago`;
            
            return date.toLocaleDateString();
        }

        function getStatusBadgeClass(status) {
            const statusClasses = {
                'pending': 'pending',
                'approved': 'approved', 
                'rejected': 'rejected',
                'completed': 'completed',
                'cancelled': 'cancelled',
                'voided': 'rejected' // Use rejected styling for voided
            };
            return statusClasses[status] || 'pending';
        }

        function createOrderCard(order) {
            const items = order.items_decoded || [];
            let itemsHtml = '';
            
            items.forEach(item => {
                const cleanName = item.item_name ? item.item_name.replace(/\s[SMLX234567]+$/, '') : '';
                itemsHtml += `
                    <div class="order-item">
                        <img src="../Images/${item.image_path || 'default.jpg'}" alt="${item.item_name || 'Item'}">
                        <div class="item-details">
                            <p class="item-name">${cleanName}</p>
                            <p class="item-info">${item.size || 'N/A'} - Qty: ${item.quantity || 0}</p>
                            <p class="item-price">₱${parseFloat(item.price || 0).toFixed(2)}</p>
                        </div>
                    </div>
                `;
            });

            const rejectionReason = order.rejection_reason ? 
                `<div class="rejection-reason">Reason: ${order.rejection_reason}</div>` : '';

            return `
                <div class="order-card">
                    <div class="order-header">
                        <h2>Order #${order.order_number || 'N/A'}</h2>
                        <div class="order-status">
                            <span class="status-badge ${getStatusBadgeClass(order.status)}">${order.status ? order.status.charAt(0).toUpperCase() + order.status.slice(1) : 'Unknown'}</span>
                        </div>
                    </div>
                    
                    <div class="order-info">
                        <p><i class="fas fa-calendar"></i> ${order.formatted_date || 'Unknown date'}</p>
                        ${order.formatted_payment_date ? `<p><i class="fas fa-credit-card"></i> Paid: ${order.formatted_payment_date}</p>` : ''}
                    </div>

                    <div class="order-items">
                        ${itemsHtml}
                    </div>
                    ${rejectionReason}
                    
                    <div class="order-footer">
                        <div class="total-amount">
                            <strong>Total: ₱${order.formatted_total || '0.00'}</strong>
                        </div>
                        ${order.status === 'pending' ? `
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="cancel_order_id" value="${order.id}">
                                <button type="submit" class="cancel-btn" onclick="return confirm('Are you sure you want to cancel this order?')">
                                    <i class="fas fa-times"></i> Cancel Order
                                </button>
                            </form>
                        ` : ''}
                    </div>
                </div>
            `;
        }

        async function updateOrdersRealTime() {
            try {
                const formData = new FormData();
                formData.append('action', 'get_user_orders');
                formData.append('status', currentStatusFilter);
                if (lastOrderCheck) {
                    formData.append('last_check', lastOrderCheck);
                }

                const response = await fetch('../Includes/order_operations.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                
                if (data.success && data.orders && data.orders.length > 0) {
                    if (!lastOrderCheck) {
                        // First load - just update timestamp
                        lastOrderCheck = data.last_update;
                    } else {
                        // Update only changed orders without page reload
                        updateOrderElements(data.orders);
                        lastOrderCheck = data.last_update;
                    }
                } else if (data.success) {
                    // Update timestamp even if no orders
                    lastOrderCheck = data.last_update;
                }
            } catch (error) {
                // Silently handle polling errors to avoid console spam
            }
        }

        function updateOrderElements(updatedOrders) {
            const ordersGrid = document.querySelector('.orders-grid');
            if (!ordersGrid) return;
            
            updatedOrders.forEach(order => {
                const existingCard = document.querySelector(`[data-order-id="${order.id}"]`);
                
                if (existingCard) {
                    // Update existing order card
                    updateSingleOrderCard(existingCard, order);
                }
                // Note: We only update existing cards during real-time updates
                // New orders are shown on page refresh to maintain data consistency
            });

            // Remove orders that no longer match the filter
            if (currentStatusFilter !== 'all') {
                const allCards = document.querySelectorAll('.order-card[data-order-id]');
                allCards.forEach(card => {
                    const orderId = parseInt(card.getAttribute('data-order-id'));
                    const orderInUpdate = updatedOrders.find(o => o.id === orderId);
                    
                    if (orderInUpdate && orderInUpdate.status !== currentStatusFilter) {
                        card.style.transition = 'all 0.3s ease';
                        card.style.opacity = '0';
                        card.style.transform = 'translateY(-20px)';
                        setTimeout(() => card.remove(), 300);
                    }
                });
            }
        }

        function updateSingleOrderCard(cardElement, order) {
            // Update status badge
            const statusBadge = cardElement.querySelector('.status-badge');
            if (statusBadge) {
                statusBadge.className = `status-badge ${order.status}`;
                statusBadge.textContent = order.status ? order.status.charAt(0).toUpperCase() + order.status.slice(1) : 'Unknown';
                
                // Add flash animation for status changes
                statusBadge.style.animation = 'flash 0.5s ease-in-out';
                setTimeout(() => statusBadge.style.animation = '', 500);
            }

            // Update rejection reason if exists
            const orderHeader = cardElement.querySelector('.order-header');
            const existingRejection = orderHeader.querySelector('.rejection-reason');
            
            if (order.rejection_reason) {
                if (!existingRejection) {
                    const rejectionSpan = document.createElement('span');
                    rejectionSpan.className = 'rejection-reason';
                    rejectionSpan.textContent = `Reason: ${order.rejection_reason}`;
                    orderHeader.appendChild(rejectionSpan);
                } else {
                    existingRejection.textContent = `Reason: ${order.rejection_reason}`;
                }
            } else if (existingRejection) {
                existingRejection.remove();
            }

            // Update payment date if completed
            const orderDate = cardElement.querySelector('.order-date');
            const paymentDate = orderDate.querySelector('.payment-date');
            
            if (order.status === 'completed' && order.formatted_payment_date) {
                if (!paymentDate) {
                    const paymentBr = document.createElement('br');
                    const paymentIcon = document.createElement('i');
                    paymentIcon.className = 'fas fa-money-bill';
                    const paymentSpan = document.createElement('span');
                    paymentSpan.className = 'payment-date';
                    paymentSpan.textContent = `Paid: ${order.formatted_payment_date}`;
                    
                    orderDate.appendChild(paymentBr);
                    orderDate.appendChild(paymentIcon);
                    orderDate.appendChild(paymentSpan);
                }
            }

            // Update cancel button visibility based on status
            const existingForm = orderHeader.querySelector('form');
            
            if (order.status === 'pending' && !existingForm) {
                // Add cancel button for pending orders
                const cancelForm = document.createElement('form');
                cancelForm.method = 'post';
                cancelForm.onsubmit = function() { return confirm('Are you sure you want to cancel this order?'); };
                cancelForm.style.display = 'inline';
                cancelForm.innerHTML = `
                    <input type="hidden" name="cancel_order_id" value="${order.id}">
                    <button type="submit" class="cancel-btn">Cancel Order</button>
                `;
                orderHeader.appendChild(cancelForm);
            } else if (order.status !== 'pending' && existingForm) {
                // Remove cancel button for non-pending orders
                existingForm.remove();
            }

            // Update receipt download button for approved/completed orders
            const orderFooter = cardElement.querySelector('.order-footer');
            const existingReceiptBtn = cardElement.querySelector('.download-receipt-btn');
            
            if ((order.status === 'approved' || order.status === 'completed') && !existingReceiptBtn) {
                // Add receipt download button for approved/completed orders
                const receiptDiv = document.createElement('div');
                receiptDiv.style.marginTop = '1rem';
                receiptDiv.style.textAlign = 'right';
                receiptDiv.innerHTML = `
                    <a href="../Backend/generate_receipt.php?order_id=${order.id}" 
                       class="download-receipt-btn" 
                       target="_blank" 
                       style="background: #007bff; color: #fff; padding: 0.5rem 1.2rem; border-radius: 4px; text-decoration: none; font-weight: 500; display: inline-block;">
                        <i class="fas fa-file-pdf"></i> Download Receipt
                    </a>
                `;
                
                // Insert after order-footer
                orderFooter.parentNode.insertBefore(receiptDiv, orderFooter.nextSibling);
            } else if (!(['approved', 'completed'].includes(order.status)) && existingReceiptBtn) {
                // Remove receipt button for non-approved/completed orders
                const receiptDiv = existingReceiptBtn.parentElement;
                if (receiptDiv) {
                    receiptDiv.remove();
                }
            }

        }

        function createOrderCardElement(order) {
            const items = order.items_decoded || [];
            let itemsHtml = '';
            
            items.forEach(item => {
                const cleanName = item.item_name ? item.item_name.replace(/\s[SMLX234567]+$/, '') : '';
                itemsHtml += `
                    <div class="order-item">
                        <img src="../Images/${item.image_path || 'default.jpg'}" alt="${item.item_name || 'Item'}">
                        <div class="item-details">
                            <p class="item-name">${cleanName}</p>
                            <p class="item-info">${item.size || 'N/A'} - Qty: ${item.quantity || 0}</p>
                            <p class="item-price">₱${parseFloat(item.price || 0).toFixed(2)}</p>
                        </div>
                    </div>
                `;
            });

            const rejectionReason = order.rejection_reason ? 
                `<div class="rejection-reason">Reason: ${order.rejection_reason}</div>` : '';

            const orderCard = document.createElement('div');
            orderCard.className = 'order-card';
            orderCard.setAttribute('data-order-id', order.id);
            orderCard.innerHTML = `
                <div class="order-header">
                    <h2>Order #${order.order_number || 'N/A'}</h2>
                    <div class="order-status">
                        <span class="status-badge ${getStatusBadgeClass(order.status)}">${order.status ? order.status.charAt(0).toUpperCase() + order.status.slice(1) : 'Unknown'}</span>
                    </div>
                </div>
                
                <div class="order-info">
                    <p><i class="fas fa-calendar"></i> ${order.formatted_date || 'Unknown date'}</p>
                    ${order.formatted_payment_date ? `<p class="payment-info"><i class="fas fa-credit-card"></i> Paid: ${order.formatted_payment_date}</p>` : ''}
                </div>

                <div class="order-items">
                    ${itemsHtml}
                </div>
                ${rejectionReason}
                
                <div class="order-footer">
                    <div class="total-amount">
                        <strong>Total: ₱${order.formatted_total || '0.00'}</strong>
                    </div>
                    ${order.status === 'pending' ? `
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="cancel_order_id" value="${order.id}">
                            <button type="submit" class="cancel-btn" onclick="return confirm('Are you sure you want to cancel this order?')">
                                <i class="fas fa-times"></i> Cancel Order
                            </button>
                        </form>
                    ` : ''}
                </div>
                ${(order.status === 'approved' || order.status === 'completed') ? `
                    <div style="margin-top: 1rem; text-align: right;">
                        <a href="../Backend/generate_receipt.php?order_id=${order.id}" 
                           class="download-receipt-btn" 
                           target="_blank" 
                           style="background: #007bff; color: #fff; padding: 0.5rem 1.2rem; border-radius: 4px; text-decoration: none; font-weight: 500; display: inline-block;">
                            <i class="fas fa-file-pdf"></i> Download Receipt
                        </a>
                    </div>
                ` : ''}
            `;
            
            return orderCard;
        }

        // Initialize real-time updates
        document.addEventListener('DOMContentLoaded', function() {
            // Initial check
            updateOrdersRealTime();
            
            // Check for updates every 15 seconds (more frequent than notifications since orders change less often)
            setInterval(updateOrdersRealTime, 15000);
        });
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
        
        /* Real-time update animations */
        @keyframes flash {
            0% { background-color: rgba(255, 193, 7, 0.3); }
            50% { background-color: rgba(255, 193, 7, 0.8); }
            100% { background-color: transparent; }
        }
        
        .order-card {
            transition: all 0.3s ease;
        }
        
        .status-badge {
            transition: all 0.3s ease;
        }
        
        /* Smooth transitions for new elements */
        .order-card[data-order-id] {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</body>
</html> 