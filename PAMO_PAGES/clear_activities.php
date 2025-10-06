<?php
session_start();
include '../includes/connection.php';

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