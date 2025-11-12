<?php
date_default_timezone_set('Asia/Manila');
session_start();
require_once '../Includes/connection.php';
require_once '../Includes/exchange_helpers.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Pages/login.php?redirect=../PAMO_PAGES/exchanges.php");
    exit();
}
$role = strtoupper($_SESSION['role_category'] ?? '');
$programAbbr = strtoupper($_SESSION['program_abbreviation'] ?? '');
if (!($role === 'EMPLOYEE' && $programAbbr === 'PAMO')) {
    header("Location: ../Pages/home.php");
    exit();
}

$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch all exchanges with customer info
$query = "
    SELECT 
        oe.*,
        o.order_type,
        o.created_at as order_date,
        a.first_name,
        a.last_name,
        a.email,
        a.program_or_position,
        a.id_number,
        a.role_category,
        processor.first_name as processor_fname,
        processor.last_name as processor_lname,
        COUNT(oei.id) as items_count,
        SUM(oei.exchange_quantity) as total_quantity
    FROM order_exchanges oe
    JOIN orders o ON oe.order_id = o.id
    JOIN account a ON oe.user_id = a.id
    LEFT JOIN account processor ON oe.processed_by = processor.id
    LEFT JOIN order_exchange_items oei ON oe.id = oei.exchange_id
    GROUP BY oe.id
    ORDER BY oe.exchange_date DESC
";

$stmt = $conn->prepare($query);
$stmt->execute();
$exchanges = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get exchange items for each exchange
foreach ($exchanges as &$exchange) {
    $items_stmt = $conn->prepare("SELECT * FROM order_exchange_items WHERE exchange_id = ?");
    $items_stmt->execute([$exchange['id']]);
    $exchange['exchange_items'] = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($exchange);

$total_items = count($exchanges);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAMO - Exchange Management</title>
    <link rel="stylesheet" href="../PAMO CSS/styles.css">
    <link rel="stylesheet" href="../PAMO CSS/orders.css">
    <link rel="stylesheet" href="../CSS/logout-modal.css">
    <link rel="stylesheet" href="../CSS/exchange.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .exchange-items-preview {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            margin-top: 12px;
            font-size: 0.9em;
        }
        
        .exchange-item-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .exchange-item-row:last-child {
            border-bottom: none;
        }
        
        .exchange-item-from, .exchange-item-to {
            flex: 1;
        }
        
        .exchange-item-arrow {
            padding: 0 12px;
            color: #666;
        }
        
        .adjustment-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
            margin-top: 8px;
        }
        
        .adjustment-badge.additional {
            background: #ffebee;
            color: #c62828;
        }
        
        .adjustment-badge.refund {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .adjustment-badge.none {
            background: #f5f5f5;
            color: #757575;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }
        
        .btn-approve, .btn-reject, .btn-complete, .btn-view-slip {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 0.85em;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
        }
        
        .btn-approve {
            background: #4caf50;
            color: white;
        }
        
        .btn-approve:hover {
            background: #45a049;
        }
        
        .btn-reject {
            background: #f44336;
            color: white;
        }
        
        .btn-reject:hover {
            background: #da190b;
        }
        
        .btn-complete {
            background: #2196f3;
            color: white;
        }
        
        .btn-complete:hover {
            background: #0b7dda;
        }
        
        .btn-view-slip {
            background: #ff9800;
            color: white;
        }
        
        .btn-view-slip:hover {
            background: #f57c00;
        }
    </style>
</head>

<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header>
                <div class="header-title-search" style="display: flex; align-items: center; gap: 24px;">
                    <h1 class="page-title" style="color: #764ba2; font-size: 2rem; font-weight: bold; margin: 0 18px 0 0;">
                        <i class="fas fa-exchange-alt"></i> Exchange Management
                    </h1>
                    <div class="search-bar">
                        <i class="material-icons">search</i>
                        <input type="text" id="searchInput" placeholder="Search exchanges...">
                    </div>
                </div>
                <div class="header-actions">
                    <div class="filter-dropdown">
                        <select id="statusFilter" onchange="filterByStatus(this.value)">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                </div>
            </header>

            <div class="orders-content">
                <div class="results-info">
                    <span class="results-count">
                        Total Exchanges: <?php echo $total_items; ?>
                    </span>
                </div>
                
                <div class="orders-grid">
                    <?php if (!empty($exchanges)): ?>
                        <?php foreach ($exchanges as $exchange): ?>
                            <div class="order-card" data-exchange-id="<?php echo $exchange['id']; ?>" data-status="<?php echo $exchange['status']; ?>">
                                <div class="order-header">
                                    <div class="order-header-row">
                                        <h3>
                                            <i class="fas fa-exchange-alt"></i> 
                                            Exchange #<?php echo htmlspecialchars($exchange['exchange_number']); ?>
                                        </h3>
                                        <?php 
                                        $orderType = $exchange['order_type'] ?? 'online';
                                        $orderTypeClass = $orderType === 'walk-in' ? 'walk-in-badge' : 'online-badge';
                                        $orderTypeIcon = $orderType === 'walk-in' ? '🚶' : '🌐';
                                        ?>
                                        <span class="order-type-badge <?php echo $orderTypeClass; ?>" title="<?php echo ucfirst($orderType); ?> Order">
                                            <?php echo $orderTypeIcon; ?> <?php echo ucfirst($orderType); ?>
                                        </span>
                                    </div>
                                    <div class="status-badge-wrapper">
                                        <span class="exchange-status-badge <?php echo $exchange['status']; ?>">
                                            <?php echo ucfirst($exchange['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="order-details">
                                    <div class="customer-info">
                                        <p><strong>Customer:</strong> <?php echo htmlspecialchars($exchange['first_name'] . ' ' . $exchange['last_name']); ?></p>
                                        <p><strong>ID Number:</strong> <?php echo htmlspecialchars($exchange['id_number']); ?></p>
                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($exchange['email']); ?></p>
                                        <p><strong>Original Order:</strong> <?php echo htmlspecialchars($exchange['order_number']); ?></p>
                                        <p><strong>Exchange Date:</strong> <?php echo date('M d, Y h:i A', strtotime($exchange['exchange_date'])); ?></p>
                                        <p><strong>Items:</strong> <?php echo $exchange['items_count']; ?> item(s), <?php echo $exchange['total_quantity']; ?> qty</p>
                                        <?php if ($orderType === 'walk-in' && !empty($exchange['processor_fname'])): ?>
                                            <p><strong>Processed by:</strong> <?php echo htmlspecialchars($exchange['processor_fname'] . ' ' . $exchange['processor_lname']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($exchange['remarks'])): ?>
                                        <div style="margin-top: 12px; padding: 10px; background: #fff3cd; border-radius: 4px; font-size: 0.9em;">
                                            <strong>Remarks:</strong> <?php echo nl2br(htmlspecialchars($exchange['remarks'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($exchange['status'] === 'rejected' && !empty($exchange['rejection_reason'])): ?>
                                        <div style="margin-top: 12px; padding: 10px; background: #ffebee; border-radius: 4px; font-size: 0.9em; color: #c62828;">
                                            <strong>Rejection Reason:</strong> <?php echo nl2br(htmlspecialchars($exchange['rejection_reason'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="exchange-items-preview">
                                        <strong style="display: block; margin-bottom: 8px;">Exchange Items:</strong>
                                        <?php foreach ($exchange['exchange_items'] as $item): ?>
                                            <div class="exchange-item-row">
                                                <div class="exchange-item-from">
                                                    <div style="font-weight: 600; color: #d32f2f;">
                                                        <?php echo htmlspecialchars($item['original_item_name']); ?>
                                                    </div>
                                                    <div style="font-size: 0.85em; color: #666;">
                                                        Size: <?php echo htmlspecialchars($item['original_size']); ?> | 
                                                        Qty: <?php echo $item['exchange_quantity']; ?> | 
                                                        ₱<?php echo number_format($item['original_price'], 2); ?>
                                                    </div>
                                                </div>
                                                <div class="exchange-item-arrow">
                                                    <i class="fas fa-arrow-right"></i>
                                                </div>
                                                <div class="exchange-item-to">
                                                    <div style="font-weight: 600; color: #388e3c;">
                                                        <?php echo htmlspecialchars($item['new_item_name']); ?>
                                                    </div>
                                                    <div style="font-size: 0.85em; color: #666;">
                                                        Size: <?php echo htmlspecialchars($item['new_size']); ?> | 
                                                        Qty: <?php echo $item['exchange_quantity']; ?> | 
                                                        ₱<?php echo number_format($item['new_price'], 2); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <?php if ($exchange['adjustment_type'] != 'none'): ?>
                                        <div class="adjustment-badge <?php echo $exchange['adjustment_type']; ?>">
                                            <?php 
                                            if ($exchange['adjustment_type'] == 'additional_payment') {
                                                echo "<i class='fas fa-plus-circle'></i> Additional Payment: ₱" . number_format(abs($exchange['total_price_difference']), 2);
                                            } elseif ($exchange['adjustment_type'] == 'refund') {
                                                echo "<i class='fas fa-minus-circle'></i> Refund Due: ₱" . number_format(abs($exchange['total_price_difference']), 2);
                                            }
                                            ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="adjustment-badge none">
                                            <i class='fas fa-equals'></i> Equal Exchange (No Price Adjustment)
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="action-buttons">
                                        <?php if ($exchange['status'] === 'pending'): ?>
                                            <button class="btn-approve" onclick="approveExchange(<?php echo $exchange['id']; ?>)">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button class="btn-reject" onclick="rejectExchange(<?php echo $exchange['id']; ?>)">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        <?php elseif ($exchange['status'] === 'approved'): ?>
                                            <button class="btn-complete" onclick="completeExchange(<?php echo $exchange['id']; ?>)">
                                                <i class="fas fa-check-double"></i> Mark as Completed
                                            </button>
                                            <a href="../Backend/generate_exchange_slip.php?exchange_id=<?php echo $exchange['id']; ?>&admin=1" target="_blank" class="btn-view-slip" style="text-decoration: none;">
                                                <i class="fas fa-file-pdf"></i> View Slip
                                            </a>
                                        <?php elseif ($exchange['status'] === 'completed'): ?>
                                            <a href="../Backend/generate_exchange_slip.php?exchange_id=<?php echo $exchange['id']; ?>&admin=1" target="_blank" class="btn-view-slip" style="text-decoration: none;">
                                                <i class="fas fa-file-pdf"></i> View Slip
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 60px 20px; color: #666;">
                            <i class="fas fa-exchange-alt" style="font-size: 4em; margin-bottom: 20px; opacity: 0.3;"></i>
                            <h3>No Exchange Requests Yet</h3>
                            <p>Exchange requests will appear here when customers submit them.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="../Javascript/logout-modal.js"></script>
    <script>
        // Filter by status
        function filterByStatus(status) {
            const url = new URL(window.location.href);
            if (status) {
                url.searchParams.set('status', status);
            } else {
                url.searchParams.delete('status');
            }
            window.location.href = url.toString();
        }
        
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const cards = document.querySelectorAll('.order-card');
            
            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
        
        // Approve exchange
        function approveExchange(exchangeId) {
            if (!confirm('Are you sure you want to approve this exchange?')) {
                return;
            }
            
            fetch('../PAMO_DASHBOARD_BACKEND/approve_exchange.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `exchange_id=${exchangeId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Exchange approved successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while approving the exchange');
            });
        }
        
        // Reject exchange
        function rejectExchange(exchangeId) {
            const reason = prompt('Please enter rejection reason:');
            if (!reason) {
                return;
            }
            
            fetch('../PAMO_DASHBOARD_BACKEND/reject_exchange.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `exchange_id=${exchangeId}&reason=${encodeURIComponent(reason)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Exchange rejected successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while rejecting the exchange');
            });
        }
        
        // Complete exchange
        function completeExchange(exchangeId) {
            if (!confirm('Are you sure you want to mark this exchange as completed? This action cannot be undone.')) {
                return;
            }
            
            fetch('../PAMO_DASHBOARD_BACKEND/complete_exchange.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `exchange_id=${exchangeId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Exchange marked as completed successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while completing the exchange');
            });
        }
    </script>
</body>

</html>
