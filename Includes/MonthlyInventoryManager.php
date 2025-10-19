<?php

class MonthlyInventoryManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }

    public function getCurrentPeriodId() {
        $year = date('Y');
        $month = date('n');
        
        $stmt = $this->conn->prepare("SELECT id FROM monthly_inventory_periods WHERE year = ? AND month = ?");
        $stmt->execute([$year, $month]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result['id'];
        }
        return $this->createCurrentPeriod();
    }

    private function createCurrentPeriod() {
        $year = date('Y');
        $month = date('n');

        $inExistingTransaction = $this->conn->inTransaction();
        $startedOwnTransaction = false;
        
        if (!$inExistingTransaction) {
            $this->conn->beginTransaction();
            $startedOwnTransaction = true;
        }
        
        try {
            $periodStart = date('Y-m-01', strtotime("$year-$month-01"));
            $periodEnd = date('Y-m-t', strtotime("$year-$month-01"));

            $stmt = $this->conn->prepare("
                INSERT INTO monthly_inventory_periods (year, month, period_start, period_end)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
            ");
            $stmt->execute([$year, $month, $periodStart, $periodEnd]);
            
            $newPeriodId = $this->conn->lastInsertId();
            if ($newPeriodId == 0) {
                $stmt = $this->conn->prepare("SELECT id FROM monthly_inventory_periods WHERE year = ? AND month = ?");
                $stmt->execute([$year, $month]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $newPeriodId = $result['id'];
            }

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
    
    public function recordDelivery($deliveryOrderNumber, $itemCode, $quantity, $processedBy, $useTransaction = true) {
        $periodId = $this->getCurrentPeriodId();
        
        $startedTransaction = false;
        if ($useTransaction && !$this->conn->inTransaction()) {
            $this->conn->beginTransaction();
            $startedTransaction = true;
        }
        
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO delivery_records 
                (delivery_order_number, item_code, quantity, period_id, processed_by) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$deliveryOrderNumber, $itemCode, $quantity, $periodId, $processedBy]);

            $this->updateMonthlySnapshot($itemCode, $periodId);

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

    public function recordSale($transactionNumber, $itemCode, $quantitySold, $pricePerItem, $totalAmount, $processedBy, $useTransaction = true) {
        $periodId = $this->getCurrentPeriodId();
        
        $startedTransaction = false;
        if ($useTransaction && !$this->conn->inTransaction()) {
            $this->conn->beginTransaction();
            $startedTransaction = true;
        }
        
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO monthly_sales_records 
                (transaction_number, item_code, quantity_sold, price_per_item, total_amount, period_id, processed_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$transactionNumber, $itemCode, $quantitySold, $pricePerItem, $totalAmount, $periodId, $processedBy]);

            $this->updateMonthlySnapshot($itemCode, $periodId);

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

    private function updateMonthlySnapshot($itemCode, $periodId) {
        $stmt = $this->conn->prepare("
            SELECT 
                COALESCE((SELECT SUM(quantity) FROM delivery_records WHERE period_id = ? AND item_code = ?), 0) as total_deliveries,
                COALESCE((SELECT SUM(quantity_sold) FROM monthly_sales_records WHERE period_id = ? AND item_code = ?), 0) as total_sales
        ");
        $stmt->execute([$periodId, $itemCode, $periodId, $itemCode]);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);

        $beginningQty = $this->getBeginningQuantity($itemCode, $periodId);
        $endingQty = $beginningQty + $totals['total_deliveries'] - $totals['total_sales'];

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

    private function updateInventoryActualQuantity($itemCode) {
        $periodId = $this->getCurrentPeriodId();
        $stmt = $this->conn->prepare("
            SELECT beginning_quantity, new_delivery_total, sales_total, ending_quantity
            FROM monthly_inventory_snapshots 
            WHERE period_id = ? AND item_code = ?
        ");
        $stmt->execute([$periodId, $itemCode]);
        $snapshot = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($snapshot) {
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

    public function initializeNewItem($itemCode, $initialQuantity, $deliveryOrderNumber = null) {
        $periodId = $this->getCurrentPeriodId();

        if ($deliveryOrderNumber === null) {
            $deliveryOrderNumber = 'INITIAL-' . $itemCode . '-' . date('YmdHis');
        }

        $stmt = $this->conn->prepare("
            INSERT INTO monthly_inventory_snapshots 
            (period_id, item_code, beginning_quantity, new_delivery_total, sales_total, ending_quantity)
            VALUES (?, ?, 0, ?, 0, ?)
            ON DUPLICATE KEY UPDATE
                new_delivery_total = new_delivery_total + VALUES(new_delivery_total),
                ending_quantity = beginning_quantity + new_delivery_total - sales_total
        ");
        $stmt->execute([$periodId, $itemCode, $initialQuantity, $initialQuantity]);

        if ($initialQuantity > 0) {
            $stmt = $this->conn->prepare("
                INSERT INTO delivery_records 
                (delivery_order_number, item_code, quantity, period_id, processed_by) 
                VALUES (?, ?, ?, ?, ?)
            ");

            $processedBy = $_SESSION['user_id'] ?? 0;
            $stmt->execute([$deliveryOrderNumber, $itemCode, $initialQuantity, $periodId, $processedBy]);
        }
        
        return $this->updateInventoryActualQuantity($itemCode);
    }

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

    public function closeCurrentMonth() {
        $currentPeriodId = $this->getCurrentPeriodId();
        
        $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare("CALL CloseMonthlyPeriod(?)");
            $stmt->execute([$currentPeriodId]);

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