<?php
/**
 * Simple Functionality Verification
 * Quick test to verify core monthly inventory functions work
 */

header('Content-Type: application/json');

try {
    require_once '../Includes/connection.php';
    require_once '../Includes/MonthlyInventoryManager.php';
    
    $monthlyInventory = new MonthlyInventoryManager($conn);
    
    // Test 1: Get current period
    $periodId = $monthlyInventory->getCurrentPeriodId();
    
    // Test 2: Check database tables exist
    $tables = ['monthly_inventory_periods', 'monthly_inventory_snapshots', 'delivery_records', 'monthly_sales_records'];
    $tablesExist = true;
    
    foreach ($tables as $table) {
        try {
            $stmt = $conn->query("SELECT COUNT(*) FROM $table LIMIT 1");
        } catch (Exception $e) {
            $tablesExist = false;
            break;
        }
    }
    
    // Test 3: Check if inventory table has new columns
    $stmt = $conn->query("DESCRIBE inventory");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $hasNewColumns = in_array('current_month_deliveries', $columns) && 
                    in_array('inventory_period_id', $columns) &&
                    in_array('last_month_end_quantity', $columns);
    
    // Test 4: Transaction test
    $transactionWorking = true;
    try {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $conn->beginTransaction();
        $conn->rollBack();
    } catch (Exception $e) {
        $transactionWorking = false;
    }
    
    echo json_encode([
        'success' => true,
        'system_status' => 'operational',
        'current_period_id' => $periodId,
        'tables_exist' => $tablesExist,
        'inventory_columns_updated' => $hasNewColumns,
        'transactions_working' => $transactionWorking,
        'monthly_inventory_ready' => $tablesExist && $hasNewColumns && $transactionWorking && $periodId > 0,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'system_status' => 'error'
    ]);
}
?>