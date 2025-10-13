<?php
/**
 * Monthly Inventory Manager
 * Handles monthly inventory period management and calculations
 */

class MonthlyInventoryManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Get or create current month's inventory period
     */
    public function getCurrentPeriodId() {
        // First try to get existing period without using the stored function
        $year = date('Y');
        $month = date('n');
        
        $stmt = $this->conn->prepare("SELECT id FROM monthly_inventory_periods WHERE year = ? AND month = ?");
        $stmt->execute([$year, $month]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result['id'];
        }
        
        // If no period exists, create it manually to avoid transaction conflicts
        return $this->createCurrentPeriod();
    }
    
    /**
     * Create current month's period manually if function fails
     */
    private function createCurrentPeriod() {
        $year = date('Y');
        $month = date('n');
        
        // Check if we need to manage our own transaction
        $inExistingTransaction = $this->conn->inTransaction();
        $startedOwnTransaction = false;
        
        if (!$inExistingTransaction) {
            $this->conn->beginTransaction();
            $startedOwnTransaction = true;
        }
        
        try {
            // Calculate period dates
            $periodStart = date('Y-m-01', strtotime("$year-$month-01"));
            $periodEnd = date('Y-m-t', strtotime("$year-$month-01"));
            
            // Insert new period
            $stmt = $this->conn->prepare("
                INSERT INTO monthly_inventory_periods (year, month, period_start, period_end)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
            ");
            $stmt->execute([$year, $month, $periodStart, $periodEnd]);
            
            $newPeriodId = $this->conn->lastInsertId();
            if ($newPeriodId == 0) {
                // Period already exists, get its ID
                $stmt = $this->conn->prepare("SELECT id FROM monthly_inventory_periods WHERE year = ? AND month = ?");
                $stmt->execute([$year, $month]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $newPeriodId = $result['id'];
            }
            
            // Initialize snapshots for existing inventory items that don't have snapshots for this period
            $stmt = $this->conn->prepare("
                INSERT INTO monthly_inventory_snapshots (period_id, item_code, beginning_quantity, new_delivery_total, sales_total, ending_quantity)
                SELECT 
                    ? as period_id,
                    i.item_code,
                    COALESCE(i.actual_quantity, 0) as beginning_quantity,
                    0 as new_delivery_total,
                    0 as sales_total,
                    COALESCE(i.actual_quantity, 0) as ending_quantity
                FROM inventory i
                LEFT JOIN monthly_inventory_snapshots mis ON mis.item_code = i.item_code AND mis.period_id = ?
                WHERE mis.id IS NULL
            ");
            $stmt->execute([$newPeriodId, $newPeriodId]);
            
            // Update inventory table
            $stmt = $this->conn->prepare("
                UPDATE inventory 
                SET 
                    inventory_period_id = ?,
                    current_month_deliveries = COALESCE(current_month_deliveries, 0),
                    current_month_sales = COALESCE(current_month_sales, 0),
                    last_month_end_quantity = COALESCE(last_month_end_quantity, actual_quantity)
                WHERE inventory_period_id IS NULL OR inventory_period_id != ?
            ");
            $stmt->execute([$newPeriodId, $newPeriodId]);
            
            if ($startedOwnTransaction) {
                $this->conn->commit();
            }
            
            return $newPeriodId;
            
        } catch (Exception $e) {
            if ($startedOwnTransaction) {
                $this->conn->rollBack();
            }
            throw new Exception("Failed to create period: " . $e->getMessage());
        }
    }
    
    /**
     * Get beginning quantity for an item in current month
     */
    public function getBeginningQuantity($itemCode, $periodId = null) {
        if ($periodId === null) {
            $periodId = $this->getCurrentPeriodId();
        }
        
        $stmt = $this->conn->prepare("
            SELECT beginning_quantity 
            FROM monthly_inventory_snapshots 
            WHERE period_id = ? AND item_code = ?
        ");
        $stmt->execute([$periodId, $itemCode]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? (int)$result['beginning_quantity'] : 0;
    }
    
    /**
     * Record a delivery for an item
     */
    public function recordDelivery($deliveryOrderNumber, $itemCode, $quantity, $processedBy, $useTransaction = true) {
        $periodId = $this->getCurrentPeriodId();
        
        $startedTransaction = false;
        if ($useTransaction && !$this->conn->inTransaction()) {
            $this->conn->beginTransaction();
            $startedTransaction = true;
        }
        
        try {
            // Insert delivery record
            $stmt = $this->conn->prepare("
                INSERT INTO delivery_records 
                (delivery_order_number, item_code, quantity, period_id, processed_by) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$deliveryOrderNumber, $itemCode, $quantity, $periodId, $processedBy]);
            
            // Update monthly snapshot
            $this->updateMonthlySnapshot($itemCode, $periodId);
            
            // Update main inventory table
            $this->updateInventoryActualQuantity($itemCode);
            
            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->commit();
            }
            return true;
        } catch (Exception $e) {
            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw new Exception("Failed to record delivery: " . $e->getMessage());
        }
    }
    
    /**
     * Record a sale for an item
     */
    public function recordSale($transactionNumber, $itemCode, $quantitySold, $pricePerItem, $totalAmount, $processedBy, $useTransaction = true) {
        $periodId = $this->getCurrentPeriodId();
        
        $startedTransaction = false;
        if ($useTransaction && !$this->conn->inTransaction()) {
            $this->conn->beginTransaction();
            $startedTransaction = true;
        }
        
        try {
            // Insert sales record
            $stmt = $this->conn->prepare("
                INSERT INTO monthly_sales_records 
                (transaction_number, item_code, quantity_sold, price_per_item, total_amount, period_id, processed_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$transactionNumber, $itemCode, $quantitySold, $pricePerItem, $totalAmount, $periodId, $processedBy]);
            
            // Update monthly snapshot
            $this->updateMonthlySnapshot($itemCode, $periodId);
            
            // Update main inventory table
            $this->updateInventoryActualQuantity($itemCode);
            
            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->commit();
            }
            return true;
        } catch (Exception $e) {
            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw new Exception("Failed to record sale: " . $e->getMessage());
        }
    }
    
    /**
     * Update monthly snapshot calculations
     */
    private function updateMonthlySnapshot($itemCode, $periodId) {
        // Get totals for the current period
        $stmt = $this->conn->prepare("
            SELECT 
                COALESCE(SUM(dr.quantity), 0) as total_deliveries,
                COALESCE(SUM(msr.quantity_sold), 0) as total_sales
            FROM monthly_inventory_periods mip
            LEFT JOIN delivery_records dr ON dr.period_id = mip.id AND dr.item_code = ?
            LEFT JOIN monthly_sales_records msr ON msr.period_id = mip.id AND msr.item_code = ?
            WHERE mip.id = ?
        ");
        $stmt->execute([$itemCode, $itemCode, $periodId]);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get beginning quantity
        $beginningQty = $this->getBeginningQuantity($itemCode, $periodId);
        $endingQty = $beginningQty + $totals['total_deliveries'] - $totals['total_sales'];
        
        // Update snapshot
        $stmt = $this->conn->prepare("
            UPDATE monthly_inventory_snapshots 
            SET 
                new_delivery_total = ?,
                sales_total = ?,
                ending_quantity = ?,
                updated_at = NOW()
            WHERE period_id = ? AND item_code = ?
        ");
        $stmt->execute([
            $totals['total_deliveries'],
            $totals['total_sales'], 
            $endingQty,
            $periodId, 
            $itemCode
        ]);
        
        // If no snapshot exists, create one
        if ($stmt->rowCount() == 0) {
            $stmt = $this->conn->prepare("
                INSERT INTO monthly_inventory_snapshots 
                (period_id, item_code, beginning_quantity, new_delivery_total, sales_total, ending_quantity)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $periodId, 
                $itemCode, 
                $beginningQty, 
                $totals['total_deliveries'],
                $totals['total_sales'], 
                $endingQty
            ]);
        }
    }
    
    /**
     * Update main inventory table with current month calculations
     */
    private function updateInventoryActualQuantity($itemCode) {
        $periodId = $this->getCurrentPeriodId();
        
        // Get current month's snapshot
        $stmt = $this->conn->prepare("
            SELECT beginning_quantity, new_delivery_total, sales_total, ending_quantity
            FROM monthly_inventory_snapshots 
            WHERE period_id = ? AND item_code = ?
        ");
        $stmt->execute([$periodId, $itemCode]);
        $snapshot = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($snapshot) {
            // Update inventory table with new calculations
            $stmt = $this->conn->prepare("
                UPDATE inventory 
                SET 
                    beginning_quantity = ?,
                    new_delivery = ?,
                    actual_quantity = ?,
                    current_month_deliveries = ?,
                    current_month_sales = ?,
                    inventory_period_id = ?
                WHERE item_code = ?
            ");
            $stmt->execute([
                $snapshot['beginning_quantity'],
                $snapshot['new_delivery_total'],
                $snapshot['ending_quantity'],
                $snapshot['new_delivery_total'],
                $snapshot['sales_total'],
                $periodId,
                $itemCode
            ]);
        }
    }
    
    /**
     * Initialize a new item in the current period
     */
    public function initializeNewItem($itemCode, $initialQuantity) {
        $periodId = $this->getCurrentPeriodId();
        
        // Create snapshot for new item
        $stmt = $this->conn->prepare("
            INSERT INTO monthly_inventory_snapshots 
            (period_id, item_code, beginning_quantity, new_delivery_total, sales_total, ending_quantity)
            VALUES (?, ?, 0, ?, 0, ?)
            ON DUPLICATE KEY UPDATE
                new_delivery_total = new_delivery_total + VALUES(new_delivery_total),
                ending_quantity = beginning_quantity + new_delivery_total - sales_total
        ");
        $stmt->execute([$periodId, $itemCode, $initialQuantity, $initialQuantity]);
        
        return $this->updateInventoryActualQuantity($itemCode);
    }
    
    /**
     * Get monthly inventory report data
     */
    public function getMonthlyReport($year = null, $month = null) {
        if ($year === null) $year = date('Y');
        if ($month === null) $month = date('n');
        
        $stmt = $this->conn->prepare("
            SELECT 
                mip.year,
                mip.month,
                mip.period_start,
                mip.period_end,
                mip.is_closed,
                mis.item_code,
                i.item_name,
                i.category,
                mis.beginning_quantity,
                mis.new_delivery_total,
                mis.sales_total,
                mis.ending_quantity
            FROM monthly_inventory_periods mip
            JOIN monthly_inventory_snapshots mis ON mip.id = mis.period_id
            JOIN inventory i ON mis.item_code = i.item_code
            WHERE mip.year = ? AND mip.month = ?
            ORDER BY i.item_name, mis.item_code
        ");
        $stmt->execute([$year, $month]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Close current month and prepare for next month
     */
    public function closeCurrentMonth() {
        $currentPeriodId = $this->getCurrentPeriodId();
        
        $this->conn->beginTransaction();
        try {
            // Close current period using stored procedure
            $stmt = $this->conn->prepare("CALL CloseMonthlyPeriod(?)");
            $stmt->execute([$currentPeriodId]);
            
            // Initialize next month
            $nextMonth = date('n') + 1;
            $nextYear = date('Y');
            if ($nextMonth > 12) {
                $nextMonth = 1;
                $nextYear++;
            }
            
            $stmt = $this->conn->prepare("CALL InitializeMonthlyPeriod(?, ?)");
            $stmt->execute([$nextYear, $nextMonth]);
            
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw new Exception("Failed to close monthly period: " . $e->getMessage());
        }
    }
    
    /**
     * Get available periods for reporting
     */
    public function getAvailablePeriods() {
        $stmt = $this->conn->prepare("
            SELECT id, year, month, period_start, period_end, is_closed
            FROM monthly_inventory_periods 
            ORDER BY year DESC, month DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}