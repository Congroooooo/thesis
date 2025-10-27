<?php
/**
 * Strike Management Utility
 * Provides functions to check user strike status and enforce blocking rules
 */

/**
 * Check if user is blocked or has temporary restrictions
 * @param PDO $conn Database connection
 * @param int $user_id User ID to check
 * @param bool $return_array If true, returns array with details, otherwise throws exception
 * @return array|bool Array with strike info if $return_array=true, true if allowed
 * @throws Exception If user is blocked or restricted and $return_array=false
 */
function checkUserStrikeStatus($conn, $user_id, $return_array = false) {
    $stmt = $conn->prepare("SELECT status, last_strike_time, pre_order_strikes FROM account WHERE id = ?");
    $stmt->execute([$user_id]);
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $result = [
        'allowed' => true,
        'blocked' => false,
        'temporary_block' => false,
        'message' => '',
        'strikes' => 0,
        'remaining_cooldown' => 0
    ];
    
    if ($userRow) {
        $result['strikes'] = (int)$userRow['pre_order_strikes'];
        
        // Check if account is inactive (could be due to 3 strikes or other reasons)
        if ($userRow['status'] === 'inactive') {
            $result['allowed'] = false;
            $result['blocked'] = true;
            
            if ($result['strikes'] >= 3) {
                $result['message'] = 'Your account has been deactivated due to 3 strikes from unclaimed orders. Please contact admin to reactivate your account.';
            } else {
                $result['message'] = 'Your account is currently inactive. Please contact admin.';
            }
            
            if (!$return_array) {
                throw new Exception($result['message']);
            }
            return $result;
        }
        
        // Check temporary cooldown after recent strike
        if ($userRow['last_strike_time']) {
            $lastStrike = strtotime($userRow['last_strike_time']);
            $now = time();
            $cooldownPeriod = 300; // 5 minutes in seconds
            
            if ($now - $lastStrike < $cooldownPeriod) {
                $remainingTime = $cooldownPeriod - ($now - $lastStrike);
                $minutes = floor($remainingTime / 60);
                $seconds = $remainingTime % 60;
                
                $result['allowed'] = false;
                $result['temporary_block'] = true;
                $result['remaining_cooldown'] = $remainingTime;
                $result['message'] = "You recently received a strike for an unclaimed order. Please wait {$minutes}m {$seconds}s before placing another order.";
                
                if (!$return_array) {
                    throw new Exception($result['message']);
                }
                return $result;
            } else {
                // Cooldown expired, clear the last_strike_time
                $clearStmt = $conn->prepare("UPDATE account SET last_strike_time = NULL WHERE id = ?");
                $clearStmt->execute([$user_id]);
            }
        }
    }
    
    return $return_array ? $result : true;
}

/**
 * Get user-friendly strike status message
 * @param PDO $conn Database connection
 * @param int $user_id User ID
 * @return string Status message for display
 */
function getUserStrikeStatusMessage($conn, $user_id) {
    try {
        $status = checkUserStrikeStatus($conn, $user_id, true);
        
        if ($status['blocked']) {
            return "❌ Account Blocked - " . $status['message'];
        } elseif ($status['temporary_block']) {
            return "⏳ Temporary Restriction - " . $status['message'];
        } elseif ($status['strikes'] > 0) {
            $remaining = 3 - $status['strikes'];
            return "⚠️ Warning: You have {$status['strikes']} strike(s). {$remaining} more strike(s) will result in account blocking.";
        } else {
            return "✅ Account in good standing";
        }
    } catch (Exception $e) {
        return "❌ " . $e->getMessage();
    }
}

/**
 * Check if user can place orders (quick version for UI checks)
 * @param PDO $conn Database connection  
 * @param int $user_id User ID
 * @return bool True if user can place orders
 */
function canUserPlaceOrders($conn, $user_id) {
    try {
        checkUserStrikeStatus($conn, $user_id, false);
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>