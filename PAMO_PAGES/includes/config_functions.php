<?php
function getLowStockThreshold($conn) {

    if ($conn instanceof PDO) {
        $stmt = $conn->prepare("SELECT config_value FROM system_config WHERE config_key = 'low_stock_threshold'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? intval($result['config_value']) : 10;
    }
    // If it's a MySQLi connection
    elseif ($conn instanceof mysqli) {
        $sql = "SELECT config_value FROM system_config WHERE config_key = 'low_stock_threshold'";
        $result = mysqli_query($conn, $sql);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            return intval($row['config_value']);
        }
        return 10;
    }
    // Unknown connection type
    return 10;
}

function updateLowStockThreshold($conn, $newValue) {
    if ($conn instanceof PDO) {
        try {
            // Use UPSERT (INSERT ... ON DUPLICATE KEY UPDATE) for better reliability
            $stmt = $conn->prepare("
                INSERT INTO system_config (config_key, config_value) 
                VALUES ('low_stock_threshold', :value)
                ON DUPLICATE KEY UPDATE 
                config_value = VALUES(config_value),
                updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->bindParam(':value', $newValue, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            // Fallback to the original method if UPSERT fails
            try {
                // First try to update existing record
                $stmt = $conn->prepare("UPDATE system_config SET config_value = :value WHERE config_key = 'low_stock_threshold'");
                $stmt->bindParam(':value', $newValue, PDO::PARAM_INT);
                $stmt->execute();
                
                // If no rows were affected, insert the record
                if ($stmt->rowCount() == 0) {
                    $stmt = $conn->prepare("INSERT INTO system_config (config_key, config_value) VALUES ('low_stock_threshold', :value)");
                    $stmt->bindParam(':value', $newValue, PDO::PARAM_INT);
                    return $stmt->execute();
                }
                return true;
            } catch (Exception $e2) {
                error_log("Failed to update low stock threshold: " . $e2->getMessage());
                return false;
            }
        }
    } elseif ($conn instanceof mysqli) {
        // First try to update existing record 
        $sql = "UPDATE system_config SET config_value = ? WHERE config_key = 'low_stock_threshold'";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $newValue);
            mysqli_stmt_execute($stmt);
            $affected_rows = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);
            
            // If no rows were affected, insert the record
            if ($affected_rows == 0) {
                $sql = "INSERT INTO system_config (config_key, config_value) VALUES ('low_stock_threshold', ?)";
                $stmt = mysqli_prepare($conn, $sql);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "i", $newValue);
                    $result = mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                    return $result;
                }
                return false;
            }
            return true;
        }
        return false;
    }
    return false;
}

function logActivity($conn, $action_type, $description, $user_id = null) {
    if ($conn instanceof PDO) {
        $stmt = $conn->prepare("INSERT INTO activities (action_type, description, user_id, timestamp) VALUES (:action_type, :description, :user_id, NOW())");
        $stmt->bindParam(':action_type', $action_type);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':user_id', $user_id);
        return $stmt->execute();
    } elseif ($conn instanceof mysqli) {
        $stmt = mysqli_prepare($conn, "INSERT INTO activities (action_type, description, user_id, timestamp) VALUES (?, ?, ?, NOW())");
        mysqli_stmt_bind_param($stmt, "ssi", $action_type, $description, $user_id);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }
    return false;
} 