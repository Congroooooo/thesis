<?php
function getLowStockThreshold($conn) {
    // If it's a PDO connection
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
        $stmt = $conn->prepare("UPDATE system_config SET config_value = :value WHERE config_key = 'low_stock_threshold'");
        $stmt->bindParam(':value', $newValue, PDO::PARAM_INT);
        return $stmt->execute();
    } elseif ($conn instanceof mysqli) {
        $sql = "UPDATE system_config SET config_value = ? WHERE config_key = 'low_stock_threshold'";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $newValue);
            $result = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $result;
        }
        return false;
    }
    return false;
}

function logActivity($conn, $action_type, $description, $user_id = null) {
    // Set timezone to Philippines (adjust this to your local timezone)
    date_default_timezone_set('Asia/Manila');
    $timestamp = date('Y-m-d H:i:s');
    
    if ($conn instanceof PDO) {
        $stmt = $conn->prepare("INSERT INTO activities (action_type, description, user_id, timestamp) VALUES (:action_type, :description, :user_id, :timestamp)");
        $stmt->bindParam(':action_type', $action_type);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':timestamp', $timestamp);
        return $stmt->execute();
    } elseif ($conn instanceof mysqli) {
        $stmt = mysqli_prepare($conn, "INSERT INTO activities (action_type, description, user_id, timestamp) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssis", $action_type, $description, $user_id, $timestamp);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }
    return false;
} 