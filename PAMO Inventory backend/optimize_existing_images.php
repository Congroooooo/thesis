<?php
/**
 * Utility script to optimize existing images in the uploads/itemlist directory
 * Run this once to improve quality of existing product images
 */

require_once '../Includes/connection.php';

function optimizeImage($sourcePath, $targetPath, $maxWidth = 600, $maxHeight = 800) {
    $imageInfo = getimagesize($sourcePath);
    if ($imageInfo === false) {
        return false;
    }

    // Create image resource based on type
    switch ($imageInfo[2]) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_GIF:
            $sourceImage = imagecreatefromgif($sourcePath);
            break;
        default:
            return false;
    }

    if ($sourceImage === false) {
        return false;
    }

    // Get original dimensions
    $originalWidth = imagesx($sourceImage);
    $originalHeight = imagesy($sourceImage);

    // Calculate new dimensions while maintaining aspect ratio
    $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
    
    // Only resize if image is larger than target
    if ($ratio >= 1) {
        imagedestroy($sourceImage);
        return true; // Image is already small enough
    }
    
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

    return $saved;
}

// Only run if accessed directly (not via web browser for security)
if (php_sapi_name() === 'cli') {
    $uploadDir = '../uploads/itemlist/';
    $backupDir = '../uploads/itemlist/backup/';
    
    // Create backup directory
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    $files = glob($uploadDir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
    $processed = 0;
    $errors = 0;

    echo "Starting image optimization...\n";
    echo "Found " . count($files) . " images to process.\n\n";

    foreach ($files as $file) {
        $filename = basename($file);
        $backupPath = $backupDir . $filename;
        
        // Create backup
        if (copy($file, $backupPath)) {
            // Optimize the original
            if (optimizeImage($file, $file)) {
                $processed++;
                echo "✓ Optimized: $filename\n";
            } else {
                $errors++;
                echo "✗ Failed to optimize: $filename\n";
                // Restore from backup if optimization failed
                copy($backupPath, $file);
            }
        } else {
            $errors++;
            echo "✗ Failed to backup: $filename\n";
        }
    }

    echo "\n=== SUMMARY ===\n";
    echo "Processed: $processed images\n";
    echo "Errors: $errors images\n";
    echo "Backup location: $backupDir\n";
    echo "Complete!\n";
} else {
    echo "This script must be run from command line for security reasons.";
}
?>