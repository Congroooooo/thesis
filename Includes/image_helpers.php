<?php
/**
 * Image helper functions for handling missing images and fallbacks
 */

/**
 * Get a fallback image path when the original image is missing
 * @param string $originalPath The original image path
 * @param string $basePath The base path for file existence checks (usually __DIR__)
 * @return string The fallback image path
 */
function getImageFallback($originalPath = '', $basePath = __DIR__) {
    // First, try to use the original image if it exists and is valid
    if (!empty($originalPath)) {
        // Handle different path formats
        $testPaths = [];
        
        // If it's already a full relative path
        if (strpos($originalPath, 'uploads/') === 0) {
            $testPaths[] = '../' . $originalPath;
        }
        // If it's just a filename, try common directories
        else if (strpos($originalPath, '/') === false) {
            $testPaths[] = '../uploads/itemlist/' . $originalPath;
            $testPaths[] = '../uploads/preorder/' . $originalPath;
        }
        // If it has a relative path indicator
        else if (strpos($originalPath, '../') === 0) {
            $testPaths[] = $originalPath;
        }
        // Default case
        else {
            $testPaths[] = '../' . ltrim($originalPath, '/');
        }
        
        // Check if any of the test paths exist
        foreach ($testPaths as $testPath) {
            $fullPath = $basePath . '/' . $testPath;
            if (file_exists($fullPath)) {
                return $testPath;
            }
        }
    }
    
    // If original image doesn't exist, try default images
    if (file_exists($basePath . '/../uploads/itemlist/default.png')) {
        return '../uploads/itemlist/default.png';
    } elseif (file_exists($basePath . '/../uploads/itemlist/default.jpg')) {
        return '../uploads/itemlist/default.jpg';
    }
    
    // Final fallback: SVG placeholder
    return 'data:image/svg+xml;base64,' . base64_encode(
        '<svg width="300" height="300" xmlns="http://www.w3.org/2000/svg">
            <rect width="300" height="300" fill="#f0f0f0" stroke="#ddd" stroke-width="2"/>
            <text x="150" y="150" text-anchor="middle" dominant-baseline="middle" font-family="Arial" font-size="16" fill="#666">No Image</text>
        </svg>'
    );
}

/**
 * Get the JavaScript onerror handler for image tags
 * @return string The onerror attribute value
 */
function getImageOnErrorHandler() {
    $svgFallback = base64_encode(
        '<svg width="300" height="300" xmlns="http://www.w3.org/2000/svg">
            <rect width="300" height="300" fill="#f0f0f0" stroke="#ddd" stroke-width="2"/>
            <text x="150" y="150" text-anchor="middle" dominant-baseline="middle" font-family="Arial" font-size="16" fill="#666">No Image</text>
        </svg>'
    );
    
    return "this.onerror=null; this.src='data:image/svg+xml;base64," . $svgFallback . "'";
}

/**
 * Generate a complete img tag with proper fallback handling
 * @param string $src The image source
 * @param string $alt The alt text
 * @param string $additionalAttributes Additional HTML attributes
 * @param string $basePath Base path for file checks
 * @return string Complete img tag HTML
 */
function generateImageTag($src, $alt = '', $additionalAttributes = '', $basePath = __DIR__) {
    $safeSrc = htmlspecialchars(getImageFallback($src, $basePath));
    $safeAlt = htmlspecialchars($alt);
    $onError = getImageOnErrorHandler();
    
    return "<img src=\"{$safeSrc}\" alt=\"{$safeAlt}\" onerror=\"{$onError}\" {$additionalAttributes}>";
}
?>