<?php
include '../Includes/connection.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && isset($_POST['title'])) {
    $id = intval($_POST['id']);
    $title = $_POST['title'];
    $newImage = isset($_FILES['image']) ? $_FILES['image'] : null;

    // Get current image path
    $stmt = $conn->prepare('SELECT image_path FROM homepage_content WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Image not found.']);
        exit;
    }
    $dbFilePath = $row['image_path'];
    $updateImage = false;

    if ($newImage && $newImage['tmp_name']) {
        $targetDir = '../uploads/Homepage contents/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $fileName = uniqid() . '_' . basename($newImage['name']);
        $targetFilePath = $targetDir . $fileName;
        $imageFileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($imageFileType, $allowedTypes)) {
            echo json_encode(['success' => false, 'error' => 'Invalid file type.']);
            exit;
        }

        // Process and optimize the image
        $imageInfo = getimagesize($newImage['tmp_name']);
        if ($imageInfo === false) {
            echo json_encode(['success' => false, 'error' => 'Invalid image file.']);
            exit;
        }

        // Create image resource based on type
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
            case IMAGETYPE_WEBP:
                $sourceImage = imagecreatefromwebp($newImage['tmp_name']);
                break;
            default:
                echo json_encode(['success' => false, 'error' => 'Unsupported image type.']);
                exit;
        }

        if ($sourceImage === false) {
            echo json_encode(['success' => false, 'error' => 'Failed to create image resource.']);
            exit;
        }

        // Get original dimensions
        $originalWidth = imagesx($sourceImage);
        $originalHeight = imagesy($sourceImage);

        // Calculate new dimensions while maintaining aspect ratio
        // Target: 800x600 max for homepage content
        $maxWidth = 800;
        $maxHeight = 600;
        
        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
        $newWidth = (int)($originalWidth * $ratio);
        $newHeight = (int)($originalHeight * $ratio);

        // Create new image with calculated dimensions
        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and GIF
        if ($imageInfo[2] == IMAGETYPE_PNG || $imageInfo[2] == IMAGETYPE_GIF || $imageInfo[2] == IMAGETYPE_WEBP) {
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
                $saved = imagejpeg($resizedImage, $targetFilePath, 92); // High quality JPEG
                break;
            case IMAGETYPE_PNG:
                $saved = imagepng($resizedImage, $targetFilePath, 2); // High quality PNG
                break;
            case IMAGETYPE_GIF:
                $saved = imagegif($resizedImage, $targetFilePath);
                break;
            case IMAGETYPE_WEBP:
                $saved = imagewebp($resizedImage, $targetFilePath, 92); // High quality WebP
                break;
        }

        // Clean up memory
        imagedestroy($sourceImage);
        imagedestroy($resizedImage);

        if ($saved) {
            // Delete old image
            $oldPath = '../' . $dbFilePath;
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
            $dbFilePath = 'uploads/Homepage contents/' . $fileName;
            $updateImage = true;
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save optimized image.']);
            exit;
        }
    }

    if ($updateImage) {
        $stmt = $conn->prepare('UPDATE homepage_content SET title = ?, image_path = ?, updated_at = NOW() WHERE id = ?');
        $success = $stmt->execute([$title, $dbFilePath, $id]);
    } else {
        $stmt = $conn->prepare('UPDATE homepage_content SET title = ?, updated_at = NOW() WHERE id = ?');
        $success = $stmt->execute([$title, $id]);
    }
    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update image.']);
    }
    exit;
}
echo json_encode(['success' => false, 'error' => 'Invalid request.']); 