<?php
if (!isset($basePath)) $basePath = '';
if (isset($_SESSION['user_id'])) {
    include_once __DIR__ . '/../../Includes/connection.php';
    $user_id = $_SESSION['user_id'];
    $query = "SELECT first_name, last_name, role_category, program_or_position FROM account WHERE id = :uid OR id_number = :uid";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':uid', $user_id);
    $stmt->execute();
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $_SESSION['role_category'] = $row['role_category'];
        $_SESSION['program_or_position'] = $row['program_or_position'];
        if (!isset($_SESSION['program_abbreviation'])) {
            $raw = trim($row['program_or_position']);
            $_SESSION['program_abbreviation'] = strtoupper($raw);
        }
    }
}
$newInquiries = 0;
try {
    include_once __DIR__ . '/../../Includes/connection.php';
    $stmtNotif = $conn->prepare("SELECT COUNT(*) FROM inquiries WHERE status = 'new'");
    $stmtNotif->execute();
    $newInquiries = $stmtNotif->fetchColumn();
} catch (Exception $e) {
    $newInquiries = 0;
}
$pendingOrdersCount = 0;
try {
    include_once __DIR__ . '/../../Includes/connection.php';
    $stmtPendingOrders = $conn->prepare("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
    $stmtPendingOrders->execute();
    $pendingOrdersCount = $stmtPendingOrders->fetchColumn();
} catch (Exception $e) {
    $pendingOrdersCount = 0;
}
?>
<nav class="sidebar">
    <div class="logo-area">
        <div class="logo">
            <img src="../Images/STI-LOGO.png" alt="PAMO Logo">
            <h2>PAMO</h2>
        </div>
    </div>
    <ul class="nav-links">
        <li <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'class="active"' : ''; ?>
            onclick="window.location.href='<?php echo $basePath; ?>dashboard.php'">
            <span class="active-bar"></span>
            <i class="material-icons">dashboard</i>Dashboard
        </li>
        <li <?php echo basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'class="active"' : ''; ?>
            onclick="window.location.href='<?php echo $basePath; ?>inventory.php'">
            <span class="active-bar"></span>
            <i class="material-icons">inventory_2</i>Inventory
        </li>
        <li <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'class="active"' : ''; ?>
            onclick="window.location.href='<?php echo $basePath; ?>orders.php'">
            <span class="active-bar"></span>
            <i class="material-icons">shopping_cart</i>Orders
            <?php if (isset($pendingOrdersCount) && $pendingOrdersCount > 0): ?>
                <span class="notif-badge"><?php echo $pendingOrdersCount; ?></span>
            <?php endif; ?>
        </li>
        <li <?php echo basename($_SERVER['PHP_SELF']) == 'pamo_preorder.php' ? 'class="active"' : ''; ?>
            onclick="window.location.href='<?php echo $basePath; ?>pamo_preorder.php'">
            <span class="active-bar"></span>
            <i class="material-icons">shopping_cart</i>Pre-Order
        </li>
        <li <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'class="active"' : ''; ?>
            onclick="window.location.href='<?php echo $basePath; ?>reports.php'">
            <span class="active-bar"></span> 
            <i class="material-icons">assessment</i>Reports
        </li>
        <?php
            $isPamo = false;
            if (isset($_SESSION['program_abbreviation']) && strtoupper($_SESSION['program_abbreviation']) === 'PAMO') {
                $isPamo = true;
            } elseif (isset($_SESSION['program_or_position']) && stripos($_SESSION['program_or_position'], 'PAMO') !== false) {
                $isPamo = true;
            }
            if ($isPamo):
        ?>
        <li <?php echo basename($_SERVER['PHP_SELF']) == 'view_inquiries.php' ? 'class="active"' : ''; ?>
            onclick="window.location.href='<?php echo $basePath; ?>view_inquiries.php'">
            <span class="active-bar"></span>
            <i class="material-icons">question_answer</i>Inquiries
            <?php if ($newInquiries > 0): ?>
                <span class="notif-badge"><?= $newInquiries ?></span>
            <?php endif; ?>
        </li>
        <?php endif; ?>
        <li <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'class="active"' : ''; ?>
            onclick="window.location.href='<?php echo $basePath; ?>settings.php'">
            <span class="active-bar"></span>
            <i class="material-icons">settings</i>Settings
        </li>
    </ul>
    <div class="user-info">
        <?php
        $initials = 'GU';
        if (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
            $query = "SELECT first_name, last_name FROM account WHERE id = :user_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $firstInitial = strtoupper(substr($row['first_name'], 0, 1));
                $lastInitial = strtoupper(substr($row['last_name'], 0, 1));
                $initials = $firstInitial . $lastInitial;
            }
        }
        ?>
        <div class="user-avatar-svg" style="width: 40px; height: 40px; border-radius: 50%; background: var(--secondary-color); display: flex; align-items: center; justify-content: center;">
            <svg width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                <circle cx="20" cy="20" r="20" fill="#3498db" />
                <text x="50%" y="55%" text-anchor="middle" fill="#fff" font-size="18" font-family="'Segoe UI', Arial, sans-serif" font-weight="bold" dy=".1em">
                    <?php echo htmlspecialchars($initials); ?>
                </text>
            </svg>
        </div>
        <div class="user-details">
            <h4>
                <?php
                if (isset($_SESSION['user_id'])) {
                    $user_id = $_SESSION['user_id'];
                    $query = "SELECT first_name, last_name, role_category, program_or_position FROM account WHERE id = :user_id";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                    $stmt->execute();
                    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        echo $row['first_name'] . ' ' . $row['last_name'];
                    } else {
                        echo 'Guest User';
                    }
                } else {
                    echo 'Guest User';
                }
                ?>
            </h4>
            <p>
                <?php
                if ($isPamo ?? false) {
                    echo 'PAMO';
                } else {
                    echo isset($_SESSION['role_category']) ? $_SESSION['role_category'] : 'No Role Assigned';
                }
                ?>
            </p>
        </div>
    </div>
    <div style="margin-top: auto; padding-bottom: 30px; width: 100%; display: flex; justify-content: center;">
        <button onclick="logout()" class="logout-btn improved-logout">
            <i class="material-icons">logout</i>
            <span>Logout</span>
        </button>
    </div>
</nav>

<style>
    .sidebar {
        width: 250px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        box-shadow: 4px 0 24px rgba(0, 114, 188, 0.08), 2px 0 8px rgba(0, 0, 0, 0.04);
        margin-right: 0px;
        font-size: 16px;
        display: flex;
        flex-direction: column;
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        z-index: 100;
        border-right: 1px solid rgba(0, 114, 188, 0.08);
    }
    .notif-badge {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: #fff;
        border-radius: 12px;
        padding: 3px 9px;
        font-size: 11px;
        margin-left: auto;
        vertical-align: middle;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 22px;
        height: 22px;
        text-align: center;
        font-weight: 700;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
        animation: pulse 2s infinite;
    }
    .nav-links li.active {
        background: linear-gradient(135deg, #0072bc 0%, #005a94 100%) !important;
        color: #ffffff !important;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 114, 188, 0.25);
        font-weight: 600;
    }
    .nav-links li.active i {
        color: #fdf005 !important;
        filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.1));
    }
    .nav-links li.active .notif-badge {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important;
        color: #ffffff !important;
    }
    .nav-links li .notif-badge {
        margin-left: auto;
    }
    .logo-area {
        background: linear-gradient(135deg, #0072bc 0%, #005a94 100%);
        padding: 20px 0 18px 0;
        margin-bottom: 16px;
        box-shadow: 0 4px 12px rgba(0, 114, 188, 0.15);
    }

    .logo {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
    }

    .logo img {
        width: 52px;
        height: 52px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        background: #fff;
        padding: 4px;
    }

    .logo h2 {
        font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
        font-weight: 800;
        font-size: 2rem;
        color: #fdf005;
        letter-spacing: 3px;
        margin: 0;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .nav-links {
        padding: 0 12px;
    }

    .nav-links li {
        position: relative;
        padding: 14px 16px;
        margin: 6px 0;
        border-radius: 12px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        gap: 14px;
        color: #475569;
        font-weight: 500;
        font-size: 15px;
    }

    .nav-links li .active-bar {
        display: none;
        position: absolute;
        left: -12px;
        top: 50%;
        transform: translateY(-50%);
        height: 60%;
        width: 4px;
        background: linear-gradient(180deg, #0072bc 0%, #005a94 100%);
        border-radius: 0 4px 4px 0;
        box-shadow: 2px 0 8px rgba(0, 114, 188, 0.3);
    }

    .nav-links li.active .active-bar {
        display: block;
    }

    .nav-links li:hover {
        background: linear-gradient(135deg, #f1f5f9 0%, #e0f2fe 100%);
        transform: translateX(4px);
        color: #0072bc;
    }

    .nav-links li:hover i {
        color: #0072bc;
        transform: scale(1.1);
    }

    .nav-links li i {
        font-size: 22px;
        transition: all 0.3s ease;
        color: #64748b;
    }

    .logout-btn.improved-logout {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: #fff;
        border: none;
        border-radius: 12px;
        font-size: 15px;
        font-weight: 600;
        padding: 14px 24px;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        margin-top: 10px;
        margin-bottom: 0;
        letter-spacing: 0.3px;
        cursor: pointer;
    }

    .logout-btn.improved-logout:hover {
        background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
        color: #fff;
        box-shadow: 0 6px 20px rgba(239, 68, 68, 0.35);
        transform: translateY(-2px);
    }

    .logout-btn.improved-logout:active {
        transform: translateY(0);
    }

    .logout-btn.improved-logout i {
        font-size: 20px;
        color: #fff;
        margin-right: 6px;
    }

    .user-info {
        padding: 16px;
        margin: 16px 12px;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .user-details h4 {
        margin: 0;
        font-size: 14px;
        font-weight: 600;
        color: #1e293b;
    }

    .user-details p {
        margin: 4px 0 0 0;
        font-size: 12px;
        color: #64748b;
        font-weight: 500;
    }

    @keyframes pulse {
        0%, 100% {
            opacity: 1;
            transform: scale(1);
        }
        50% {
            opacity: 0.9;
            transform: scale(1.05);
        }
    }
</style>

<!-- Include Font Awesome for modal icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<script>
function logout() {
    showLogoutConfirmation();
}
</script>

<!-- Real-time sidebar updates -->
<script src="../PAMO JS/sidebar-realtime.js"></script>