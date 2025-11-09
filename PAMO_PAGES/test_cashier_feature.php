<?php
// Test the cashier API endpoints
session_start();

echo "<h2>Testing Cashier Feature</h2>";
echo "<hr>";

// Test 1: Check if session is set
echo "<h3>Test 1: Session Check</h3>";
echo "Session user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET') . "<br>";
echo "Session role_category: " . (isset($_SESSION['role_category']) ? $_SESSION['role_category'] : 'NOT SET') . "<br>";
echo "Session program_abbreviation: " . (isset($_SESSION['program_abbreviation']) ? $_SESSION['program_abbreviation'] : 'NOT SET') . "<br>";

$role = strtoupper($_SESSION['role_category'] ?? '');
$programAbbr = strtoupper($_SESSION['program_abbreviation'] ?? '');
$isPAMO = ($role === 'EMPLOYEE' && $programAbbr === 'PAMO');

echo "Is PAMO: " . ($isPAMO ? 'YES' : 'NO') . "<br>";

echo "<hr>";

// Test 2: Check database connection
echo "<h3>Test 2: Database Connection</h3>";
require_once '../Includes/connection.php';
require_once '../Includes/cashier_session_manager.php';

try {
    $stmt = $conn->query('SELECT COUNT(*) FROM cashier_sessions');
    echo "✓ Database connected successfully<br>";
    echo "✓ cashier_sessions table exists<br>";
    echo "✓ Current records: " . $stmt->fetchColumn() . "<br>";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "<br>";
}

echo "<hr>";

// Test 3: Check today's cashier
echo "<h3>Test 3: Today's Cashier Status</h3>";
$today = date('Y-m-d');
echo "Today's date: $today<br>";

$cashier = getTodayCashier($conn);
if ($cashier) {
    echo "✓ Cashier is set: $cashier<br>";
} else {
    echo "✗ Cashier NOT set for today<br>";
}

echo "<hr>";

// Test 4: Simulate API call
echo "<h3>Test 4: API Simulation</h3>";
echo "<a href='get_cashier_status.php' target='_blank'>Click to test get_cashier_status.php</a><br>";

echo "<hr>";

// Test 5: JavaScript file check
echo "<h3>Test 5: JavaScript File Check</h3>";
$jsFile = __DIR__ . '/../PAMO_JS/cashier-modal.js';
if (file_exists($jsFile)) {
    echo "✓ cashier-modal.js exists<br>";
    echo "File size: " . filesize($jsFile) . " bytes<br>";
} else {
    echo "✗ cashier-modal.js NOT FOUND<br>";
}

$cssFile = __DIR__ . '/../PAMO_CSS/cashier-modal.css';
if (file_exists($cssFile)) {
    echo "✓ cashier-modal.css exists<br>";
    echo "File size: " . filesize($cssFile) . " bytes<br>";
} else {
    echo "✗ cashier-modal.css NOT FOUND<br>";
}

echo "<hr>";
echo "<h3>Expected Behavior:</h3>";
echo "If cashier is NOT set and you're logged in as PAMO, the modal should appear.<br>";
echo "Check your browser console (F12) for any JavaScript errors.<br>";
?>
