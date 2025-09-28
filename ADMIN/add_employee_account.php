<?php
require_once '../Includes/connection.php';
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id']) || strtoupper($_SESSION['role_category'] ?? '') !== 'EMPLOYEE' || strtoupper($_SESSION['program_abbreviation'] ?? '') !== 'ADMIN') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $firstName = ucwords(strtolower(trim($_POST['firstName'] ?? '')));
    $lastName = ucwords(strtolower(trim($_POST['lastName'] ?? '')));
    $extensionName = ucwords(strtolower(trim($_POST['extensionName'] ?? '')));
    $birthday = trim($_POST['birthday'] ?? '');
    $program_or_position = trim($_POST['program_position'] ?? '');

    if ($firstName === '' || $lastName === '' || $birthday === '' || $program_or_position === '') {
        throw new Exception('Missing required fields');
    }

    $birthdayObj = new DateTime($birthday);

    $currentDate = new DateTime();
    $age = $currentDate->diff($birthdayObj)->y;
    if ($age < 15) {
        throw new Exception('User must be at least 15 years old to register');
    }

    $sanitizedLastName = preg_replace('/\s+/', '', strtolower($lastName));
    $autoPassword = $sanitizedLastName . $birthdayObj->format('mdY');
    $password = password_hash($autoPassword, PASSWORD_DEFAULT);

    $sanitizedFirstName = preg_replace('/\s+/', '', strtolower($firstName));
    $email = strtolower(str_replace(' ', '', $lastName . '.' . $firstName . '@lucena.sti.edu.ph'));

    $checkEmailSql = "SELECT COUNT(*) FROM account WHERE email = ?";
    $checkStmt = $conn->prepare($checkEmailSql);
    $checkStmt->execute([$email]);
    if ($checkStmt->fetchColumn() > 0) {
        throw new Exception('An account with this email already exists');
    }

    $sql = "INSERT INTO account (first_name, last_name, extension_name, birthday, id_number, email, password, role_category, program_or_position, status, date_created)
            VALUES (?, ?, ?, ?, NULL, ?, ?, 'EMPLOYEE', ?, 'active', CURRENT_TIMESTAMP)";
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([
        $firstName,
        $lastName,
        $extensionName !== '' ? $extensionName : null,
        $birthday,
        $email,
        $password,
        $program_or_position
    ]);

    if (!$result) {
        throw new Exception('Database insert failed');
    }

    echo json_encode([
        'success' => true,
        'generated_email' => $email,
        'generated_password' => $autoPassword,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'birthday' => $birthday,
        'role_category' => 'EMPLOYEE',
        'program_or_position' => $program_or_position
    ]);
} catch (Exception $e) {
    http_response_code(400);
    error_log('Add Employee Account Error: ' . $e->getMessage());
    if (!headers_sent()) {
        $safePost = [
            'firstName' => $_POST['firstName'] ?? null,
            'lastName' => $_POST['lastName'] ?? null,
            'birthday' => $_POST['birthday'] ?? null,
            'program_position' => $_POST['program_position'] ?? null,
        ];
        error_log('Add Employee Account POST snapshot: ' . json_encode($safePost));
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;
?>