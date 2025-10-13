<?php
/**
 * Quick setup verification and fix for Monthly Inventory System
 */

session_start();
require_once '../Includes/connection.php';

// Ensure user is authorized (optional check)
if (!isset($_SESSION['user_id'])) {
    die("Please log in to access this script.");
}

echo "<h1>Monthly Inventory System - Setup Verification</h1>\n";

try {
    // Check if tables exist and create them if they don't
    $tables = [
        'monthly_inventory_periods',
        'monthly_inventory_snapshots', 
        'delivery_records',
        'monthly_sales_records'
    ];
    
    echo "<h2>Checking Required Tables</h2>\n";
    
    foreach ($tables as $table) {
        try {
            $stmt = $conn->query("SELECT COUNT(*) FROM $table LIMIT 1");
            echo "<p style='color: green;'>✓ Table '$table' exists</p>\n";
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ Table '$table' missing</p>\n";
            echo "<p>Please run the database migration script first.</p>\n";
        }
    }
    
    // Check if MonthlyInventoryManager class is available
    echo "<h2>Checking MonthlyInventoryManager</h2>\n";
    
    if (file_exists('../Includes/MonthlyInventoryManager.php')) {
        echo "<p style='color: green;'>✓ MonthlyInventoryManager.php exists</p>\n";
        
        require_once '../Includes/MonthlyInventoryManager.php';
        
        $monthlyInventory = new MonthlyInventoryManager($conn);
        $currentPeriodId = $monthlyInventory->getCurrentPeriodId();
        
        echo "<p style='color: green;'>✓ MonthlyInventoryManager initialized successfully</p>\n";
        echo "<p>Current Period ID: $currentPeriodId</p>\n";
        
        // Check if current period exists
        $stmt = $conn->prepare("SELECT * FROM monthly_inventory_periods WHERE id = ?");
        $stmt->execute([$currentPeriodId]);
        $period = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($period) {
            echo "<p style='color: green;'>✓ Current period found: " . date('F Y', strtotime($period['period_start'])) . "</p>\n";
        } else {
            echo "<p style='color: red;'>✗ Current period not found</p>\n";
        }
        
    } else {
        echo "<p style='color: red;'>✗ MonthlyInventoryManager.php not found</p>\n";
    }
    
    // Check inventory table for new columns
    echo "<h2>Checking Inventory Table Columns</h2>\n";
    
    $requiredColumns = [
        'current_month_deliveries',
        'current_month_sales', 
        'last_month_end_quantity',
        'inventory_period_id'
    ];
    
    try {
        $stmt = $conn->query("DESCRIBE inventory");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($requiredColumns as $col) {
            if (in_array($col, $columns)) {
                echo "<p style='color: green;'>✓ Column '$col' exists</p>\n";
            } else {
                echo "<p style='color: red;'>✗ Column '$col' missing</p>\n";
                echo "<p>Run: ALTER TABLE inventory ADD COLUMN $col INT DEFAULT NULL;</p>\n";
            }
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error checking inventory table: " . $e->getMessage() . "</p>\n";
    }
    
    // Test transaction functionality
    echo "<h2>Testing Transaction Functionality</h2>\n";
    
    try {
        // Clear any existing transactions
        if ($conn->inTransaction()) {
            $conn->rollBack();
            echo "<p style='color: orange;'>⚠ Rolled back existing transaction</p>\n";
        }
        
        $conn->beginTransaction();
        echo "<p style='color: green;'>✓ Transaction started successfully</p>\n";
        
        $conn->rollBack();
        echo "<p style='color: green;'>✓ Transaction rolled back successfully</p>\n";
        
        // Test MonthlyInventoryManager without transactions
        $conn->beginTransaction();
        
        // Test that MonthlyInventoryManager works within existing transaction
        $monthlyInventory->recordDelivery(
            'SETUP_TEST_' . time(),
            'TEST_ITEM_SETUP', 
            1,
            $_SESSION['user_id'],
            false // Don't use internal transaction
        );
        
        $conn->rollBack(); // Don't actually save
        echo "<p style='color: green;'>✓ MonthlyInventoryManager works within external transaction</p>\n";
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        echo "<p style='color: red;'>✗ Transaction test failed: " . $e->getMessage() . "</p>\n";
    }
    
    echo "<h2>Setup Status Summary</h2>\n";
    echo "<p>If all checks show green checkmarks, the system is ready to use.</p>\n";
    echo "<p>If there are any red X marks, please:</p>\n";
    echo "<ol>\n";
    echo "<li>Run the database migration script: <code>/setup/setup_monthly_inventory.php</code></li>\n";
    echo "<li>Ensure all files are uploaded correctly</li>\n";
    echo "<li>Check database permissions</li>\n";
    echo "</ol>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Fatal Error: " . $e->getMessage() . "</p>\n";
    echo "<p>File: " . $e->getFile() . "</p>\n";
    echo "<p>Line: " . $e->getLine() . "</p>\n";
}
?>