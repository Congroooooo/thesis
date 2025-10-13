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
        background-color: #cfcecf;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        margin-right: 0px;
        font-size: 20px;
        display: flex;
        flex-direction: column;
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        z-index: 100;
    }
    .notif-badge {
        background: #d32f2f;
        color: #fff;
        border-radius: 50%;
        padding: 2px 8px;
        font-size: 0.9em;
        margin-left: 8px;
        vertical-align: middle;
        display: inline-block;
        min-width: 24px;
        text-align: center;
        font-weight: bold;
        position: relative;
        top: -2px;
    }
    .nav-links li.active {
        background: #534f54 !important;
        color: yellow !important;
        border-radius: 12px;
    }
    .nav-links li.active i,
    .nav-links li.active .notif-badge {
        color: yellow !important;
    }
    .nav-links li.active .notif-badge {
        background: #d32f2f !important;
    }
    .nav-links li .notif-badge {
        margin-left: 8px;
    }
    .logo-area {
        background: #cfcecf;
        padding: 18px 0 10px 0;
        margin-bottom: 8px;
    }

    .logo {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 16px;
    }

    .logo img {
        width: 56px;
        height: 56px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        background: #fff;
    }

    .logo h2 {
        font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
        font-weight: 800;
        font-size: 2.1rem;
        color: yellow;
        letter-spacing: 2px;
        margin: 0;
        text-shadow: 0 1px 0 #fff, 0 2px 8px rgba(0,0,0,0.04);
    }

    .nav-links li {
        position: relative;
        padding-left: 18px;
        transition: background 0.2s;
    }

    .nav-links li .active-bar {
        display: none;
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        width: 6px;
        background: #534f54;
        border-radius: 6px 0 0 6px;
    }

    .nav-links li.active .active-bar {
        display: block;
    }

    .nav-links li:hover {
        background: #f0f4fa;
    }

    .logout-btn.improved-logout {
        background: linear-gradient(90deg, #b02a37 60%, #dc3545 100%);
        color: #fff;
        border: 1.5px solid #dc3545;
        border-radius: 32px;
        font-size: 1.1rem;
        font-weight: 700;
        padding: 14px 0;
        box-shadow: 0 2px 12px rgba(0,114,188,0.08);
        transition: background 0.2s, color 0.2s, box-shadow 0.2s;
        margin-top: 10px;
        margin-bottom: 0;
        letter-spacing: 0.5px;
    }

    .logout-btn.improved-logout:hover {
        background: linear-gradient(90deg, #dc3545 60%, #b02a37 100%);
        color: #fff;
        box-shadow: 0 4px 18px rgba(220,53,69,0.2);
        border-color: #b02a37;
    }

    .logout-btn.improved-logout i {
        font-size: 24px;
        color: #fff;
        margin-right: 2px;
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