<?php
/**
 * Image Path Helper Functions
 * Handles image path resolution and fallback logic for product images
 */

/**
 * Resolve the correct image path from various possible formats
 * @param string $imagePath The image path from database
 * @return string The resolved image path
 */
function resolveImagePath($imagePath) {
    if (empty($imagePath)) {
        return getDefaultImagePath();
    }

    // Check if it's already a data URL
    if (strpos($imagePath, 'data:image') === 0) {
        return $imagePath;
    }

    // Clean up the path
    $imagePath = trim($imagePath);
    
    // If path starts with uploads/, make it relative from Pages directory
    if (strpos($imagePath, 'uploads/') === 0) {
        return '../' . $imagePath;
    }
    
    // If path already starts with ../, use as is
    if (strpos($imagePath, '../') === 0) {
        return $imagePath;
    }
    
    // If it's just a filename, assume it's in uploads/itemlist/
    if (strpos($imagePath, '/') === false) {
        return '../uploads/itemlist/' . $imagePath;
    }
    
    // Default: prepend ../ if not present
    return '../' . ltrim($imagePath, '/');
}

/**
 * Get the default image path when no image is available
 * @return string Default image path or SVG placeholder
 */
function getDefaultImagePath() {
    // Check for default image in itemlist folder
    $defaultPaths = [
        '../uploads/itemlist/default.png',
        '../uploads/itemlist/default.jpg',
        '../uploads/default.png',
        '../uploads/default.jpg'
    ];
    
    foreach ($defaultPaths as $path) {
        // Convert relative path to absolute for file_exists check
        $absolutePath = __DIR__ . '/' . $path;
        if (file_exists($absolutePath)) {
            return $path;
        }
    }
    
    // Return SVG placeholder if no default image found
    return 'data:image/svg+xml;base64,' . base64_encode(
        '<svg width="300" height="300" xmlns="http://www.w3.org/2000/svg">
            <rect width="300" height="300" fill="#f0f0f0" stroke="#ddd" stroke-width="2"/>
            <text x="150" y="150" text-anchor="middle" dominant-baseline="middle" font-family="Arial" font-size="16" fill="#666">No Image Available</text>
        </svg>'
    );
}

/**
 * Get fallback image path with existence check
 * @param string $imagePath Original image path
 * @return string Valid image path or fallback
 */
function getImagePathWithFallback($imagePath) {
    $resolved = resolveImagePath($imagePath);
    
    // If it's a data URL, return as is
    if (strpos($resolved, 'data:image') === 0) {
        return $resolved;
    }
    
    // Convert to absolute path for existence check
    $absolutePath = __DIR__ . '/../Pages/' . $resolved;
    
    // If file exists, return the resolved path
    if (file_exists($absolutePath)) {
        return $resolved;
    }
    
    // Otherwise return default
    return getDefaultImagePath();
}
?>
