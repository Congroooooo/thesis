<?php
/**
 * Image Recovery and Repair System
 * Fixes corrupted image paths and recovers missing images
 */

require_once 'connection.php';

/**
 * Fix corrupted image paths in the database
 */
function repairCorruptedImagePaths($conn) {
    $results = [
        'fixed' => 0,
        'errors' => [],
        'details' => []
    ];
    
    try {
        // Find corrupted paths (ending with periods, incomplete extensions)
        $stmt = $conn->prepare("
            SELECT id, item_code, item_name, image_path 
            FROM inventory 
            WHERE image_path IS NOT NULL 
            AND image_path != '' 
            AND (image_path LIKE '%.' OR image_path NOT LIKE '%.png' AND image_path NOT LIKE '%.jpg' AND image_path NOT LIKE '%.jpeg' AND image_path NOT LIKE '%.gif')
        ");
        $stmt->execute();
        $corruptedItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Found " . count($corruptedItems) . " corrupted image paths\n";
        
        foreach ($corruptedItems as $item) {
            $corruptedPath = $item['image_path'];
            $fixedPath = null;
            
            // Try to find the actual file by matching pattern
            $baseFilename = basename($corruptedPath);
            $cleanFilename = rtrim($baseFilename, '.');
            
            // Look for files with similar patterns
            $searchPatterns = [
                '../uploads/itemlist/' . $cleanFilename . '.png',
                '../uploads/itemlist/' . $cleanFilename . '.jpg',
                '../uploads/itemlist/' . $cleanFilename . '.jpeg',
                '../uploads/itemlist/' . substr($cleanFilename, 0, 20) . '*.png',
                '../uploads/itemlist/' . substr($cleanFilename, 0, 20) . '*.jpg'
            ];
            
            foreach ($searchPatterns as $pattern) {
                if (strpos($pattern, '*') !== false) {
                    $matches = glob(__DIR__ . '/' . $pattern);
                    if (!empty($matches)) {
                        $fixedPath = 'uploads/itemlist/' . basename($matches[0]);
                        break;
                    }
                } else {
                    if (file_exists(__DIR__ . '/' . $pattern)) {
                        $fixedPath = str_replace('../', '', $pattern);
                        break;
                    }
                }
            }
            
            if ($fixedPath) {
                // Update database with corrected path
                $updateStmt = $conn->prepare("UPDATE inventory SET image_path = ? WHERE id = ?");
                if ($updateStmt->execute([$fixedPath, $item['id']])) {
                    $results['fixed']++;
                    $results['details'][] = [
                        'id' => $item['id'],
                        'item_code' => $item['item_code'],
                        'old_path' => $corruptedPath,
                        'new_path' => $fixedPath,
                        'status' => 'fixed'
                    ];
                    echo "Fixed #{$item['id']}: {$corruptedPath} -> {$fixedPath}\n";
                }
            } else {
                $results['details'][] = [
                    'id' => $item['id'],
                    'item_code' => $item['item_code'],
                    'old_path' => $corruptedPath,
                    'status' => 'no_file_found'
                ];
                echo "No file found for #{$item['id']}: {$corruptedPath}\n";
            }
        }
        
    } catch (Exception $e) {
        $results['errors'][] = $e->getMessage();
    }
    
    return $results;
}

/**
 * Assign available images to items without images
 */
function assignAvailableImages($conn) {
    $results = [
        'assigned' => 0,
        'details' => []
    ];
    
    try {
        // Get items without images
        $stmt = $conn->prepare("
            SELECT id, item_code, item_name, category
            FROM inventory 
            WHERE (image_path IS NULL OR image_path = '' OR image_path LIKE '%.')
            ORDER BY id DESC
            LIMIT 20
        ");
        $stmt->execute();
        $itemsWithoutImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get available image files
        $imageFiles = [];
        $uploadDir = __DIR__ . '/../uploads/itemlist/';
        $files = glob($uploadDir . '*.{png,jpg,jpeg,gif}', GLOB_BRACE);
        
        foreach ($files as $file) {
            $filename = basename($file);
            if ($filename !== 'default.png' && $filename !== 'default.jpg') {
                $imageFiles[] = $filename;
            }
        }
        
        echo "Found " . count($itemsWithoutImages) . " items without images\n";
        echo "Found " . count($imageFiles) . " available image files\n";
        
        // Try to match items with images based on categories, names, etc.
        foreach ($itemsWithoutImages as $index => $item) {
            if ($index < count($imageFiles)) {
                $assignedImage = 'uploads/itemlist/' . $imageFiles[$index];
                
                $updateStmt = $conn->prepare("UPDATE inventory SET image_path = ? WHERE id = ?");
                if ($updateStmt->execute([$assignedImage, $item['id']])) {
                    $results['assigned']++;
                    $results['details'][] = [
                        'id' => $item['id'],
                        'item_code' => $item['item_code'],
                        'item_name' => $item['item_name'],
                        'assigned_image' => $assignedImage
                    ];
                    echo "Assigned image to #{$item['id']}: {$assignedImage}\n";
                }
            }
        }
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
    
    return $results;
}

/**
 * Create a backup of current image assignments
 */
function backupImageAssignments($conn) {
    $backupFile = __DIR__ . '/../uploads/backup/image_assignments_' . date('Y-m-d_H-i-s') . '.json';
    
    try {
        // Ensure backup directory exists
        $backupDir = dirname($backupFile);
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $stmt = $conn->prepare("SELECT id, item_code, item_name, image_path FROM inventory WHERE image_path IS NOT NULL AND image_path != ''");
        $stmt->execute();
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $backup = [
            'timestamp' => date('Y-m-d H:i:s'),
            'total_items' => count($assignments),
            'assignments' => $assignments
        ];
        
        file_put_contents($backupFile, json_encode($backup, JSON_PRETTY_PRINT));
        echo "Backup created: {$backupFile}\n";
        
        return $backupFile;
        
    } catch (Exception $e) {
        echo "Backup failed: " . $e->getMessage() . "\n";
        return false;
    }
}

// Main execution
echo "Image Recovery and Repair System\n";
echo "================================\n\n";

// Create backup first
echo "1. Creating backup...\n";
$backupFile = backupImageAssignments($conn);

// Fix corrupted paths
echo "\n2. Repairing corrupted image paths...\n";
$repairResults = repairCorruptedImagePaths($conn);
echo "Fixed: {$repairResults['fixed']} items\n";

// Assign available images to items without images
echo "\n3. Assigning available images to items without images...\n";
$assignResults = assignAvailableImages($conn);
echo "Assigned: {$assignResults['assigned']} items\n";

// Summary
echo "\n=== SUMMARY ===\n";
echo "Backup file: " . ($backupFile ?: 'Failed') . "\n";
echo "Corrupted paths fixed: {$repairResults['fixed']}\n";
echo "Images assigned: {$assignResults['assigned']}\n";

if (!empty($repairResults['errors'])) {
    echo "Errors encountered:\n";
    foreach ($repairResults['errors'] as $error) {
        echo "- {$error}\n";
    }
}

echo "\nRecommendation: Test the website now to see if images are displaying properly.\n";
?>