<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in.', 'session' => $_SESSION]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['question'])) {
    require_once '../Includes/connection.php';
    $question = trim($_POST['question']);
    $user_id = $_SESSION['user_id'];

    if (empty($question)) {
        echo json_encode(['success' => false, 'error' => 'Question is empty.']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO inquiries (user_id, question) VALUES (?, ?)");
    $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $question, PDO::PARAM_STR);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error.', 'info' => $stmt->errorInfo()]);
    }
    $conn = null;
    exit;
}
echo json_encode(['success' => false, 'error' => 'Invalid request.', 'post' => $_POST]);
exit; 