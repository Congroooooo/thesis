<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'connection.php';
include("../Includes/sessionStart.php");
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $_SESSION['cart_count'] = $total;
}
?> 