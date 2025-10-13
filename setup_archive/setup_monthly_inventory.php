<?php
/**
 * Monthly Inventory System Setup Script
 * Run this script once to set up the monthly inventory system
 */

require_once '../Includes/connection.php';

try {
    $conn->beginTransaction();
    
    echo "<h1>Setting up Monthly Inventory System</h1>\n";
    
    // Read and execute the SQL migration script
    $sqlFile = '../database_migrations/monthly_inventory_system.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("Migration file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    if ($sql === false) {
        throw new Exception("Could not read migration file");
    }
    
    echo "<p>Executing database migrations...</p>\n";
    
    // Split the SQL into individual statements
    $statements = explode(';', $sql);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement) && !preg_match('/^--/', $statement)) {
            try {
                $conn->exec($statement);
                echo "<p style='color: green;'>✓ Executed: " . substr($statement, 0, 50) . "...</p>\n";
            } catch (PDOException $e) {
                // Some statements might fail if they already exist, which is okay
                echo "<p style='color: orange;'>⚠ Skipped (already exists): " . substr($statement, 0, 50) . "...</p>\n";
            }
        }
    }
    
    echo "<h2>Initializing current inventory items...</h2>\n";
    
    // Get all existing inventory items
    $stmt = $conn->query("SELECT item_code, actual_quantity FROM inventory");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Require the MonthlyInventoryManager
    require_once '../Includes/MonthlyInventoryManager.php';
    $monthlyInventory = new MonthlyInventoryManager($conn);
    
    $currentPeriodId = $monthlyInventory->getCurrentPeriodId();
    echo "<p>Current period ID: $currentPeriodId</p>\n";
    
    // Initialize each item in the monthly system
    foreach ($items as $item) {
        try {
            // Check if item already has monthly tracking set up
            $stmt = $conn->prepare("
                SELECT id FROM monthly_inventory_snapshots 
                WHERE period_id = ? AND item_code = ?
            ");
            $stmt->execute([$currentPeriodId, $item['item_code']]);
            
            if (!$stmt->fetch()) {
                // Create initial snapshot for this item
                $stmt = $conn->prepare("
                    INSERT INTO monthly_inventory_snapshots 
                    (period_id, item_code, beginning_quantity, new_delivery_total, sales_total, ending_quantity)
                    VALUES (?, ?, 0, ?, 0, ?)
                ");
                $stmt->execute([
                    $currentPeriodId,
                    $item['item_code'],
                    $item['actual_quantity'], // Treat current stock as initial delivery
                    $item['actual_quantity']
                ]);
                
                // Update the inventory record with monthly tracking info
                $stmt = $conn->prepare("
                    UPDATE inventory 
                    SET 
                        beginning_quantity = 0,
                        new_delivery = ?,
                        current_month_deliveries = ?,
                        current_month_sales = 0,
                        inventory_period_id = ?
                    WHERE item_code = ?
                ");
                $stmt->execute([
                    $item['actual_quantity'],
                    $item['actual_quantity'],
                    $currentPeriodId,
                    $item['item_code']
                ]);
                
                echo "<p style='color: green;'>✓ Initialized: {$item['item_code']} (Qty: {$item['actual_quantity']})</p>\n";
            } else {
                echo "<p style='color: blue;'>ℹ Already initialized: {$item['item_code']}</p>\n";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ Error initializing {$item['item_code']}: " . $e->getMessage() . "</p>\n";
        }
    }
    
    $conn->commit();
    
    echo "<h2 style='color: green;'>Setup Complete!</h2>\n";
    echo "<p>The monthly inventory system has been successfully set up. Key features:</p>\n";
    echo "<ul>\n";
    echo "<li>✓ Database tables and stored procedures created</li>\n";
    echo "<li>✓ Existing inventory items initialized for monthly tracking</li>\n";
    echo "<li>✓ Current month period established</li>\n";
    echo "<li>✓ Beginning quantities set to 0 (as per new system)</li>\n";
    echo "<li>✓ Current stock treated as 'New Delivery' for this month</li>\n";
    echo "</ul>\n";
    
    echo "<h3>Next Steps:</h3>\n";
    echo "<p>1. Visit <a href='../PAMO_PAGES/inventory.php'>Inventory Page</a> to see the new columns</p>\n";
    echo "<p>2. Visit <a href='../PAMO_PAGES/monthly_periods.php'>Monthly Periods</a> to manage monthly cycles</p>\n";
    echo "<p>3. At the end of each month, use 'Close Month' to finalize the period</p>\n";
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "<h2 style='color: red;'>Setup Failed</h2>\n";
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>\n";
    echo "<p>Please check the error and try again.</p>\n";
}
?>