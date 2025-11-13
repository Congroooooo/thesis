<?php
session_start();

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Pages/login.php?redirect=../PAMO_PAGES/dashboard.php");
    exit();
}
$role = strtoupper($_SESSION['role_category'] ?? '');
$programAbbr = strtoupper($_SESSION['program_abbreviation'] ?? '');
if (!($role === 'EMPLOYEE' && $programAbbr === 'PAMO')) {
    header("Location: ../Pages/home.php");
    exit();
}
$reportType = isset($_GET['type']) ? $_GET['type'] : 'sales';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

require_once '../Includes/connection.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAMO - Reports</title>
    <link rel="stylesheet" href="../PAMO CSS/styles.css">
    <link rel="stylesheet" href="../PAMO CSS/reports.css">
    <link rel="stylesheet" href="../CSS/logout-modal.css">
    <script src="../Javascript/logout-modal.js"></script>
    <script src="../PAMO JS/reports.js"></script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>

<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="reports-header">
                <h2 class="reports-title">Reports</h2>
                <div class="search-bar" style="display: <?php echo $reportType == 'monthly' ? 'none' : 'flex'; ?>;">
                    <i class="material-icons">search</i>
                    <input type="text" id="searchInput" placeholder="Search reports...">
                </div>
                <div class="date-filters" style="display: <?php echo $reportType == 'monthly' ? 'none' : 'flex'; ?>;">
                    <label for="startDate" class="date-label">From</label>
                    <input type="date" id="startDate" placeholder="Start Date">
                    <label for="endDate" class="date-label">To</label>
                    <input type="date" id="endDate" placeholder="End Date">
                    <button onclick="clearDates()" class="clear-date-btn" title="Clear Dates">
                        <i class="material-icons">clear</i>
                    </button>
                    <button id="applyFiltersBtn" type="button" class="apply-filters-btn" title="Apply Filters">
                        <i class="material-icons">filter_list</i>
                        Apply
                    </button>
                    <div class="quick-filters">
                        <button onclick="applyDailyFilter()" class="daily-filter-btn" title="Daily">
                            <i class="material-icons">today</i>
                            Daily
                        </button>
                        <button onclick="applyMonthlyFilter()" class="monthly-filter-btn" title="Monthly">
                            <i class="material-icons">date_range</i>
                            Monthly
                        </button>
                    </div>
                </div>
                <button onclick="exportReport()" class="export-btn" title="Export to Excel">
                    <i class="material-icons">table_view</i>
                    Export
                </button>
            </header>

            <div class="reports-content">
                <div class="report-filters">
                    <h3>Report Filters</h3>
                    <select id="reportType" onchange="changeReportType()">
                        <option value="sales"<?php if($reportType=='sales') echo ' selected'; ?>>Sales Report</option>
                        <option value="monthly"<?php if($reportType=='monthly') echo ' selected'; ?>>Monthly Inventory</option>
                        <option value="audit"<?php if($reportType=='audit') echo ' selected'; ?>>Audit Trail</option>
                    </select>
                </div>

                <!-- Sales Report Table -->
                <div class="report-table-responsive">
                    <div id="salesReport" class="report-table" style="display: <?php echo $reportType == 'sales' ? 'block' : 'none'; ?>;"></div>
                </div>

                <!-- Monthly Inventory Report -->
                <div class="report-table-responsive">
                    <div id="monthlyReport" class="report-table" style="display: <?php echo $reportType == 'monthly' ? 'block' : 'none'; ?>;">
                        <?php if ($reportType == 'monthly'): ?>
                            <?php
                            // Include monthly inventory functionality
                            require_once '../Includes/MonthlyInventoryManager.php';
                            $monthlyInventory = new MonthlyInventoryManager($conn);
                            
                            // Get year and month from URL parameters or use current
                            $selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
                            $selectedMonth = isset($_GET['month']) ? intval($_GET['month']) : date('n');
                            $monthName = date('F Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear));
                            
                            // Get period information
                            $stmt = $conn->prepare("SELECT * FROM monthly_inventory_periods WHERE year = ? AND month = ?");
                            $stmt->execute([$selectedYear, $selectedMonth]);
                            $periodInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            // Auto-create period ONLY if it's the current month or follows the last existing period
                            if (!$periodInfo) {
                                $currentYear = date('Y');
                                $currentMonth = date('n');
                                $isCurrentOrPast = ($selectedYear < $currentYear) || 
                                                  ($selectedYear == $currentYear && $selectedMonth <= $currentMonth);
                                
                                // Get the latest existing period
                                $stmt = $conn->query("
                                    SELECT year, month 
                                    FROM monthly_inventory_periods 
                                    ORDER BY year DESC, month DESC 
                                    LIMIT 1
                                ");
                                $latestPeriod = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                // Calculate if selected month is the next month after latest period
                                $isNextMonth = false;
                                if ($latestPeriod) {
                                    $latestDate = mktime(0, 0, 0, $latestPeriod['month'], 1, $latestPeriod['year']);
                                    $selectedDate = mktime(0, 0, 0, $selectedMonth, 1, $selectedYear);
                                    $nextMonthDate = strtotime('+1 month', $latestDate);
                                    $isNextMonth = ($selectedDate == $nextMonthDate);
                                }
                                
                                // Only create if it's current/past AND it's the next month in sequence
                                if ($isCurrentOrPast && $isNextMonth) {
                                    try {
                                        require_once '../Includes/period_helper.php';
                                        $periodId = createMonthlyPeriod($conn, $selectedYear, $selectedMonth);
                                        $stmt = $conn->prepare("SELECT * FROM monthly_inventory_periods WHERE id = ?");
                                        $stmt->execute([$periodId]);
                                        $periodInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                                    } catch (Exception $e) {
                                        // Period creation failed, will show no data message
                                    }
                                }
                            }
                            
                            if ($periodInfo) {
                                $periodId = $periodInfo['id'];
                                
                                // Get summary statistics
                                $stmt = $conn->prepare("
                                    SELECT 
                                        COUNT(*) as total_items,
                                        SUM(beginning_quantity) as total_beginning,
                                        SUM(new_delivery_total) as total_deliveries,
                                        SUM(sales_total) as total_sales,
                                        SUM(removals_total) as total_removals,
                                        SUM(ending_quantity) as total_ending
                                    FROM monthly_inventory_snapshots 
                                    WHERE period_id = ?
                                ");
                                $stmt->execute([$periodId]);
                                $summary = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                // Get detailed inventory data
                                $stmt = $conn->prepare("
                                    SELECT 
                                        mis.item_code,
                                        i.item_name,
                                        i.category,
                                        mis.beginning_quantity,
                                        mis.new_delivery_total,
                                        mis.sales_total,
                                        mis.removals_total,
                                        mis.ending_quantity,
                                        i.price,
                                        (mis.ending_quantity * i.price) as ending_value
                                    FROM monthly_inventory_snapshots mis
                                    JOIN inventory i ON mis.item_code = i.item_code
                                    WHERE mis.period_id = ?
                                    ORDER BY i.category, i.item_name
                                ");
                                $stmt->execute([$periodId]);
                                $inventoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                // Get removal details for this period
                                $stmt = $conn->prepare("
                                    SELECT 
                                        ir.id,
                                        ir.item_code,
                                        i.item_name,
                                        ir.quantity_removed,
                                        ir.removal_reason,
                                        ir.removed_at,
                                        ir.pullout_order_number
                                    FROM inventory_removals ir
                                    JOIN inventory i ON ir.item_code COLLATE utf8mb4_unicode_ci = i.item_code COLLATE utf8mb4_unicode_ci
                                    WHERE ir.period_id = ?
                                    ORDER BY ir.removed_at DESC
                                ");
                                $stmt->execute([$periodId]);
                                $removalDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            } else {
                                $summary = null;
                                $inventoryData = [];
                            }
                            ?>
                            
                            <!-- Monthly Report Header -->
                            <div class="monthly-report-header" style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                    <h3 style="margin: 0; font-family: 'Inter', 'Segoe UI', Arial, sans-serif; font-weight: 700; font-size: 1.3rem;">
                                        <span style="display: inline-flex; align-items: center; gap: 10px;">
                                            <span class="material-icons" style="font-size: 28px;">calendar_month</span>
                                            Monthly Inventory Report - <?php echo $monthName; ?>
                                        </span>
                                    </h3>
                                    <div style="display: flex; gap: 10px;">
                                        <select id="monthSelector" onchange="changeMonth()">
                                            <?php for($m = 1; $m <= 12; $m++): ?>
                                                <option value="<?php echo $m; ?>" <?php echo $m == $selectedMonth ? 'selected' : ''; ?>>
                                                    <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                        <select id="yearSelector" onchange="changeMonth()">
                                            <?php 
                                            // Get available years from database
                                            $stmt = $conn->query("SELECT DISTINCT year FROM monthly_inventory_periods ORDER BY year ASC");
                                            $availableYears = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                            
                                            // If no years exist, show current year
                                            if (empty($availableYears)) {
                                                $availableYears = [date('Y')];
                                            }
                                            
                                            foreach($availableYears as $y): 
                                            ?>
                                                <option value="<?php echo $y; ?>" <?php echo $y == $selectedYear ? 'selected' : ''; ?>>
                                                    <?php echo $y; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <?php if ($periodInfo && $summary): ?>
                                <!-- Summary Cards -->
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px;">
                                    <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; text-align: center;">
                                        <div style="font-size: 24px; font-weight: 600; color: #1976d2; margin-bottom: 5px;">
                                            <?php echo number_format((int)($summary['total_items'] ?? 0)); ?>
                                        </div>
                                        <div style="font-size: 12px; color: #666; text-transform: uppercase; font-weight: 600;">
                                            Total Items
                                        </div>
                                    </div>
                                    <div style="background: #f3e5f5; padding: 15px; border-radius: 8px; text-align: center;">
                                        <div style="font-size: 24px; font-weight: 600; color: #7b1fa2; margin-bottom: 5px;">
                                            <?php echo number_format((int)($summary['total_beginning'] ?? 0)); ?>
                                        </div>
                                        <div style="font-size: 12px; color: #666; text-transform: uppercase; font-weight: 600;">
                                            Beginning Stock
                                        </div>
                                    </div>
                                    <div style="background: #e8f5e8; padding: 15px; border-radius: 8px; text-align: center;">
                                        <div style="font-size: 24px; font-weight: 600; color: #388e3c; margin-bottom: 5px;">
                                            <?php echo number_format((int)($summary['total_deliveries'] ?? 0)); ?>
                                        </div>
                                        <div style="font-size: 12px; color: #666; text-transform: uppercase; font-weight: 600;">
                                            Deliveries
                                        </div>
                                    </div>
                                    <div style="background: #fff3e0; padding: 15px; border-radius: 8px; text-align: center;">
                                        <div style="font-size: 24px; font-weight: 600; color: #f57c00; margin-bottom: 5px;">
                                            <?php echo number_format((int)($summary['total_sales'] ?? 0)); ?>
                                        </div>
                                        <div style="font-size: 12px; color: #666; text-transform: uppercase; font-weight: 600;">
                                            Sales
                                        </div>
                                    </div>
                                    <div style="background: #ffebee; padding: 15px; border-radius: 8px; text-align: center;">
                                        <div style="font-size: 24px; font-weight: 600; color: #d32f2f; margin-bottom: 5px;">
                                            <?php echo number_format((int)($summary['total_removals'] ?? 0)); ?>
                                        </div>
                                        <div style="font-size: 12px; color: #666; text-transform: uppercase; font-weight: 600;">
                                            Removed
                                        </div>
                                    </div>
                                    <div style="background: #fce4ec; padding: 15px; border-radius: 8px; text-align: center;">
                                        <div style="font-size: 24px; font-weight: 600; color: #c2185b; margin-bottom: 5px;">
                                            <?php echo number_format((int)($summary['total_ending'] ?? 0)); ?>
                                        </div>
                                        <div style="font-size: 12px; color: #666; text-transform: uppercase; font-weight: 600;">
                                            Ending Stock
                                        </div>
                                    </div>
                                </div>
                                
                                <div style="background: #f8f9fa; padding: 15px; border-radius: 6px;">
                                    <strong>Period:</strong> <?php echo date('F j, Y', strtotime($periodInfo['period_start'])); ?> - <?php echo date('F j, Y', strtotime($periodInfo['period_end'])); ?>
                                    <span style="margin-left: 15px;">
                                        <strong>Status:</strong> <?php echo $periodInfo['is_closed'] ? 'Closed' : 'Active'; ?>
                                    </span>
                                </div>
                                <?php else: ?>
                                <div style="background: #fff3cd; padding: 15px; border-radius: 6px; color: #856404;">
                                    <strong>No data available</strong> for <?php echo $monthName; ?>. 
                                    The monthly period may not have been created yet or no inventory activity occurred.
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($periodInfo && !empty($inventoryData)): ?>
                            <!-- Detailed Inventory Table -->
                            <div style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr>
                                            <th style="text-align: left;">Item Code</th>
                                            <th style="text-align: left;">Item Name</th>
                                            <th style="text-align: left;">Category</th>
                                            <th style="text-align: center;">Beginning</th>
                                            <th style="text-align: center;">Deliveries</th>
                                            <th style="text-align: center;">Sales</th>
                                            <th style="text-align: center;">Removed</th>
                                            <th style="text-align: center;">Ending</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $currentCategory = '';
                                        foreach ($inventoryData as $item): 
                                            if ($currentCategory != $item['category']):
                                                $currentCategory = $item['category'];
                                        ?>
                                            <tr style="background: #e9ecef;">
                                                <td colspan="8" style="padding: 10px 12px; font-weight: 600; color: #495057;">
                                                    <?php echo htmlspecialchars($item['category']); ?>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                        <tr style="border-bottom: 1px solid #dee2e6;">
                                            <td style="padding: 12px; color: #495057;"><?php echo htmlspecialchars($item['item_code']); ?></td>
                                            <td style="padding: 12px; color: #495057;"><?php echo htmlspecialchars($item['item_name']); ?></td>
                                            <td style="padding: 12px; color: #6c757d; font-size: 14px;"><?php echo htmlspecialchars($item['category']); ?></td>
                                            <td style="padding: 12px; text-align: center; color: #495057;"><?php echo number_format($item['beginning_quantity']); ?></td>
                                            <td style="padding: 12px; text-align: center; color: #28a745;"><?php echo number_format($item['new_delivery_total']); ?></td>
                                            <td style="padding: 12px; text-align: center; color: #dc3545;"><?php echo number_format($item['sales_total']); ?></td>
                                            <td style="padding: 12px; text-align: center; color: #d32f2f; font-weight: <?php echo $item['removals_total'] > 0 ? '600' : 'normal'; ?>;">
                                                <?php echo number_format($item['removals_total']); ?>
                                            </td>
                                            <td style="padding: 12px; text-align: center; font-weight: 600; color: <?php echo $item['ending_quantity'] > 0 ? '#28a745' : '#dc3545'; ?>;">
                                                <?php echo number_format($item['ending_quantity']); ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Removal Details Section -->
                            <?php if (!empty($removalDetails)): ?>
                            <div style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-top: 20px;">
                                <h4 style="margin: 0 0 15px 0; color: #d32f2f; font-family: 'Inter', 'Segoe UI', Arial, sans-serif; font-weight: 600; font-size: 1.1rem; display: flex; align-items: center; gap: 10px;">
                                    <span class="material-icons" style="font-size: 24px;">remove_circle_outline</span>
                                    Removed Items Details
                                </h4>
                                <div style="overflow-x: auto;">
                                    <table style="width: 100%; border-collapse: collapse;">
                                        <thead>
                                            <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Item Code</th>
                                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Item Name</th>
                                                <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Quantity</th>
                                                <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Status</th>
                                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Reason</th>
                                                <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Date Removed</th>
                                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Pullout Order #</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($removalDetails as $removal): ?>
                                            <tr style="border-bottom: 1px solid #dee2e6;">
                                                <td style="padding: 12px; color: #495057; font-family: 'Courier New', monospace;">
                                                    <?php echo htmlspecialchars($removal['item_code']); ?>
                                                </td>
                                                <td style="padding: 12px; color: #495057;">
                                                    <?php echo htmlspecialchars($removal['item_name']); ?>
                                                </td>
                                                <td style="padding: 12px; text-align: center; color: #d32f2f; font-weight: 600;">
                                                    <?php echo number_format($removal['quantity_removed']); ?>
                                                </td>
                                                <td style="padding: 12px; text-align: center;">
                                                    <span style="display: inline-block; padding: 4px 12px; background: #ffebee; color: #d32f2f; border-radius: 12px; font-size: 13px; font-weight: 600;">
                                                        Removed
                                                    </span>
                                                </td>
                                                <td style="padding: 12px; color: #6c757d;">
                                                    <?php echo htmlspecialchars($removal['removal_reason'] ?? 'N/A'); ?>
                                                </td>
                                                <td style="padding: 12px; text-align: center; color: #6c757d; font-size: 14px;">
                                                    <?php echo date('M j, Y g:i A', strtotime($removal['removed_at'])); ?>
                                                </td>
                                                <td style="padding: 12px; color: #495057; font-family: 'Courier New', monospace; font-size: 13px;">
                                                    <?php echo htmlspecialchars($removal['pullout_order_number']); ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div style="margin-top: 15px; padding: 12px; background: #f8f9fa; border-radius: 6px; display: flex; align-items: center; gap: 10px;">
                                    <span class="material-icons" style="color: #d32f2f;">info</span>
                                    <span style="color: #495057; font-size: 14px;">
                                        <strong>Total Items Removed:</strong> <?php echo count($removalDetails); ?> 
                                        | 
                                        <strong>Total Quantity:</strong> <?php echo number_format(array_sum(array_column($removalDetails, 'quantity_removed'))); ?> units
                                    </span>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php endif; ?>
                            
                        <?php endif; ?>
                        
                        <script>
                        function changeMonth() {
                            const month = document.getElementById('monthSelector').value;
                            const year = document.getElementById('yearSelector').value;
                            window.location.href = '?type=monthly&month=' + month + '&year=' + year;
                        }
                        </script>
                    </div>
                </div>

                <!-- Audit Trail Table -->
                <div class="report-table-responsive">
                    <div id="auditReport" class="report-table" style="display: <?php echo $reportType == 'audit' ? 'block' : 'none'; ?>;"></div>
                </div>
            </div>
        </main>
    </div>
    <script>
    function changeReportType() {
        const reportType = document.getElementById("reportType").value;
        window.location.href = "?type=" + reportType + "&page=1";
    }
    
    function exportReport() {
        const reportType = document.getElementById("reportType").value;
        
        if (reportType === 'monthly') {
            exportMonthlyReport();
        } else {
            exportToExcel(); // Use existing export function for other reports
        }
    }
    </script>
</body>

</html>