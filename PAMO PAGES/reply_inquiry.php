<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
// Robust PAMO authorization: accept abbreviation or full name containing PAMO
$isPamo = false;
if (isset($_SESSION['program_abbreviation']) && strtoupper(trim($_SESSION['program_abbreviation'])) === 'PAMO') {
    $isPamo = true;
} elseif (isset($_SESSION['program_or_position']) && stripos($_SESSION['program_or_position'], 'PAMO') !== false) {
    $isPamo = true;
}
if (!isset($_SESSION['user_id']) || !$isPamo) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inquiry_id'], $_POST['reply'])) {
    require_once '../Includes/connection.php';
    $inquiry_id = intval($_POST['inquiry_id']);
    $reply = trim($_POST['reply']);
    if ($inquiry_id < 1 || $reply === '') {
        echo json_encode(['success' => false, 'error' => 'Invalid input.']);
        exit;
    }
    $stmt = $conn->prepare("UPDATE inquiries SET reply = :reply, status = 'replied', updated_at = NOW(), student_read = 0 WHERE id = :id");
    $stmt->bindParam(':reply', $reply, PDO::PARAM_STR);
    $stmt->bindParam(':id', $inquiry_id, PDO::PARAM_INT);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error.']);
    }
    exit;
}
echo json_encode(['success' => false, 'error' => 'Invalid request.']);
exit; 