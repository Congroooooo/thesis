<?php
/**
 * Inventory Update Notifier
 * Centralizes inventory update notifications across the system
 * Triggers real-time UI updates whenever inventory changes
 * 
 * Usage: Call triggerInventoryUpdate() after any inventory modification
 */

if (!function_exists('triggerInventoryUpdate')) {
    /**
     * Trigger an inventory update notification
     * Updates a timestamp that polling clients check for changes
     * 
     * @param PDO $conn Database connection
     * @param string $source Source of the update (e.g., 'order_completion', 'exchange', 'restock')
     * @param string $details Additional details about the update
     * @return bool Success status
     */
    function triggerInventoryUpdate($conn, $source = 'unknown', $details = '') {
        try {
            // Use a simple key-value settings table or create an update log
            // For now, we'll use a file-based approach for simplicity and reliability
            
            $updateData = [
                'timestamp' => date('Y-m-d H:i:s'),
                'microtime' => microtime(true),
                'source' => $source,
                'details' => $details
            ];
            
            $updateFile = __DIR__ . '/../.inventory_update_signal';
            file_put_contents($updateFile, json_encode($updateData));
            
            // Also log to database for audit trail (optional)
            try {
                $stmt = $conn->prepare("
                    INSERT INTO inventory_updates_log 
                    (update_source, update_details, updated_at) 
                    VALUES (?, ?, NOW())
                ");
                $stmt->execute([$source, $details]);
            } catch (Exception $e) {
                // Table might not exist, ignore silently
                // This is optional logging, don't fail the main operation
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Inventory update notification failed: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('getLastInventoryUpdate')) {
    /**
     * Get the timestamp of the last inventory update
     * 
     * @return array|null Update data or null if no updates
     */
    function getLastInventoryUpdate() {
        try {
            $updateFile = __DIR__ . '/../.inventory_update_signal';
            
            if (!file_exists($updateFile)) {
                return null;
            }
            
            $content = file_get_contents($updateFile);
            return json_decode($content, true);
            
        } catch (Exception $e) {
            error_log("Failed to get last inventory update: " . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('hasInventoryUpdatedSince')) {
    /**
     * Check if inventory has been updated since a given timestamp
     * 
     * @param float $clientTimestamp Client's last check timestamp (microtime)
     * @return bool True if inventory has been updated
     */
    function hasInventoryUpdatedSince($clientTimestamp) {
        $lastUpdate = getLastInventoryUpdate();
        
        if (!$lastUpdate) {
            return false;
        }
        
        return ($lastUpdate['microtime'] > $clientTimestamp);
    }
}
