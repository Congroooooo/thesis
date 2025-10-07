<?php
/**
 * Admin utility to manage user strikes and unblock accounts
 */

require_once __DIR__ . '/../Includes/connection.php';

/**
 * Unblock a user account and reset strikes
 * @param PDO $conn Database connection
 * @param int $user_id User ID to unblock
 * @param string $admin_reason Reason for unblocking (for logging)
 * @return array Result with success status and message
 */
function unblockUserAccount($conn, $user_id, $admin_reason = 'Admin intervention') {
    try {
        $conn->beginTransaction();
        
        // Get current user info
        $stmt = $conn->prepare('SELECT first_name, last_name, pre_order_strikes, is_strike FROM account WHERE id = ?');
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // Reset strikes and unblock
        $stmt = $conn->prepare('UPDATE account SET pre_order_strikes = 0, is_strike = 0, last_strike_time = NULL WHERE id = ?');
        $stmt->execute([$user_id]);
        
        // Log the admin action
        $activity_description = "Admin unblocked user: {$user['first_name']} {$user['last_name']} (ID: $user_id). Previous strikes: {$user['pre_order_strikes']}. Reason: $admin_reason";
        $stmt = $conn->prepare('INSERT INTO activities (action_type, description, user_id, timestamp) VALUES (?, ?, ?, NOW())');
        $stmt->execute(['Admin_Unblock', $activity_description, null]);
        
        $conn->commit();
        
        return [
            'success' => true,
            'message' => "Successfully unblocked {$user['first_name']} {$user['last_name']} and reset strikes to 0"
        ];
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        return [
            'success' => false,
            'message' => 'Error unblocking user: ' . $e->getMessage()
        ];
    }
}

/**
 * Reduce strikes for a user (partial strike removal)
 * @param PDO $conn Database connection
 * @param int $user_id User ID
 * @param int $strikes_to_remove Number of strikes to remove
 * @param string $admin_reason Reason for strike reduction
 * @return array Result with success status and message
 */
function reduceUserStrikes($conn, $user_id, $strikes_to_remove = 1, $admin_reason = 'Admin intervention') {
    try {
        $conn->beginTransaction();
        
        // Get current user info
        $stmt = $conn->prepare('SELECT first_name, last_name, pre_order_strikes, is_strike FROM account WHERE id = ?');
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        $old_strikes = $user['pre_order_strikes'];
        $new_strikes = max(0, $old_strikes - $strikes_to_remove);
        
        // Update strikes
        $stmt = $conn->prepare('UPDATE account SET pre_order_strikes = ?, is_strike = ?, last_strike_time = NULL WHERE id = ?');
        $is_strike = ($new_strikes >= 3) ? 1 : 0;
        $stmt->execute([$new_strikes, $is_strike, $user_id]);
        
        // Log the admin action
        $activity_description = "Admin reduced strikes for user: {$user['first_name']} {$user['last_name']} (ID: $user_id). Strikes: $old_strikes → $new_strikes. Reason: $admin_reason";
        $stmt = $conn->prepare('INSERT INTO activities (action_type, description, user_id, timestamp) VALUES (?, ?, ?, NOW())');
        $stmt->execute(['Admin_Strike_Reduction', $activity_description, null]);
        
        $conn->commit();
        
        $status = $new_strikes >= 3 ? 'blocked' : 'active';
        return [
            'success' => true,
            'message' => "Successfully updated strikes for {$user['first_name']} {$user['last_name']}: $old_strikes → $new_strikes (Status: $status)"
        ];
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        return [
            'success' => false,
            'message' => 'Error reducing strikes: ' . $e->getMessage()
        ];
    }
}

// Command line interface for testing
if (isset($argv) && count($argv) > 1) {
    $action = $argv[1] ?? '';
    $user_id = intval($argv[2] ?? 0);
    
    switch ($action) {
        case 'unblock':
            if ($user_id > 0) {
                $reason = $argv[3] ?? 'Command line admin action';
                $result = unblockUserAccount($conn, $user_id, $reason);
                echo ($result['success'] ? '✅ ' : '❌ ') . $result['message'] . "\n";
            } else {
                echo "Usage: php admin_strike_management.php unblock <user_id> [reason]\n";
            }
            break;
            
        case 'reduce':
            if ($user_id > 0) {
                $strikes_to_remove = intval($argv[3] ?? 1);
                $reason = $argv[4] ?? 'Command line admin action';
                $result = reduceUserStrikes($conn, $user_id, $strikes_to_remove, $reason);
                echo ($result['success'] ? '✅ ' : '❌ ') . $result['message'] . "\n";
            } else {
                echo "Usage: php admin_strike_management.php reduce <user_id> [strikes_to_remove] [reason]\n";
            }
            break;
            
        case 'status':
            if ($user_id > 0) {
                require_once __DIR__ . '/strike_management.php';
                $stmt = $conn->prepare('SELECT first_name, last_name FROM account WHERE id = ?');
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                if ($user) {
                    echo "User: {$user['first_name']} {$user['last_name']}\n";
                    echo getUserStrikeStatusMessage($conn, $user_id) . "\n";
                } else {
                    echo "❌ User not found\n";
                }
            } else {
                echo "Usage: php admin_strike_management.php status <user_id>\n";
            }
            break;
            
        default:
            echo "Available commands:\n";
            echo "  unblock <user_id> [reason] - Completely unblock user and reset strikes\n";
            echo "  reduce <user_id> [strikes] [reason] - Reduce strikes by specified amount\n";
            echo "  status <user_id> - Show user strike status\n";
    }
}
?>