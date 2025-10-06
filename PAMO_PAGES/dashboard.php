<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

session_start();

// Use absolute paths for better compatibility
$base_dir = dirname(__DIR__); // Go up one directory from PAMO_PAGES
$config_file = __DIR__ . '/includes/config_functions.php';
$connection_file = $base_dir . '/includes/connection.php';
$loader_file = __DIR__ . '/includes/pamo_loader.php';

// Alternative connection file paths to try
$connection_alternatives = [
    $base_dir . '/includes/connection.php',
    $base_dir . '/Includes/connection.php',  // Capital I
    dirname(__DIR__) . '/includes/connection.php',
    __DIR__ . '/../includes/connection.php'
];

// Check config file
if (!file_exists($config_file)) {
    die("Error: Config functions file not found at: " . $config_file . " (Real path: " . realpath(dirname($config_file)) . ")");
}

// Try different paths for connection file
$connection_found = false;
foreach ($connection_alternatives as $alt_path) {
    if (file_exists($alt_path)) {
        $connection_file = $alt_path;
        $connection_found = true;
        break;
    }
}

if (!$connection_found) {
    die("Error: Connection file not found. Tried paths: " . implode(', ', $connection_alternatives) . " | Base dir: " . $base_dir);
}

// Check loader file
if (!file_exists($loader_file)) {
    die("Error: Loader file not found at: " . $loader_file . " (Real path: " . realpath(dirname($loader_file)) . ")");
}

include $config_file;
include $connection_file;
include $loader_file;

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


// Initialize default values
$total_items = 0;
$pending_orders = 0;
$low_stock_items = 0;

try {
    // Check if database connection is working
    if (!$conn) {
        die("Database connection failed");
    }

    // Test database connection
    $test_query = "SELECT 1";
    $conn->query($test_query);

    // Get total items
    $total_items_query = "SELECT SUM(actual_quantity) as total FROM inventory";
    $total_result = $conn->query($total_items_query);
    if ($total_result) {
        $row = $total_result->fetch(PDO::FETCH_ASSOC);
        $total_items = $row['total'] ?? 0;
    }

    // Get pending orders
    $pending_orders_query = "SELECT COUNT(*) as pending FROM orders WHERE status = 'pending'";
    $pending_result = $conn->query($pending_orders_query);
    if ($pending_result) {
        $row = $pending_result->fetch(PDO::FETCH_ASSOC);
        $pending_orders = $row['pending'] ?? 0;
    }

    // Get low stock items
    $low_stock_threshold = getLowStockThreshold($conn);
    $low_stock_query = "SELECT COUNT(*) as low_stock 
                        FROM inventory 
                        WHERE actual_quantity <= ? 
                        AND actual_quantity > 0";
    $low_stock_stmt = $conn->prepare($low_stock_query);
    $low_stock_stmt->execute([$low_stock_threshold]);
    $row = $low_stock_stmt->fetch(PDO::FETCH_ASSOC);
    $low_stock_items = $row['low_stock'] ?? 0;

} catch (Exception $e) {
    // Log the error but continue with default values
    error_log("Dashboard query error: " . $e->getMessage());
    // Optionally display error for debugging (remove in production)
    echo "<!-- Debug: Database error: " . htmlspecialchars($e->getMessage()) . " -->";
}

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAMO - Dashboard</title>
    <link rel="stylesheet" href="../PAMO CSS/styles.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Anton+SC&family=Smooch+Sans:wght@100..900&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../PAMO CSS/dashboard.css">
    <link rel="stylesheet" href="../CSS/logout-modal.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../Javascript/logout-modal.js"></script>
    <script src="../PAMO JS/dashboard.js"></script>
</head>

<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="dashboard">
                <div class="stats-cards">
                    <div class="card" onclick="window.location.href='inventory.php'">
                        <div class="card-content">
                            <h3>Total Items</h3>
                            <h2><?php echo number_format($total_items); ?></h2>
                            <p>Total inventory quantity</p>
                        </div>
                        <i class="material-icons">inventory</i>
                    </div>
                    <div class="card" onclick="window.location.href='orders.php?status=pending'">
                        <div class="card-content">
                            <h3>Pending Orders</h3>
                            <h2><?php echo number_format($pending_orders); ?></h2>
                            <p>Awaiting processing</p>
                        </div>
                        <i class="material-icons">shopping_cart</i>
                    </div>
                    <div class="card" onclick="redirectToLowStock()">
                        <div class="card-content">
                            <h3>Low Stock Items</h3>
                            <h2><?php echo number_format($low_stock_items); ?></h2>
                            <p>Items need restock</p>
                        </div>
                        <i class="material-icons">warning</i>
                    </div>
                </div>

                <div class="analytics-section">
                    <div class="analytics-card sales-analytics">
                        <div class="sales-filters" style="position: absolute; top: 18px; left: 18px; z-index: 2; width: auto; background: none; box-shadow: none; padding: 0; margin-bottom: 0;">
                            <label>
                                Category:
                                <select id="salesCategoryFilter"><option value="">All</option></select>
                            </label>
                            <label id="subcategoryLabel" style="display: none;">
                                Subcategory:
                                <select id="salesSubcategoryFilter"><option value="">All</option></select>
                            </label>
                            <label>
                                Period:
                                <select id="salesPeriodFilter">
                                    <option value="daily">Daily</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                            </label>
                        </div>
                        <div class="section-title" style="margin-top: 48px;">Sales Analytics</div>
                        <h4>Sales Performance</h4>
                        <canvas id="salesLineChart" height="320"></canvas>
                    </div>
                    <div class="analytics-card inventory-analytics">
                        <div class="section-title">Inventory Analytics</div>
                        <h4>Inventory Stock Levels</h4>
                        <canvas id="stockPieChart"></canvas>
                    </div>
                </div>
                <div class="recent-activities">
                    <div class="activities-header">
                        <h3>Recent Activities</h3>
                        <button onclick="clearActivities()" class="clear-btn">
                            <i class="material-icons">clear_all</i>
                            Clear Activities
                        </button>
                    </div>
                    <div class="activity-list">
                        <?php
                        try {
                            // Get current page number
                            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                            $items_per_page = 10;
                            $offset = ($page - 1) * $items_per_page;

                            // Initialize default values
                            $total_activities = 0;
                            $total_pages = 1;
                            $activities_result = null;

                            // Check if activities table exists
                            $table_check = $conn->query("SHOW TABLES LIKE 'activities'");
                            if ($table_check->rowCount() > 0) {
                                // Get total number of activities
                                $total_query = "SELECT COUNT(*) as total FROM activities WHERE DATE(timestamp) = CURDATE()";
                                $total_result = $conn->query($total_query);
                                if ($total_result) {
                                    $row = $total_result->fetch(PDO::FETCH_ASSOC);
                                    $total_activities = $row['total'] ?? 0;
                                }
                                $total_pages = max(1, ceil($total_activities / $items_per_page));

                                // Modified query with pagination
                                $activities_query = "SELECT * FROM activities 
                                WHERE DATE(timestamp) = CURDATE()
                                ORDER BY id DESC, timestamp DESC
                                LIMIT :offset, :items_per_page";
                                
                                $stmt = $conn->prepare($activities_query);
                                $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
                                $stmt->bindParam(':items_per_page', $items_per_page, PDO::PARAM_INT);
                                $stmt->execute();
                                $activities_result = $stmt;
                            }
                        } catch (Exception $e) {
                            error_log("Activities query error: " . $e->getMessage());
                            echo "<!-- Debug: Activities error: " . htmlspecialchars($e->getMessage()) . " -->";
                            $total_activities = 0;
                            $total_pages = 1;
                            $activities_result = null;
                        }

                        if ($activities_result && $activities_result->rowCount() > 0) {
                            while ($activity = $activities_result->fetch(PDO::FETCH_ASSOC)) {
                                $icon = '';
                                switch ($activity['action_type']) {
                                    case 'price_update':
                                        $icon = 'edit';
                                        break;
                                    case 'quantity_update':
                                        $icon = 'add_circle';
                                        break;
                                    case 'new_item':
                                        $icon = 'add_box';
                                        break;
                                    case 'sale':
                                        $icon = 'point_of_sale';
                                        break;
                                    case 'order_accepted':
                                        $icon = 'check_circle';
                                        break;
                                    case 'edit_image':
                                        $icon = 'image';
                                        break;
                                    default:
                                        $icon = 'info';
                                }
                                ?>
                                <div class="activity-item">
                                    <i class="material-icons"><?php echo $icon; ?></i>
                                    <div class="activity-details">
                                        <p><?php echo htmlspecialchars($activity['description']); ?></p>
                                        <span class="activity-time">
                                            <?php echo date('M d, Y h:i A', strtotime($activity['timestamp'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php
                            }
                        } else {
                            echo "<p class='no-activities'>No recent activities</p>";
                        }
                        ?>
                    </div>
                    
                    <!-- Debug information (remove after fixing) -->
                    <!-- PHP execution completed successfully -->
                    
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>" class="page-link">
                                <i class="material-icons">chevron_left</i>
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>" class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>" class="page-link">
                                <i class="material-icons">chevron_right</i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Debug script to check page loading -->
    <script>
        console.log('Dashboard page loaded successfully');
        
        // Check if main content is visible
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded');
            const mainContent = document.querySelector('.main-content');
            if (mainContent) {
                console.log('Main content found:', mainContent);
            } else {
                console.error('Main content not found!');
            }
            
            // Check if loader is still visible
            const loader = document.getElementById('pamo-loader');
            if (loader) {
                console.log('Loader element found:', loader.style.display);
            }
        });
    </script>
</body>
</html>