<?php
session_start();

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Pages/login.php?redirect=../PAMO_PAGES/monthly_periods.php");
    exit();
}
$role = strtoupper($_SESSION['role_category'] ?? '');
$programAbbr = strtoupper($_SESSION['program_abbreviation'] ?? '');
if (!($role === 'EMPLOYEE' && $programAbbr === 'PAMO')) {
    header("Location: ../Pages/home.php");
    exit();
}

require_once '../Includes/connection.php';
require_once '../Includes/MonthlyInventoryManager.php';

$monthlyInventory = new MonthlyInventoryManager($conn);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'close_month':
                try {
                    $monthlyInventory->closeCurrentMonth();
                    $success_message = "Monthly period has been closed successfully and next month initialized.";
                } catch (Exception $e) {
                    $error_message = "Error closing month: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get available periods
$periods = $monthlyInventory->getAvailablePeriods();
$currentPeriodId = $monthlyInventory->getCurrentPeriodId();

include 'includes/pamo_loader.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAMO - Monthly Inventory Periods</title>
    <link rel="stylesheet" href="../PAMO CSS/styles.css">
    <link rel="stylesheet" href="../PAMO CSS/inventory.css">
    <link rel="stylesheet" href="../CSS/logout-modal.css">
    <script src="../Javascript/logout-modal.js"></script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        .period-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }
        
        .period-card.current {
            border-left-color: #28a745;
            background: #f8fff9;
        }
        
        .period-card.closed {
            border-left-color: #6c757d;
            background: #f8f9fa;
        }
        
        .period-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .period-title {
            font-size: 18px;
            font-weight: 600;
            color: #495057;
        }
        
        .period-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-closed {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .period-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .info-value {
            font-size: 16px;
            color: #495057;
            font-weight: 500;
        }
        
        .actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border: 1px solid transparent;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        
        .close-month-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .close-month-warning h4 {
            color: #856404;
            margin-bottom: 10px;
        }
        
        .close-month-warning p {
            color: #856404;
            margin-bottom: 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="page-header">
                <h2>Monthly Inventory Periods</h2>
                <p>Manage monthly inventory periods and month-end processing</p>
            </header>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">check_circle</i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">error</i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="close-month-warning">
                <h4><i class="material-icons" style="vertical-align: middle; margin-right: 8px;">warning</i>Month-End Processing</h4>
                <p>Closing the current month will finalize all inventory calculations for this period and create a new period for next month. Beginning quantities for the new month will be set to the ending quantities of the current month.</p>
            </div>

            <div class="periods-list">
                <?php foreach ($periods as $period): ?>
                    <?php 
                    $isCurrent = ($period['id'] == $currentPeriodId);
                    $isClosed = $period['is_closed'];
                    $periodClass = $isCurrent ? 'current' : ($isClosed ? 'closed' : '');
                    ?>
                    <div class="period-card <?php echo $periodClass; ?>">
                        <div class="period-header">
                            <h3 class="period-title">
                                <?php echo date('F Y', strtotime($period['period_start'])); ?>
                                <?php if ($isCurrent): ?>
                                    <i class="material-icons" style="color: #28a745; vertical-align: middle; margin-left: 8px;">schedule</i>
                                <?php endif; ?>
                            </h3>
                            <span class="period-status <?php echo $isClosed ? 'status-closed' : 'status-active'; ?>">
                                <?php echo $isClosed ? 'Closed' : ($isCurrent ? 'Current' : 'Active'); ?>
                            </span>
                        </div>
                        
                        <div class="period-info">
                            <div class="info-item">
                                <span class="info-label">Period Start</span>
                                <span class="info-value"><?php echo date('M d, Y', strtotime($period['period_start'])); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Period End</span>
                                <span class="info-value"><?php echo date('M d, Y', strtotime($period['period_end'])); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Status</span>
                                <span class="info-value"><?php echo $isClosed ? 'Closed' : 'Active'; ?></span>
                            </div>
                            <?php if ($isClosed && $period['closed_at']): ?>
                            <div class="info-item">
                                <span class="info-label">Closed Date</span>
                                <span class="info-value"><?php echo date('M d, Y H:i', strtotime($period['closed_at'])); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="actions">
                            <a href="reports.php?type=monthly&year=<?php echo $period['year']; ?>&month=<?php echo $period['month']; ?>" class="btn btn-primary">
                                <i class="material-icons">assessment</i>
                                View Report
                            </a>
                            
                            <?php if ($isCurrent && !$isClosed): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to close this month? This action cannot be undone.');">
                                    <input type="hidden" name="action" value="close_month">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="material-icons">lock</i>
                                        Close Month
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
</body>

</html>