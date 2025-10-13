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

    // Validate item exists and get base item code
    $checkItem = $conn->prepare("SELECT item_code FROM inventory WHERE item_code = ?");
    $checkItem->execute([$itemId]);
    if (!$checkItem->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception('Item not found');
    }

    $baseItemCode = strtok($itemId, '-');

    // Check if file was uploaded (using same logic as Add New Item)
    if (!isset($_FILES['newImage']) || $_FILES['newImage']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Image upload is required');
    }

    $newImage = $_FILES['newImage'];
    $imageTmpPath = $newImage['tmp_name'];
    $imageName = $newImage['name'];
    $imageType = $newImage['type'];

    // Validate file type (same as Add New Item)
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($imageType, $allowed_types)) {
        throw new Exception('Invalid image type. Allowed types: JPG, PNG, GIF');
    }

    // Create uploads directory if it doesn't exist
    $uploadDir = '../uploads/itemlist/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }

    // Generate unique filename (same as Add New Item)
    // Get extension from original filename, with fallback to MIME type
    $imageExtension = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));
    
    // If no extension found, determine from MIME type
    if (empty($imageExtension)) {
        switch ($imageType) {
            case 'image/jpeg':
                $imageExtension = 'jpg';
                break;
            case 'image/png':
                $imageExtension = 'png';
                break;
            case 'image/gif':
                $imageExtension = 'gif';
                break;
            default:
                $imageExtension = 'jpg'; // fallback
        }
    }
    
    // Ensure extension is valid
    if (!in_array($imageExtension, ['jpg', 'jpeg', 'png', 'gif'])) {
        $imageExtension = 'jpg'; // fallback
    }
    
    $uniqueName = uniqid('img_', true) . '.' . $imageExtension;
    $imagePath = $uploadDir . $uniqueName;
    $dbFilePath = 'uploads/itemlist/' . $uniqueName;

    // Get all old image paths for items with the same base code to delete them
    $getOldImagesQuery = "SELECT DISTINCT image_path FROM inventory WHERE item_code LIKE ? AND image_path IS NOT NULL AND image_path != ''";
    $stmt = $conn->prepare($getOldImagesQuery);
    $stmt->execute([$baseItemCode . '%']);
    $oldImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($oldImages as $imageRow) {
        $oldImagePath = '../' . $imageRow['image_path'];
        if (file_exists($oldImagePath)) {
            unlink($oldImagePath);
        }
    }

    // Move the uploaded file (same as Add New Item)
    if (!move_uploaded_file($imageTmpPath, $imagePath)) {
        throw new Exception('Error moving uploaded file');
    }

    // Update database with new image path for all items with the same base code
    $sql = "UPDATE inventory SET image_path = ? WHERE item_code LIKE ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt->execute([$dbFilePath, $baseItemCode . '%'])) {
        // If database update fails, delete the uploaded file
        unlink($imagePath);
        throw new Exception('Database update failed');
    }

    // Get count of updated items for logging
    $updatedCount = $stmt->rowCount();

    // Log the activity
    $activity_description = "Updated image for: $baseItemCode (affected $updatedCount items, triggered from: $itemId)";
    $log_activity_query = "INSERT INTO activities (action_type, description, item_code, user_id, timestamp) VALUES ('Edit Image', ?, ?, ?, NOW())";
    $stmt = $conn->prepare($log_activity_query);
    session_start();
    $user_id = $_SESSION['user_id'] ?? null;
    $stmt->execute([$activity_description, $itemId, $user_id]);

    echo json_encode([
        'success' => true,
        'message' => "Image updated successfully for all items in the $baseItemCode group ($updatedCount items affected)",
        'image_path' => $dbFilePath,
        'updated_count' => $updatedCount,
        'base_item_code' => $baseItemCode
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