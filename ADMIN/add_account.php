<?php
require_once '../Includes/connection.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $extensionName = trim($_POST['extensionName'] ?? '');
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

    $birthdayObj = new DateTime($birthday);
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
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;
?>
