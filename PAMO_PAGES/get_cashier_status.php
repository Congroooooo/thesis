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

// Allow checking cashier for a specific date (for receipts)
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

// Get cashier for the specified date
if ($date === date('Y-m-d')) {
    $cashier_name = getTodayCashier($conn);
} else {
    $cashier_name = getCashierByDate($conn, $date);
    // getCashierByDate returns 'Cashier' as default, but we need null for is_set check
    if ($cashier_name === 'Cashier') {
        $cashier_name = null;
    }
}

$is_set = $cashier_name !== null;

echo json_encode([
    'success' => true,
    'is_set' => $is_set,
    'cashier_name' => $cashier_name,
    'date' => $date
]);

