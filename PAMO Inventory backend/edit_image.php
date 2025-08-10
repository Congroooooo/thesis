<?php
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once '../Includes/connection.php'; // PDO $conn

    // Get the item ID from POST data
    if (!isset($_POST['itemId'])) {
        throw new Exception('Item ID is required');
    }
    $itemId = $_POST['itemId'];

    // Validate item exists
    $checkItem = $conn->prepare("SELECT item_code FROM inventory WHERE item_code = ?");
    $checkItem->execute([$itemId]);
    if (!$checkItem->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception('Item not found');
    }

    // Check if file was uploaded
    if (!isset($_FILES['newImage']) || $_FILES['newImage']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred: ' . 
            (isset($_FILES['newImage']) ? $_FILES['newImage']['error'] : 'No file data'));
    }

    $newImage = $_FILES['newImage'];

    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($newImage['type'], $allowed_types)) {
        throw new Exception('Invalid file type. Only JPG, PNG and GIF are allowed.');
    }

    // Create uploads directory if it doesn't exist
    $uploadDir = '../uploads/itemlist/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }

    // Generate unique filename
    $fileExtension = pathinfo($newImage['name'], PATHINFO_EXTENSION);
    $uniqueFilename = uniqid('item_') . '.' . $fileExtension;
    $uploadFile = $uploadDir . $uniqueFilename;
    $dbFilePath = 'uploads/itemlist/' . $uniqueFilename;

    // Get the old image path to delete it
    $getOldImageQuery = "SELECT image_path FROM inventory WHERE item_code = ?";
    $stmt = $conn->prepare($getOldImageQuery);
    $stmt->execute([$itemId]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $oldImagePath = '../' . $row['image_path'];
        if (file_exists($oldImagePath)) {
            unlink($oldImagePath);
        }
    }

    // Move the uploaded file
    if (!move_uploaded_file($newImage['tmp_name'], $uploadFile)) {
        throw new Exception('Failed to move uploaded file: ' . error_get_last()['message']);
    }

    // Update database with new image path
    $sql = "UPDATE inventory SET image_path = ? WHERE item_code = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt->execute([$dbFilePath, $itemId])) {
        // If database update fails, delete the uploaded file
        unlink($uploadFile);
        throw new Exception('Database update failed');
    }

    // Log the activity
    $activity_description = "Updated image for item: $itemId";
    $log_activity_query = "INSERT INTO activities (action_type, description, item_code, user_id, timestamp) VALUES ('Edit Image', ?, ?, ?, NOW())";
    $stmt = $conn->prepare($log_activity_query);
    session_start();
    $user_id = $_SESSION['user_id'] ?? null;
    $stmt->execute([$activity_description, $itemId, $user_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Image updated successfully',
        'image_path' => $dbFilePath
    ]);

} catch (Exception $e) {
    error_log("Error in edit_image.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    // PDO closes automatically
}