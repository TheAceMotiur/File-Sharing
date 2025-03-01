<?php
/**
 * Helper functions for handling file uploads
 */

// Maximum file size allowed (2GB)
define('MAX_FILE_SIZE', 2 * 1024 * 1024 * 1024);

// Default chunk size (2MB)
define('DEFAULT_CHUNK_SIZE', 2 * 1024 * 1024);

/**
 * Validates a file based on size and type
 * 
 * @param array $fileInfo File information array
 * @return array Status and error message if applicable
 */
function validateFile($fileInfo) {
    // Check file size
    if ($fileInfo['size'] > MAX_FILE_SIZE) {
        return [
            'valid' => false,
            'error' => 'File size exceeds the 2 GB limit'
        ];
    }

    // Define allowed file types
    $allowedTypes = [
        // Images
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        // Audio
        'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4', 'audio/aac',
        // Video
        'video/mp4', 'video/mpeg', 'video/webm', 'video/quicktime', 'video/x-msvideo',
        // Archives
        'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed',
        'application/x-tar', 'application/gzip',
        // Documents
        'application/pdf', 'image/vnd.djvu',
        // Other media
        'application/vnd.apple.mpegurl', 'application/x-mpegurl'
    ];

    // Check MIME type if a file path is provided
    if (isset($fileInfo['tmp_name']) && file_exists($fileInfo['tmp_name'])) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fileInfo['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            return [
                'valid' => false,
                'error' => 'File type not allowed. Only media and archive files are supported.'
            ];
        }
    }

    return ['valid' => true];
}

/**
 * Creates a temporary directory for chunked uploads
 * 
 * @param string $fileId Unique file identifier
 * @return array Status and directory path or error message
 */
function createTempDirectory($fileId) {
    $tempDir = __DIR__ . '/temp/' . $fileId;
    
    // Check if directory already exists
    if (is_dir($tempDir)) {
        return [
            'success' => true,
            'path' => $tempDir
        ];
    }
    
    // Create the directory with proper permissions
    if (!mkdir($tempDir, 0755, true)) {
        return [
            'success' => false,
            'error' => 'Failed to create temporary directory'
        ];
    }
    
    return [
        'success' => true,
        'path' => $tempDir
    ];
}

/**
 * Combines uploaded chunks into a single file
 * 
 * @param string $tempDir Directory containing chunks
 * @param string $fileName Original file name
 * @return array Status and complete file path or error message
 */
function combineChunks($tempDir, $fileName) {
    // Get all chunks
    $chunks = glob($tempDir . '/chunk_*');
    
    if (empty($chunks)) {
        return [
            'success' => false,
            'error' => 'No chunks found'
        ];
    }
    
    // Sort chunks numerically to ensure correct order
    sort($chunks, SORT_NATURAL);
    
    // Create output file
    $outputPath = $tempDir . '/complete_' . $fileName;
    $outputFile = fopen($outputPath, 'wb');
    
    if (!$outputFile) {
        return [
            'success' => false,
            'error' => 'Failed to create output file'
        ];
    }
    
    // Combine all chunks
    foreach ($chunks as $chunk) {
        $chunkContent = file_get_contents($chunk);
        if ($chunkContent === false) {
            fclose($outputFile);
            return [
                'success' => false,
                'error' => 'Failed to read chunk: ' . $chunk
            ];
        }
        
        if (fwrite($outputFile, $chunkContent) === false) {
            fclose($outputFile);
            return [
                'success' => false,
                'error' => 'Failed to write to output file'
            ];
        }
        
        // Remove processed chunk to free up space
        unlink($chunk);
    }
    
    fclose($outputFile);
    
    return [
        'success' => true,
        'path' => $outputPath
    ];
}

/**
 * Clean up temporary directory after upload
 * 
 * @param string $tempDir Directory to clean
 * @return boolean Success status
 */
function cleanupTempDirectory($tempDir) {
    // Safety check - only delete directories under /temp/
    if (strpos($tempDir, __DIR__ . '/temp/') !== 0 || !is_dir($tempDir)) {
        return false;
    }
    
    // Delete any remaining files
    $files = glob($tempDir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    
    // Remove the directory
    return rmdir($tempDir);
}

/**
 * Create download link for uploaded file
 * 
 * @param string $fileId Unique file identifier
 * @return string Download URL
 */
function createDownloadLink($fileId) {
    return 'https://' . $_SERVER['HTTP_HOST'] . '/download/' . $fileId;
}

/**
 * Get human-readable file size
 * 
 * @param int $bytes Size in bytes
 * @return string Formatted file size
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Log upload activity
 * 
 * @param string $message Log message
 * @param string $type Log type (info, warning, error)
 * @return void
 */
function logUploadActivity($message, $type = 'info') {
    $logDir = __DIR__ . '/logs';
    
    // Create log directory if it doesn't exist
    if (!is_dir($logDir) && !mkdir($logDir, 0755, true)) {
        return;
    }
    
    $logFile = $logDir . '/upload_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp][$type] $message" . PHP_EOL;
    
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}
?>
