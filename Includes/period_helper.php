<?php
/**
 * Helper function to create a monthly period and initialize snapshots
 * Uses previous month's ending quantities as beginning quantities
 */
function createMonthlyPeriod($conn, $year, $month) {
    $inExistingTransaction = $conn->inTransaction();
    $startedOwnTransaction = false;
    
    if (!$inExistingTransaction) {
        $conn->beginTransaction();
        $startedOwnTransaction = true;
    }
    
    try {
        $periodStart = date('Y-m-01', strtotime("$year-$month-01"));
        $periodEnd = date('Y-m-t', strtotime("$year-$month-01"));
        
        // Create the period
        $stmt = $conn->prepare("
            INSERT INTO monthly_inventory_periods (year, month, period_start, period_end, is_closed)
            VALUES (?, ?, ?, ?, 0)
            ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
        ");
        $stmt->execute([$year, $month, $periodStart, $periodEnd]);
        
        $newPeriodId = $conn->lastInsertId();
        if ($newPeriodId == 0) {
            $stmt = $conn->prepare("SELECT id FROM monthly_inventory_periods WHERE year = ? AND month = ?");
            $stmt->execute([$year, $month]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $newPeriodId = $result['id'];
        }
        
        // Get previous month's data to use as beginning quantities
        $prevMonth = $month - 1;
        $prevYear = $year;
        if ($prevMonth < 1) {
            $prevMonth = 12;
            $prevYear--;
        }
        
        // Check if snapshots already exist for this period
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM monthly_inventory_snapshots WHERE period_id = ?");
        $stmt->execute([$newPeriodId]);
        $existingCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($existingCount == 0) {
            // Get previous period's ending quantities
            $stmt = $conn->prepare("
                SELECT mis.item_code, mis.ending_quantity
                FROM monthly_inventory_snapshots mis
                JOIN monthly_inventory_periods mip ON mis.period_id = mip.id
                WHERE mip.year = ? AND mip.month = ?
            ");
            $stmt->execute([$prevYear, $prevMonth]);
            $previousEndings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // IMPORTANT: Only create snapshots for items that existed in previous month
            // This maintains data continuity - new items added mid-month should only appear
            // in the current period when they receive their first delivery
            $insertStmt = $conn->prepare("
                INSERT INTO monthly_inventory_snapshots 
                (period_id, item_code, beginning_quantity, new_delivery_total, sales_total, ending_quantity)
                VALUES (?, ?, ?, 0, 0, ?)
            ");
            
            foreach ($previousEndings as $prev) {
                $itemCode = $prev['item_code'];
                $beginningQty = $prev['ending_quantity'];
                $insertStmt->execute([$newPeriodId, $itemCode, $beginningQty, $beginningQty]);
            }
        }
        
        if ($startedOwnTransaction) {
            $conn->commit();
        }
        
        return $newPeriodId;
        
    } catch (Exception $e) {
        if ($startedOwnTransaction) {
            $conn->rollBack();
        }
        throw new Exception("Failed to create period: " . $e->getMessage());
    }
}
?>
