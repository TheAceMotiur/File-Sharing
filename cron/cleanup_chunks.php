<?php
/**
 * Cleanup old chunk uploads
 * Remove incomplete chunked uploads older than 24 hours
 * Run this every hour: 0 * * * * php /path/to/cleanup_chunks.php
 */

require_once __DIR__ . '/../config/bootstrap.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting chunk cleanup...\n";

$uploadDir = dirname(__DIR__) . '/uploads/';
$chunkDir = $uploadDir . 'chunks/';

// Create chunks directory if it doesn't exist
if (!is_dir($chunkDir)) {
    mkdir($chunkDir, 0755, true);
    echo "Created chunks directory\n";
}

$cleaned = 0;
$errors = 0;
$totalSize = 0;

try {
    // Get all chunk directories
    if ($handle = opendir($chunkDir)) {
        while (false !== ($entry = readdir($handle))) {
            if ($entry != "." && $entry != "..") {
                $chunkPath = $chunkDir . $entry;
                
                if (is_dir($chunkPath)) {
                    // Check if directory is older than 24 hours
                    $dirTime = filemtime($chunkPath);
                    $age = time() - $dirTime;
                    
                    if ($age > 86400) { // 24 hours in seconds
                        // Calculate size before deletion
                        $size = 0;
                        if ($dirHandle = opendir($chunkPath)) {
                            while (false !== ($file = readdir($dirHandle))) {
                                if ($file != "." && $file != "..") {
                                    $filePath = $chunkPath . '/' . $file;
                                    if (is_file($filePath)) {
                                        $size += filesize($filePath);
                                    }
                                }
                            }
                            closedir($dirHandle);
                        }
                        
                        // Delete all files in the chunk directory
                        $files = glob($chunkPath . '/*');
                        foreach ($files as $file) {
                            if (is_file($file)) {
                                unlink($file);
                            }
                        }
                        
                        // Remove the directory
                        if (rmdir($chunkPath)) {
                            $cleaned++;
                            $totalSize += $size;
                            echo "Cleaned chunk directory: {$entry} (Age: " . round($age / 3600, 1) . " hours, Size: " . formatBytes($size) . ")\n";
                        } else {
                            $errors++;
                            echo "Failed to remove directory: {$entry}\n";
                        }
                    }
                }
            }
        }
        closedir($handle);
    }
    
    echo "\n[" . date('Y-m-d H:i:s') . "] Cleanup completed\n";
    echo "Directories cleaned: {$cleaned}\n";
    echo "Space freed: " . formatBytes($totalSize) . "\n";
    echo "Errors: {$errors}\n";
    
} catch (Exception $e) {
    echo "Error during cleanup: " . $e->getMessage() . "\n";
    exit(1);
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
