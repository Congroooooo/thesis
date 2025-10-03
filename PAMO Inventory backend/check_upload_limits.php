<?php
// Get current upload limits
$max_file_size = ini_get('upload_max_filesize');
$max_post_size = ini_get('post_max_size');
$memory_limit = ini_get('memory_limit');

// Convert to bytes for comparison
function convertToBytes($value) {
    $unit = strtoupper(substr($value, -1));
    $value = (int) substr($value, 0, -1);
    
    switch ($unit) {
        case 'G':
            return $value * 1024 * 1024 * 1024;
        case 'M':
            return $value * 1024 * 1024;
        case 'K':
            return $value * 1024;
        default:
            return $value;
    }
}

$limits = [
    'max_file_size' => $max_file_size,
    'max_file_size_bytes' => convertToBytes($max_file_size),
    'max_post_size' => $max_post_size,
    'max_post_size_bytes' => convertToBytes($max_post_size),
    'memory_limit' => $memory_limit,
    'memory_limit_bytes' => convertToBytes($memory_limit)
];

header('Content-Type: application/json');
echo json_encode($limits);
?>