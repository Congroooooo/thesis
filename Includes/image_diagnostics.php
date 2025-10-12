<?php
/**
 * Image Diagnostics and Health Check System
 * This file helps identify and fix image-related issues in the deployed system
 */

require_once '../Includes/connection.php';
require_once 'image_helpers.php';

/**
 * Diagnose image issues across the system
 * @param PDO $conn Database connection
 * @return array Diagnostic results
 */
function diagnoseImageIssues($conn) {
    $results = [
        'summary' => [],
        'missing_files' => [],
        'permission_issues' => [],
        'path_inconsistencies' => [],
        'suggestions' => []
    ];
    
    // Check upload directories
    $uploadDirs = [
        '../uploads/itemlist/',
        '../uploads/preorder/',
        '../uploads/'
    ];
    
    $results['summary']['directories'] = [];
    foreach ($uploadDirs as $dir) {
        $dirInfo = [
            'path' => $dir,
            'exists' => is_dir($dir),
            'writable' => is_dir($dir) ? is_writable($dir) : false,
            'permissions' => is_dir($dir) ? substr(sprintf('%o', fileperms($dir)), -4) : 'N/A'
        ];
        $results['summary']['directories'][] = $dirInfo;
        
        if (!$dirInfo['exists']) {
            $results['suggestions'][] = "Create missing directory: {$dir}";
        } elseif (!$dirInfo['writable']) {
            $results['permission_issues'][] = "Directory not writable: {$dir}";
            $results['suggestions'][] = "Fix permissions for: {$dir} (chmod 755 or 777)";
        }
    }
    
    // Check inventory table images
    try {
        $stmt = $conn->prepare("SELECT id, item_code, item_name, image_path FROM inventory WHERE image_path IS NOT NULL AND image_path != '' LIMIT 100");
        $stmt->execute();
        $inventoryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $totalItems = count($inventoryItems);
        $missingFiles = 0;
        $pathIssues = 0;
        
        foreach ($inventoryItems as $item) {
            $imagePath = $item['image_path'];
            $testPaths = [];
            
            // Generate possible file paths
            if (strpos($imagePath, 'uploads/') === 0) {
                $testPaths[] = '../' . $imagePath;
            } else {
                $testPaths[] = '../uploads/itemlist/' . $imagePath;
                $testPaths[] = '../uploads/preorder/' . $imagePath;
                $testPaths[] = '../' . $imagePath;
            }
            
            $fileExists = false;
            foreach ($testPaths as $testPath) {
                if (file_exists(__DIR__ . '/' . $testPath)) {
                    $fileExists = true;
                    break;
                }
            }
            
            if (!$fileExists) {
                $missingFiles++;
                $results['missing_files'][] = [
                    'table' => 'inventory',
                    'id' => $item['id'],
                    'item_code' => $item['item_code'],
                    'item_name' => $item['item_name'],
                    'stored_path' => $imagePath,
                    'tested_paths' => $testPaths
                ];
            }
            
            // Check for path inconsistencies
            if (strpos($imagePath, 'uploads/') !== 0 && strpos($imagePath, '../') !== 0) {
                $pathIssues++;
                $results['path_inconsistencies'][] = [
                    'table' => 'inventory',
                    'id' => $item['id'],
                    'item_code' => $item['item_code'],
                    'stored_path' => $imagePath,
                    'issue' => 'Path does not start with uploads/ or ../'
                ];
            }
        }
        
        $results['summary']['inventory'] = [
            'total_items' => $totalItems,
            'missing_files' => $missingFiles,
            'path_issues' => $pathIssues,
            'success_rate' => $totalItems > 0 ? round((($totalItems - $missingFiles) / $totalItems) * 100, 2) : 100
        ];
        
    } catch (Exception $e) {
        $results['summary']['inventory'] = ['error' => $e->getMessage()];
    }
    
    // Check preorder items
    try {
        $stmt = $conn->prepare("SELECT id, base_item_code, item_name, image_path FROM preorder_items WHERE image_path IS NOT NULL AND image_path != '' LIMIT 50");
        $stmt->execute();
        $preorderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $totalPreorder = count($preorderItems);
        $missingPreorder = 0;
        
        foreach ($preorderItems as $item) {
            $imagePath = $item['image_path'];
            $testPath = '../' . $imagePath;
            
            if (!file_exists(__DIR__ . '/' . $testPath)) {
                $missingPreorder++;
                $results['missing_files'][] = [
                    'table' => 'preorder_items',
                    'id' => $item['id'],
                    'item_code' => $item['base_item_code'],
                    'item_name' => $item['item_name'],
                    'stored_path' => $imagePath,
                    'tested_paths' => [$testPath]
                ];
            }
        }
        
        $results['summary']['preorder'] = [
            'total_items' => $totalPreorder,
            'missing_files' => $missingPreorder,
            'success_rate' => $totalPreorder > 0 ? round((($totalPreorder - $missingPreorder) / $totalPreorder) * 100, 2) : 100
        ];
        
    } catch (Exception $e) {
        $results['summary']['preorder'] = ['error' => $e->getMessage()];
    }
    
    // Add general suggestions
    if (count($results['missing_files']) > 0) {
        $results['suggestions'][] = "Run the fixImagePaths() function to attempt automatic path correction";
        $results['suggestions'][] = "Consider implementing the image backup/restore system";
    }
    
    if (count($results['path_inconsistencies']) > 0) {
        $results['suggestions'][] = "Standardize image paths to use 'uploads/' prefix";
    }
    
    return $results;
}

/**
 * Attempt to fix common image path issues
 * @param PDO $conn Database connection
 * @return array Fix results
 */
function fixImagePaths($conn) {
    $results = [
        'inventory_fixed' => 0,
        'preorder_fixed' => 0,
        'errors' => []
    ];
    
    try {
        // Fix inventory items
        $stmt = $conn->prepare("SELECT id, image_path FROM inventory WHERE image_path IS NOT NULL AND image_path != ''");
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($items as $item) {
            $currentPath = $item['image_path'];
            $fixedPath = null;
            
            // Skip if already properly formatted
            if (strpos($currentPath, 'uploads/') === 0) {
                continue;
            }
            
            // Try to find the actual file and correct the path
            $possiblePaths = [
                'uploads/itemlist/' . $currentPath,
                'uploads/preorder/' . $currentPath,
                'uploads/' . $currentPath
            ];
            
            foreach ($possiblePaths as $testPath) {
                if (file_exists(__DIR__ . '/../' . $testPath)) {
                    $fixedPath = $testPath;
                    break;
                }
            }
            
            if ($fixedPath && $fixedPath !== $currentPath) {
                $updateStmt = $conn->prepare("UPDATE inventory SET image_path = ? WHERE id = ?");
                if ($updateStmt->execute([$fixedPath, $item['id']])) {
                    $results['inventory_fixed']++;
                }
            }
        }
        
        // Fix preorder items
        $stmt = $conn->prepare("SELECT id, image_path FROM preorder_items WHERE image_path IS NOT NULL AND image_path != ''");
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($items as $item) {
            $currentPath = $item['image_path'];
            
            // Skip if already properly formatted
            if (strpos($currentPath, 'uploads/') === 0) {
                continue;
            }
            
            $fixedPath = 'uploads/preorder/' . basename($currentPath);
            if (file_exists(__DIR__ . '/../' . $fixedPath)) {
                $updateStmt = $conn->prepare("UPDATE preorder_items SET image_path = ? WHERE id = ?");
                if ($updateStmt->execute([$fixedPath, $item['id']])) {
                    $results['preorder_fixed']++;
                }
            }
        }
        
    } catch (Exception $e) {
        $results['errors'][] = $e->getMessage();
    }
    
    return $results;
}

/**
 * Create missing directories with proper permissions
 */
function ensureUploadDirectories() {
    $directories = [
        __DIR__ . '/../uploads/',
        __DIR__ . '/../uploads/itemlist/',
        __DIR__ . '/../uploads/preorder/'
    ];
    
    $results = [];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            if (mkdir($dir, 0755, true)) {
                $results[] = "Created directory: {$dir}";
            } else {
                $results[] = "Failed to create directory: {$dir}";
            }
        } else {
            // Check and fix permissions
            if (!is_writable($dir)) {
                if (chmod($dir, 0755)) {
                    $results[] = "Fixed permissions for: {$dir}";
                } else {
                    $results[] = "Failed to fix permissions for: {$dir}";
                }
            } else {
                $results[] = "Directory OK: {$dir}";
            }
        }
    }
    
    return $results;
}

/**
 * Get image health status for monitoring
 * @param PDO $conn Database connection
 * @return array Health status
 */
function getImageHealthStatus($conn) {
    $health = [
        'timestamp' => date('Y-m-d H:i:s'),
        'overall_status' => 'healthy',
        'issues' => []
    ];
    
    try {
        // Quick check on recent items
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN image_path IS NULL OR image_path = '' THEN 1 ELSE 0 END) as no_path
            FROM inventory 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute();
        $recent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($recent['total'] > 0) {
            $no_image_rate = ($recent['no_path'] / $recent['total']) * 100;
            if ($no_image_rate > 20) {
                $health['overall_status'] = 'warning';
                $health['issues'][] = "High rate of items without images in last 24h: {$no_image_rate}%";
            }
        }
        
        // Check directory accessibility
        $uploadDir = __DIR__ . '/../uploads/itemlist/';
        if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
            $health['overall_status'] = 'critical';
            $health['issues'][] = "Upload directory not accessible: {$uploadDir}";
        }
        
    } catch (Exception $e) {
        $health['overall_status'] = 'error';
        $health['issues'][] = "Database error: " . $e->getMessage();
    }
    
    return $health;
}

// CLI usage
if (php_sapi_name() === 'cli') {
    echo "Image Diagnostics Tool\n";
    echo "=====================\n\n";
    
    $diagnostics = diagnoseImageIssues($conn);
    
    echo "SUMMARY:\n";
    print_r($diagnostics['summary']);
    
    if (!empty($diagnostics['missing_files'])) {
        echo "\nMISSING FILES (" . count($diagnostics['missing_files']) . "):\n";
        foreach (array_slice($diagnostics['missing_files'], 0, 10) as $missing) {
            echo "- {$missing['table']} #{$missing['id']}: {$missing['item_name']} ({$missing['stored_path']})\n";
        }
        if (count($diagnostics['missing_files']) > 10) {
            echo "... and " . (count($diagnostics['missing_files']) - 10) . " more\n";
        }
    }
    
    if (!empty($diagnostics['suggestions'])) {
        echo "\nSUGGESTIONS:\n";
        foreach ($diagnostics['suggestions'] as $suggestion) {
            echo "- {$suggestion}\n";
        }
    }
}

?>