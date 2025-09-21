<?php
header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once '../Includes/connection.php';

    if (!isset($_POST['itemId'])) {
        throw new Exception('Item ID is required');
    }
    $itemId = $_POST['itemId'];

    $checkItem = $conn->prepare("SELECT item_code FROM inventory WHERE item_code = ?");
    $checkItem->execute([$itemId]);
    if (!$checkItem->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception('Item not found');
    }

    if (!isset($_FILES['newImage']) || $_FILES['newImage']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred: ' . 
            (isset($_FILES['newImage']) ? $_FILES['newImage']['error'] : 'No file data'));
    }

    $newImage = $_FILES['newImage'];

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($newImage['type'], $allowed_types)) {
        throw new Exception('Invalid file type. Only JPG, PNG and GIF are allowed.');
    }

    $uploadDir = '../uploads/itemlist/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }

    $fileExtension = pathinfo($newImage['name'], PATHINFO_EXTENSION);
    $uniqueFilename = uniqid('item_') . '.' . $fileExtension;
    $uploadFile = $uploadDir . $uniqueFilename;
    $dbFilePath = 'uploads/itemlist/' . $uniqueFilename;

    $getOldImageQuery = "SELECT image_path FROM inventory WHERE item_code = ?";
    $stmt = $conn->prepare($getOldImageQuery);
    $stmt->execute([$itemId]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $oldImagePath = '../' . $row['image_path'];
        if (file_exists($oldImagePath)) {
            unlink($oldImagePath);
        }
    }

    $imageInfo = getimagesize($newImage['tmp_name']);
    if ($imageInfo === false) {
        throw new Exception('Invalid image file');
    }

    switch ($imageInfo[2]) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($newImage['tmp_name']);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($newImage['tmp_name']);
            break;
        case IMAGETYPE_GIF:
            $sourceImage = imagecreatefromgif($newImage['tmp_name']);
            break;
        default:
            throw new Exception('Unsupported image type');
    }

    if ($sourceImage === false) {
        throw new Exception('Failed to create image resource');
    }

    $originalWidth = imagesx($sourceImage);
    $originalHeight = imagesy($sourceImage);

    $maxWidth = 600;
    $maxHeight = 800;
    
    $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
    $newWidth = (int)($originalWidth * $ratio);
    $newHeight = (int)($originalHeight * $ratio);

    $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

    if ($imageInfo[2] == IMAGETYPE_PNG || $imageInfo[2] == IMAGETYPE_GIF) {
        imagealphablending($resizedImage, false);
        imagesavealpha($resizedImage, true);
        $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
        imagefill($resizedImage, 0, 0, $transparent);
    }

    imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

    $saved = false;
    switch ($imageInfo[2]) {
        case IMAGETYPE_JPEG:
            $saved = imagejpeg($resizedImage, $uploadFile, 92);
            break;
        case IMAGETYPE_PNG:
            $saved = imagepng($resizedImage, $uploadFile, 2);
            break;
        case IMAGETYPE_GIF:
            $saved = imagegif($resizedImage, $uploadFile);
            break;
    }

    imagedestroy($sourceImage);
    imagedestroy($resizedImage);

    if (!$saved) {
        throw new Exception('Failed to save optimized image');
    }

    $sql = "UPDATE inventory SET image_path = ? WHERE item_code = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt->execute([$dbFilePath, $itemId])) {
        unlink($uploadFile);
        throw new Exception('Database update failed');
    }

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

}