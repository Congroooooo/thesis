<?php
session_start();
require_once '../Includes/connection.php';
require_once '../Includes/cashier_session_manager.php';

header('Content-Type: application/json');

// Verify PAMO access
$role = strtoupper($_SESSION['role_category'] ?? '');
$programAbbr = strtoupper($_SESSION['program_abbreviation'] ?? '');

if (!($role === 'EMPLOYEE' && $programAbbr === 'PAMO')) {
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized - PAMO access required'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cashier_name = trim($_POST['cashier_name'] ?? '');
    
    // Validate input
    if (empty($cashier_name)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Cashier name is required'
        ]);
        exit;
    }
    
    if (strlen($cashier_name) < 2) {
        echo json_encode([
            'success' => false, 
            'message' => 'Cashier name must be at least 2 characters'
        ]);
        exit;
    }
    
    if (strlen($cashier_name) > 255) {
        echo json_encode([
            'success' => false, 
            'message' => 'Cashier name is too long (max 255 characters)'
        ]);
        exit;
    }
    
    // Save the cashier name to database
    $user_id = $_SESSION['user_id'];
    $success = setTodayCashier($conn, $cashier_name, $user_id);
    
    if ($success) {
        $_SESSION['cashier_set_today'] = true;
        $_SESSION['cashier_name_today'] = $cashier_name;
        
        echo json_encode([
            'success' => true, 
            'message' => 'Cashier name saved successfully',
            'cashier_name' => $cashier_name,
            'date' => date('Y-m-d')
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to save cashier name. Please try again.'
        ]);
    }
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Invalid request method. Use POST.'
    ]);
}
