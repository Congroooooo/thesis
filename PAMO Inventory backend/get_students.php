<?php
header('Content-Type: application/json');
require_once '../Includes/connection.php'; // PDO $conn
$role = isset($_GET['role']) ? (string)$_GET['role'] : '';
$allowed_roles = ['COLLEGE STUDENT', 'SHS', 'EMPLOYEE'];
if (!in_array($role, $allowed_roles)) {
    $role = $allowed_roles[0]; // Default to first allowed role
}
$sql = "SELECT id, first_name, last_name, id_number FROM account WHERE role_category = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$role]);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
$students = [];
foreach ($result as $row) {
    $students[] = [
        'id' => $row['id'],
        'name' => $row['first_name'] . ' ' . $row['last_name'],
        'id_number' => $row['id_number']
    ];
}
echo json_encode(['success' => true, 'students' => $students]);
?> 