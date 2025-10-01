<?php
session_start();
require_once '../Includes/connection.php';


if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Pages/login.php?redirect=../PAMO PAGES/orders.php");
    exit();
}
$role = strtoupper($_SESSION['role_category'] ?? '');
$programAbbr = strtoupper($_SESSION['program_abbreviation'] ?? '');
if (!($role === 'EMPLOYEE' && $programAbbr === 'PAMO')) {
    header("Location: ../Pages/home.php");
    exit();
}

$status = isset($_GET['status']) ? $_GET['status'] : '';

$query = "
    SELECT po.*, a.first_name, a.last_name, a.email, a.program_or_position, a.id_number
    FROM orders po
    JOIN account a ON po.user_id = a.id
";

if ($status) {
    $query .= " WHERE po.status = :status";
}

$query .= " ORDER BY po.created_at DESC";

$stmt = $conn->prepare($query);

if ($status) {
    $stmt->bindParam(':status', $status);
}

$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Add category to each item in each order ---
foreach ($orders as &$order) {
    $order_items = json_decode($order['items'], true);
    if ($order_items && is_array($order_items)) {
        foreach ($order_items as &$item) {
            // Fetch category from inventory table using item_code
            $item_code = $item['item_code'] ?? '';
            if ($item_code) {
                $cat_stmt = $conn->prepare("SELECT category FROM inventory WHERE item_code = ? LIMIT 1");
                $cat_stmt->execute([$item_code]);
                $cat_row = $cat_stmt->fetch(PDO::FETCH_ASSOC);
                $item['category'] = $cat_row ? $cat_row['category'] : '';
            } else {
                $item['category'] = '';
            }
        }
        $order['items'] = json_encode($order_items);
    }
}
unset($order);

include 'includes/pamo_loader.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAMO - Orders</title>
    <link rel="stylesheet" href="../PAMO CSS/styles.css">
    <link rel="stylesheet" href="../PAMO CSS/orders.css">
    <link rel="stylesheet" href="../CSS/logout-modal.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header>
                <div class="header-title-search" style="display: flex; align-items: center; gap: 24px;">
                    <h1 class="page-title" style="color: #007bff; font-size: 2rem; font-weight: bold; margin: 0 18px 0 0;">Orders</h1>
                    <div class="search-bar">
                        <i class="material-icons">search</i>
                        <input type="text" id="searchInput" placeholder="Search orders...">
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
                            <option value="voided" <?php echo $status === 'voided' ? 'selected' : ''; ?>>Voided</option>
                            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                </div>
            </header>

            <div class="orders-content">
                <div class="orders-grid">
                    <?php if (!empty($orders)): ?>
                        <?php foreach ($orders as $order): 
                            $order_items = json_decode($order['items'], true);
                            $total_amount = 0;
                            foreach ($order_items as $item) {
                                $total_amount += $item['price'] * $item['quantity'];
                            }
                        ?>
                            <div class="order-card" data-status="<?php echo $order['status']; ?>">
                                <div class="order-header">
                                    <h3>Order #<?php echo htmlspecialchars($order['order_number']); ?></h3>
                                    <span class="status-badge <?php echo $order['status']; ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="order-details">
                                    <div class="customer-info">
                                        <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></p>
                                        <p><strong>Student Number:</strong> <?php echo htmlspecialchars($order['id_number']); ?></p>
                                        <p><strong>Course/Strand:</strong> <?php echo htmlspecialchars($order['program_or_position']); ?></p>
                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($order['email']); ?></p>
                                    </div>
                                    
                                    <div class="items-list">
                                        <h4>Ordered Items:</h4>
                                        <div class="items-table">
                                            <div class="table-header">
                                                <span class="item-name">Item</span>
                                                <span class="item-size">Size</span>
                                                <span class="item-quantity">Qty</span>
                                                <span class="item-price">Price</span>
                                            </div>
                                            <?php foreach ($order_items as $item): 
                                                // Remove size suffix from item name
                                                $clean_name = rtrim($item['item_name'], " SMLX234567");
                                            ?>
                                                <div class="table-row">
                                                    <span class="item-name"><?php echo htmlspecialchars($clean_name); ?></span>
                                                    <span class="item-size"><?php echo htmlspecialchars($item['size'] ?? 'N/A'); ?></span>
                                                    <span class="item-quantity"><?php echo $item['quantity']; ?></span>
                                                    <span class="item-price">₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="order-footer">
                                        <div class="total-amount">
                                            <strong>Total:</strong> ₱<?php echo number_format($total_amount, 2); ?>
                                        </div>
                                        <div class="order-date">
                                            <?php echo date('F d, Y h:i A', strtotime($order['created_at'])); ?>
                                            <?php if ($order['status'] === 'completed' && isset($order['payment_date'])): ?>
                                                <br>
                                                <span class="payment-date">Paid: <?php echo date('F d, Y h:i A', strtotime($order['payment_date'])); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($order['status'] === 'pending'): ?>
                                    <div class="order-actions">
                                        <button class="accept-btn" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'approved')">
                                            <i class="fas fa-check"></i> Accept
                                        </button>
                                        <button class="reject-btn" onclick="showRejectionModal(<?php echo $order['id']; ?>)">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </div>
                                <?php elseif ($order['status'] === 'approved'): ?>
                                    <div class="order-actions">
                                        <button class="complete-btn" data-order-id="<?php echo $order['id']; ?>">
                                            <i class="fas fa-check-double"></i> Mark as Completed (After Payment)
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-orders">
                            <i class="material-icons">shopping_cart</i>
                            <p>No orders found</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Order Receipt Modal (hidden by default) -->
    <div id="orderReceiptModal" class="modal">
        <div class="modal-card">
            <div class="modal-header">
                <h2>Sales Receipt</h2>
                <span class="close" onclick="closeOrderReceiptModal()">&times;</span>
            </div>
            <div class="modal-body" id="orderReceiptBody">
                <!-- Receipt content will be injected here -->
            </div>
            <div class="modal-footer">
                <button type="button" onclick="printOrderReceipt()" class="save-btn">Print</button>
                <button type="button" onclick="closeOrderReceiptModal()" class="cancel-btn">Close</button>
            </div>
        </div>
    </div>

    <!-- Rejection Reason Modal -->
    <div id="rejectionModal" class="modal">
        <div class="modal-card">
            <div class="modal-header">
                <h2>Reject Order</h2>
                <span class="close" onclick="closeRejectionModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="rejectionReason">Reason for Rejection:</label>
                    <textarea id="rejectionReason" rows="4" placeholder="Please provide a reason for rejecting this order..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="submitRejection()" class="save-btn">Submit</button>
                <button type="button" onclick="closeRejectionModal()" class="cancel-btn">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const cards = document.querySelectorAll('.order-card');
            
            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(searchTerm) ? 'block' : 'none';
            });
        });

        // Filter by status
        function filterByStatus(status) {
            window.location.href = `orders.php?status=${status}`;
        }

        // Expose PHP $orders as a JS object for use in modal logic
        window.ORDERS = <?php echo json_encode($orders); ?>;
        // Expose PAMO user name for receipt
        window.PAMO_USER = { name: "<?php echo addslashes($_SESSION['name'] ?? ''); ?>" };
    </script>

    <script src="../Javascript/logout-modal.js"></script>
    <script src="../PAMO JS/orders.js"></script>
</body>

</html>