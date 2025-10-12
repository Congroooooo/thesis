<?php
/**
 * Robust Image Upload and Management System
 * Addresses common issues with image persistence in deployed environments
 */

class ImageManager {
    private $conn;
    private $baseUploadDir;
    private $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private $maxFileSize = 5 * 1024 * 1024; // 5MB
    
    public function __construct($conn, $baseUploadDir = '../uploads/') {
        $this->conn = $conn;
        $this->baseUploadDir = rtrim($baseUploadDir, '/') . '/';
        $this->ensureDirectoryStructure();
    }
    
    /**
     * Ensure all required directories exist with proper permissions
     */
    private function ensureDirectoryStructure() {
        $dirs = [
            $this->baseUploadDir,
            $this->baseUploadDir . 'itemlist/',
            $this->baseUploadDir . 'preorder/',
            $this->baseUploadDir . 'temp/',
            $this->baseUploadDir . 'backup/'
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    throw new Exception("Failed to create directory: {$dir}");
                }
            }
            
            // Ensure directory is writable
            if (!is_writable($dir)) {
                if (!chmod($dir, 0755)) {
                    error_log("Warning: Could not set permissions for {$dir}");
                }
            }
        }
        
        // Create .htaccess to protect upload directories
        $htaccessContent = "Options -Indexes\n<Files ~ \"\\.(php|phtml|php3|php4|php5|pl|py|jsp|asp|sh|cgi)$\">\nOrder Allow,Deny\nDeny from all\n</Files>";
        foreach (['itemlist/', 'preorder/', 'temp/', 'backup/'] as $subdir) {
            $htaccessPath = $this->baseUploadDir . $subdir . '.htaccess';
            if (!file_exists($htaccessPath)) {
                file_put_contents($htaccessPath, $htaccessContent);
            }
        }
    }
    
    /**
     * Upload and process an image with multiple fallback mechanisms
     * @param array $file $_FILES array element
     * @param string $category 'itemlist' or 'preorder'
     * @param string $prefix Filename prefix (e.g., item code)
     * @return array Upload result with path and metadata
     */
    public function uploadImage($file, $category = 'itemlist', $prefix = '') {
        // Validate file upload
        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $this->getUploadErrorMessage($file['error'] ?? UPLOAD_ERR_NO_FILE));
        }
        
        // Validate file size
        if ($file['size'] > $this->maxFileSize) {
            throw new Exception('File too large. Maximum size: ' . ($this->maxFileSize / 1024 / 1024) . 'MB');
        }
        
        // Validate file type
        $originalExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($originalExtension, $this->allowedTypes)) {
            throw new Exception('Invalid file type. Allowed: ' . implode(', ', $this->allowedTypes));
        }
        
        // Validate that it's actually an image
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            throw new Exception('File is not a valid image');
        }
        
        // Generate secure filename
        $safePrefix = preg_replace('/[^A-Za-z0-9_-]/', '_', $prefix);
        $timestamp = time();
        $randomSuffix = bin2hex(random_bytes(4));
        $filename = ($safePrefix ? $safePrefix . '_' : '') . $timestamp . '_' . $randomSuffix . '.' . $originalExtension;
        
        // Determine upload path
        $categoryDir = $this->baseUploadDir . $category . '/';
        $fullPath = $categoryDir . $filename;
        $dbPath = 'uploads/' . $category . '/' . $filename;
        
        // Create temporary backup location
        $tempPath = $this->baseUploadDir . 'temp/' . $filename;
        
        try {
            // First, copy to temp location
            if (!move_uploaded_file($file['tmp_name'], $tempPath)) {
                throw new Exception('Failed to move uploaded file to temporary location');
            }
            
            // Verify the temp file
            if (!file_exists($tempPath) || filesize($tempPath) === 0) {
                throw new Exception('Temporary file verification failed');
            }
            
            // Copy from temp to final location
            if (!copy($tempPath, $fullPath)) {
                throw new Exception('Failed to copy file to final location');
            }
            
            // Verify final file
            if (!file_exists($fullPath) || filesize($fullPath) === 0) {
                // Clean up temp file
                @unlink($tempPath);
                throw new Exception('Final file verification failed');
            }
            
            // Set proper permissions
            chmod($fullPath, 0644);
            
            // Create backup copy
            $backupPath = $this->baseUploadDir . 'backup/' . $filename;
            copy($fullPath, $backupPath);
            
            // Clean up temp file
            @unlink($tempPath);
            
            return [
                'success' => true,
                'filename' => $filename,
                'db_path' => $dbPath,
                'full_path' => $fullPath,
                'file_size' => filesize($fullPath),
                'mime_type' => $imageInfo['mime'],
                'dimensions' => [
                    'width' => $imageInfo[0],
                    'height' => $imageInfo[1]
                ]
            ];
            
        } catch (Exception $e) {
            // Clean up any partial files
            @unlink($tempPath);
            @unlink($fullPath);
            throw $e;
        }
    }
    
    /**
     * Validate that an image path exists and is accessible
     * @param string $imagePath Database image path
     * @return array Validation result
     */
    public function validateImagePath($imagePath) {
        if (empty($imagePath)) {
            return ['valid' => false, 'reason' => 'Empty path'];
        }
        
        $testPaths = [];
        
        // Generate possible full paths
        if (strpos($imagePath, 'uploads/') === 0) {
            $testPaths[] = $this->baseUploadDir . '../' . $imagePath;
        } else {
            $testPaths[] = $this->baseUploadDir . 'itemlist/' . $imagePath;
            $testPaths[] = $this->baseUploadDir . 'preorder/' . $imagePath;
            $testPaths[] = $this->baseUploadDir . $imagePath;
        }
        
        foreach ($testPaths as $testPath) {
            if (file_exists($testPath) && is_readable($testPath) && filesize($testPath) > 0) {
                return [
                    'valid' => true,
                    'resolved_path' => $testPath,
                    'web_path' => str_replace($this->baseUploadDir . '../', '', $testPath),
                    'file_size' => filesize($testPath),
                    'last_modified' => filemtime($testPath)
                ];
            }
        }
        
        // Check backup location
        $backupPath = $this->baseUploadDir . 'backup/' . basename($imagePath);
        if (file_exists($backupPath)) {
            return [
                'valid' => false,
                'reason' => 'File missing but backup exists',
                'backup_path' => $backupPath,
                'can_restore' => true
            ];
        }
        
        return [
            'valid' => false,
            'reason' => 'File not found',
            'tested_paths' => $testPaths
        ];
    }
    
    /**
     * Attempt to restore a missing image from backup
     * @param string $imagePath Database image path
     * @return bool Success status
     */
    public function restoreFromBackup($imagePath) {
        $backupPath = $this->baseUploadDir . 'backup/' . basename($imagePath);
        
        if (!file_exists($backupPath)) {
            return false;
        }
        
        // Determine correct restore location
        $restorePath = '';
        if (strpos($imagePath, 'uploads/itemlist/') === 0) {
            $restorePath = $this->baseUploadDir . '../' . $imagePath;
        } elseif (strpos($imagePath, 'uploads/preorder/') === 0) {
            $restorePath = $this->baseUploadDir . '../' . $imagePath;
        } else {
            // Try to guess from filename
            $restorePath = $this->baseUploadDir . 'itemlist/' . basename($imagePath);
        }
        
        return copy($backupPath, $restorePath) && chmod($restorePath, 0644);
    }
    
    /**
     * Get upload error message
     */
    private function getUploadErrorMessage($errorCode) {
        $messages = [
            UPLOAD_ERR_OK => 'No error',
            UPLOAD_ERR_INI_SIZE => 'File too large (php.ini limit)',
            UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
            UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'No temporary directory',
            UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
        ];
        
        return $messages[$errorCode] ?? 'Unknown upload error';
    }
    
    /**
     * Batch validate images from database
     * @param string $table Table name (inventory or preorder_items)
     * @param int $limit Number of items to check
     * @return array Validation results
     */
    public function batchValidateImages($table = 'inventory', $limit = 100) {
        $results = [
            'checked' => 0,
            'valid' => 0,
            'invalid' => 0,
            'restored' => 0,
            'details' => []
        ];
        
        $imageColumn = $table === 'inventory' ? 'image_path' : 'image_path';
        $nameColumn = $table === 'inventory' ? 'item_name' : 'item_name';
        $codeColumn = $table === 'inventory' ? 'item_code' : 'base_item_code';
        
        $stmt = $this->conn->prepare("
            SELECT id, {$codeColumn} as code, {$nameColumn} as name, {$imageColumn} as image_path 
            FROM {$table} 
            WHERE {$imageColumn} IS NOT NULL AND {$imageColumn} != '' 
            ORDER BY id DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results['checked']++;
            $validation = $this->validateImagePath($row['image_path']);
            
            if ($validation['valid']) {
                $results['valid']++;
            } else {
                $results['invalid']++;
                $results['details'][] = [
                    'table' => $table,
                    'id' => $row['id'],
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'image_path' => $row['image_path'],
                    'issue' => $validation['reason']
                ];
                
                // Try to restore from backup
                if (isset($validation['can_restore']) && $validation['can_restore']) {
                    if ($this->restoreFromBackup($row['image_path'])) {
                        $results['restored']++;
                        $results['details'][count($results['details']) - 1]['restored'] = true;
                    }
                }
            }
        }
        
        return $results;
    }
}

// Usage example and testing function
function testImageManager($conn) {
    try {
        $imageManager = new ImageManager($conn);
        
        echo "Testing Image Manager...\n";
        
        // Test directory creation
        echo "Directory structure: OK\n";
        
        // Test batch validation
        $validation = $imageManager->batchValidateImages('inventory', 10);
        echo "Batch validation results:\n";
        echo "- Checked: {$validation['checked']}\n";
        echo "- Valid: {$validation['valid']}\n";
        echo "- Invalid: {$validation['invalid']}\n";
        echo "- Restored: {$validation['restored']}\n";
        
        return $imageManager;
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        return null;
    }
}

?>