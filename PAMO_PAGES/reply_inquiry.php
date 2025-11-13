<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

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
    require_once 'includes/config_functions.php';
    
    $inquiry_id = intval($_POST['inquiry_id']);
    $reply = trim($_POST['reply']);
    if ($inquiry_id < 1 || $reply === '') {
        echo json_encode(['success' => false, 'error' => 'Invalid input.']);
        exit;
    }
    
    // Get inquiry details for audit trail
    $inquiryStmt = $conn->prepare("SELECT i.question, a.first_name, a.last_name, a.id_number 
                                    FROM inquiries i 
                                    JOIN account a ON i.user_id = a.id 
                                    WHERE i.id = :id");
    $inquiryStmt->bindParam(':id', $inquiry_id, PDO::PARAM_INT);
    $inquiryStmt->execute();
    $inquiryDetails = $inquiryStmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $conn->prepare("UPDATE inquiries SET reply = :reply, status = 'replied', updated_at = NOW(), student_read = 0 WHERE id = :id");
    $stmt->bindParam(':reply', $reply, PDO::PARAM_STR);
    $stmt->bindParam(':id', $inquiry_id, PDO::PARAM_INT);
    if ($stmt->execute()) {
        // Log to audit trail
        if ($inquiryDetails) {
            $studentName = $inquiryDetails['first_name'] . ' ' . $inquiryDetails['last_name'];
            $studentId = $inquiryDetails['id_number'];
            $questionPreview = mb_substr($inquiryDetails['question'], 0, 50) . (mb_strlen($inquiryDetails['question']) > 50 ? '...' : '');
            $auditDescription = "Replied to inquiry from {$studentName} ({$studentId}). Question: \"{$questionPreview}\"";
            logActivity($conn, 'Reply Inquiry', $auditDescription, $_SESSION['user_id']);
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error.']);
    }
    exit;
}
echo json_encode(['success' => false, 'error' => 'Invalid request.']);
exit; 