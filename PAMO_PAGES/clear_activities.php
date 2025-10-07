<?php
session_start();

// Use absolute paths for better compatibility across environments
$base_dir = dirname(__DIR__);
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

include $connection_file;

try {
    $query = "DELETE FROM activities WHERE DATE(timestamp) = CURDATE()";
    $stmt = $conn->prepare($query);
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Error clearing activities: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>