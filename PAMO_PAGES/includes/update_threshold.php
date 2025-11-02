<?php
session_start();

// Security headers
header('Content-Type: application/json');

// Use absolute paths for better compatibility
$base_dir = dirname(dirname(__DIR__));
$config_file = __DIR__ . '/config_functions.php';

// Try different possible paths for the connection file
$connection_alternatives = [
    $base_dir . '/includes/connection.php',
    $base_dir . '/Includes/connection.php'
];

$connection_file = null;
foreach ($connection_alternatives as $alt_path) {
    if (file_exists($alt_path)) {
        $connection_file = $alt_path;
        break;
    }
}

if (!$connection_file) {
    echo json_encode(['success' => false, 'message' => 'Database connection file not found']);
    exit;
}

require_once $config_file;
require_once $connection_file;

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check role and program
$role = strtoupper($_SESSION['role_category'] ?? '');
$programAbbr = strtoupper($_SESSION['program_abbreviation'] ?? '');
if (!($role === 'EMPLOYEE' && $programAbbr === 'PAMO')) {
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['low_stock_threshold'])) {
        echo json_encode(['success' => false, 'message' => 'Threshold value not provided']);
        exit;
    }
    
    $newThreshold = intval($input['low_stock_threshold']);
    
    if ($newThreshold <= 0) {
        echo json_encode(['success' => false, 'message' => 'Threshold must be greater than 0']);
        exit;
    }
    
    try {
        // Get old threshold for logging
        $oldThreshold = getLowStockThreshold($conn);
        
        // Check if system_config table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'system_config'");
        if ($tableCheck->rowCount() == 0) {
            // Create table if it doesn't exist
            $createTable = "CREATE TABLE IF NOT EXISTS system_config (
                id INT AUTO_INCREMENT PRIMARY KEY,
                config_key VARCHAR(50) UNIQUE NOT NULL,
                config_value VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            $conn->exec($createTable);
        }
        
        // Update threshold
        if (updateLowStockThreshold($conn, $newThreshold)) {
            // Log activity
            $user_id = $_SESSION['user_id'] ?? null;
            $desc = "Low stock threshold changed from $oldThreshold to $newThreshold.";
            logActivity($conn, 'Low Stock Update', $desc, $user_id);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Low stock threshold updated successfully!',
                'new_threshold' => $newThreshold
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update threshold']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
