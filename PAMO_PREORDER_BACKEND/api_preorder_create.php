<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../Includes/connection.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('POST required');

    $itemName = trim($_POST['item_name'] ?? '');
    $baseCode = trim($_POST['base_item_code'] ?? '');
    $categoryId = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? intval($_POST['category_id']) : null;
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
    $sizesCsv = trim($_POST['sizes'] ?? '');
    $subcategoryIds = isset($_POST['subcategory_ids']) && is_array($_POST['subcategory_ids']) ? $_POST['subcategory_ids'] : [];

    if ($itemName === '' || $baseCode === '' || $sizesCsv === '') {
        throw new Exception('item_name, base_item_code and sizes are required');
    }

    $imagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', strtolower($baseCode));
        $fileName = $safeName . '_' . time() . '.' . $ext;
        $targetDir = __DIR__ . '/../uploads/preorder/';
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $targetPath = $targetDir . $fileName;
        
        // Process and optimize the image (same logic as add_item.php)
        $imageInfo = getimagesize($_FILES['image']['tmp_name']);
        if ($imageInfo === false) {
            throw new Exception('Invalid image file');
        }

        // Create image resource based on type
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($_FILES['image']['tmp_name']);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($_FILES['image']['tmp_name']);
                break;
            case IMAGETYPE_GIF:
                $sourceImage = imagecreatefromgif($_FILES['image']['tmp_name']);
                break;
            default:
                throw new Exception('Unsupported image type');
        }

        if ($sourceImage === false) {
            throw new Exception('Failed to create image resource');
        }

        // Get original dimensions
        $originalWidth = imagesx($sourceImage);
        $originalHeight = imagesy($sourceImage);

        // Calculate new dimensions while maintaining aspect ratio
        // Target: 600x800 max for high quality display
        $maxWidth = 600;
        $maxHeight = 800;
        
        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
        $newWidth = (int)($originalWidth * $ratio);
        $newHeight = (int)($originalHeight * $ratio);

        // Create new image with calculated dimensions
        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and GIF
        if ($imageInfo[2] == IMAGETYPE_PNG || $imageInfo[2] == IMAGETYPE_GIF) {
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
            $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
            imagefill($resizedImage, 0, 0, $transparent);
        }

        // Resize with high quality
        imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

        // Save the optimized image
        $saved = false;
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                $saved = imagejpeg($resizedImage, $targetPath, 92); // High quality JPEG
                break;
            case IMAGETYPE_PNG:
                $saved = imagepng($resizedImage, $targetPath, 2); // High quality PNG
                break;
            case IMAGETYPE_GIF:
                $saved = imagegif($resizedImage, $targetPath);
                break;
        }

        // Clean up memory
        imagedestroy($sourceImage);
        imagedestroy($resizedImage);

        if (!$saved) {
            throw new Exception('Failed to save optimized image');
        }
        
        $imagePath = 'uploads/preorder/' . $fileName;
    }

    $stmt = $conn->prepare('INSERT INTO preorder_items (base_item_code, item_name, category_id, price, sizes, image_path) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$baseCode, $itemName, $categoryId, $price, $sizesCsv, $imagePath]);
    $preId = intval($conn->lastInsertId());

    if (!empty($subcategoryIds)) {
        $ins = $conn->prepare('INSERT INTO preorder_item_subcategory (preorder_item_id, subcategory_id) VALUES (?, ?)');
        foreach ($subcategoryIds as $sid) {
            $sid = intval($sid);
            if ($sid > 0) $ins->execute([$preId, $sid]);
        }
    }

    // Audit trail: log pre-order creation
    try {
        $userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
        $desc = sprintf('Pre-order created → base_code=%s, name=%s, price=%.2f, sizes=%s', $baseCode, $itemName, $price, $sizesCsv);
        $log = $conn->prepare('INSERT INTO activities (action_type, description, item_code, user_id, timestamp) VALUES (?,?,?,?, NOW())');
        $log->execute(['PreOrder Created', $desc, $baseCode, $userId]);
    } catch (Throwable $e) { /* best-effort logging */ }

    echo json_encode(['success' => true, 'id' => $preId, 'image_path' => $imagePath]);
    exit;
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>


