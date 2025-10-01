<?php
header('Content-Type: application/json');
require_once '../Includes/connection.php';

$role = isset($_GET['role']) ? trim((string)$_GET['role']) : '';
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$limit = 100;

$allowed_roles = ['COLLEGE STUDENT', 'SHS', 'EMPLOYEE'];

if (empty($role)) {
    echo json_encode(['success' => false, 'message' => 'Role parameter is required']);
    exit();
}

if (!in_array($role, $allowed_roles)) {
    echo json_encode(['success' => false, 'message' => 'Invalid role selected. Allowed: ' . implode(', ', $allowed_roles)]);
    exit();
}

$sql = "SELECT id, first_name, last_name, id_number FROM account WHERE role_category = ? AND status = 'active'";
$params = [$role];

if (!empty($search)) {
    $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ? OR id_number LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " ORDER BY first_name, last_name LIMIT $limit";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $students = [];
    foreach ($result as $row) {
        $students[] = [
            'id' => $row['id'],
            'name' => $row['first_name'] . ' ' . $row['last_name'],
            'id_number' => $row['id_number']
        ];
    }

    $countSql = "SELECT COUNT(*) as total FROM account WHERE role_category = ? AND status = 'active'";
    $countParams = [$role];
    
    if (!empty($search)) {
        $countSql .= " AND (first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ? OR id_number LIKE ?)";
        $countParams[] = $searchTerm;
        $countParams[] = $searchTerm;
        $countParams[] = $searchTerm;
        $countParams[] = $searchTerm;
    }
    
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($countParams);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true, 
        'students' => $students,
        'total' => $totalCount
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error occurred: ' . $e->getMessage()]);
}
?> 