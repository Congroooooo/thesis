<?php
session_start();

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Pages/login.php?redirect=../PAMO PAGES/content-edit.php");
    exit();
}
$role = strtoupper($_SESSION['role_category'] ?? '');
$programAbbr = strtoupper($_SESSION['program_abbreviation'] ?? '');
if (!($role === 'EMPLOYEE' && $programAbbr === 'PAMO')) {
    header("Location: ../Pages/home.php");
    exit();
}
$reportType = isset($_GET['type']) ? $_GET['type'] : 'inventory';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
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
                <div class="search-bar">
                    <i class="material-icons">search</i>
                    <input type="text" id="searchInput" placeholder="Search reports...">
                </div>
                <div class="date-filters">
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
                <button onclick="exportToExcel()" class="export-btn" title="Export to Excel">
                    <i class="material-icons">table_view</i>
                    Export
                </button>
            </header>

            <div class="reports-content">
                <div class="report-filters">
                    <h3>Report Filters</h3>
                    <select id="reportType" onchange="changeReportType()">
                        <option value="inventory"<?php if($reportType=='inventory') echo ' selected'; ?>>Inventory Report</option>
                        <option value="sales"<?php if($reportType=='sales') echo ' selected'; ?>>Sales Report</option>
                        <option value="audit"<?php if($reportType=='audit') echo ' selected'; ?>>Audit Trail</option>
                    </select>
                </div>

                <!-- Inventory Report Table -->
                <div class="report-table-responsive">
                    <div id="inventoryReport" class="report-table" style="display: <?php echo $reportType == 'inventory' ? 'block' : 'none'; ?>;"></div>
                </div>

                <!-- Sales Report Table -->
                <div class="report-table-responsive">
                    <div id="salesReport" class="report-table" style="display: <?php echo $reportType == 'sales' ? 'block' : 'none'; ?>;"></div>
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
    </script>
</body>

</html>