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
    $idNumber = trim($_POST['idNumber'] ?? '');
    $role_category = trim($_POST['role_category'] ?? '');
    $program_or_position = trim($_POST['program_position'] ?? '');

    if ($firstName === '' || $lastName === '' || $birthday === '' || $idNumber === '' || $role_category === '' || $program_or_position === '') {
        throw new Exception('Missing required fields');
    }

    if (strlen($idNumber) !== 11) {
        throw new Exception('ID Number must be exactly 11 digits');
    }
    
    // Validate that ID starts with 02000
    if (!str_starts_with($idNumber, '02000')) {
        throw new Exception('ID Number must start with 02000 followed by 6 digits');
    }
    
    // Check for duplicate ID number
    $checkIdStmt = $conn->prepare("SELECT id FROM account WHERE id_number = ?");
    $checkIdStmt->execute([$idNumber]);
    if ($checkIdStmt->fetch()) {
        throw new Exception('This ID number already exists in the system');
    }

    $birthdayObj = new DateTime($birthday);

    $currentDate = new DateTime();
    $age = $currentDate->diff($birthdayObj)->y;
    if ($age < 16) {
        throw new Exception('User must be at least 16 years old to register');
    }
    $sanitizedLastName = preg_replace('/\s+/', '', strtolower($lastName));
    $autoPassword = $sanitizedLastName . $birthdayObj->format('mdY');
    $password = password_hash($autoPassword, PASSWORD_DEFAULT);

    $lastSixDigits = substr($idNumber, -6);
    $email = strtolower(str_replace(' ', '', $lastName . '.' . $lastSixDigits . '@lucena.sti.edu.ph'));

    $sql = "INSERT INTO account (first_name, last_name, extension_name, birthday, id_number, email, password, role_category, program_or_position, status, date_created)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', CURRENT_TIMESTAMP)";
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([
            $firstName,
            $lastName,
        $extensionName !== '' ? $extensionName : null,
        $birthday,
            $idNumber,
            $email,
            $password,
            $role_category,
            $program_or_position
        ]);

    if (!$result) {
        throw new Exception('Database insert failed');
    }

    echo json_encode([
        'success' => true,
        'generated_email' => $email,
        'generated_password' => $autoPassword,
        'id_number' => $idNumber,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'birthday' => $birthday,
        'role_category' => $role_category,
        'program_or_position' => $program_or_position
    ]);
} catch (Exception $e) {
    http_response_code(400);
    error_log('Add Account Error: ' . $e->getMessage());
    if (!headers_sent()) {
        $safePost = [
            'firstName' => $_POST['firstName'] ?? null,
            'lastName' => $_POST['lastName'] ?? null,
            'birthday' => $_POST['birthday'] ?? null,
            'idNumber' => isset($_POST['idNumber']) ? ('len=' . strlen((string)$_POST['idNumber'])) : null,
            'role_category' => $_POST['role_category'] ?? null,
            'program_position' => $_POST['program_position'] ?? null,
        ];
        error_log('Add Account POST snapshot: ' . json_encode($safePost));
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;
?>
