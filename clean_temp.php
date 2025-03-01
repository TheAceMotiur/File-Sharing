<?php
/**
 * Clean up temporary files
 * Can be run as a cron job to remove orphaned upload chunks
 */

require_once __DIR__ . '/upload_helper.php';

// Security: Make sure this is being run from CLI or with proper admin authentication
if (php_sapi_name() !== 'cli' && (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin'])) {
    die('Unauthorized');
}

$tempDir = __DIR__ . '/temp';
$expireTime = 24 * 3600; // 24 hours in seconds
$count = 0;

// Check if temp directory exists
if (!is_dir($tempDir)) {
    echo "Temp directory does not exist. Nothing to clean.\n";
    exit;
}

// Get all subdirectories in the temp folder
$dirs = glob($tempDir . '/*', GLOB_ONLYDIR);

foreach ($dirs as $dir) {
    // Check the last modification time of the directory
    $lastModified = filemtime($dir);
    $now = time();
    
    // If directory is older than expiration time, delete it
    if (($now - $lastModified) > $expireTime) {
        echo "Cleaning up directory: " . basename($dir) . " (last modified " . date('Y-m-d H:i:s', $lastModified) . ")\n";
        
        // Delete all files in the directory
        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                echo "  Deleted: " . basename($file) . "\n";
            }
        }
        
        // Remove directory
        if (rmdir($dir)) {
            echo "  Removed directory: " . basename($dir) . "\n";
            $count++;
        } else {
            echo "  Failed to remove directory: " . basename($dir) . "\n";
        }
    }
}

echo "Cleanup complete. Removed $count directories.\n";
?>
