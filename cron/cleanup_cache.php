<?php
/**
 * Cache Cleanup Script
 * Removes old cached files and manages cache size
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/bootstrap.php';

$db = getDBConnection();
$cacheDir = __DIR__ . '/../cache/';

// Maximum cache size (500MB)
$maxCacheSize = 500 * 1024 * 1024;

// Maximum file age in cache (7 days)
$maxAge = 7 * 24 * 60 * 60;

echo "=== Cache Cleanup ===" . PHP_EOL;

// Get all cached files
$cachedFiles = [];
$totalSize = 0;

if (is_dir($cacheDir)) {
    $files = glob($cacheDir . '*');
    
    foreach ($files as $file) {
        if (is_file($file)) {
            $size = filesize($file);
            $mtime = filemtime($file);
            $age = time() - $mtime;
            
            $cachedFiles[] = [
                'path' => $file,
                'size' => $size,
                'mtime' => $mtime,
                'age' => $age,
                'unique_id' => basename($file)
            ];
            
            $totalSize += $size;
        }
    }
}

echo "Total cached files: " . count($cachedFiles) . PHP_EOL;
echo "Total cache size: " . formatBytes($totalSize) . PHP_EOL;

$removed = 0;
$freedSpace = 0;

// Remove files older than maxAge
foreach ($cachedFiles as $key => $file) {
    if ($file['age'] > $maxAge) {
        if (unlink($file['path'])) {
            $removed++;
            $freedSpace += $file['size'];
            $totalSize -= $file['size'];
            unset($cachedFiles[$key]);
            echo "Removed old cache: {$file['unique_id']} (age: " . round($file['age'] / 86400, 1) . " days)" . PHP_EOL;
        }
    }
}

// If cache is still too large, remove oldest files
if ($totalSize > $maxCacheSize) {
    echo PHP_EOL . "Cache size exceeds limit, removing oldest files..." . PHP_EOL;
    
    // Sort by modification time (oldest first)
    usort($cachedFiles, function($a, $b) {
        return $a['mtime'] - $b['mtime'];
    });
    
    foreach ($cachedFiles as $file) {
        if ($totalSize <= $maxCacheSize) {
            break;
        }
        
        if (unlink($file['path'])) {
            $removed++;
            $freedSpace += $file['size'];
            $totalSize -= $file['size'];
            echo "Removed old cache: {$file['unique_id']} (freed: " . formatBytes($file['size']) . ")" . PHP_EOL;
        }
    }
}

// Remove orphaned cache files (files not in database)
$stmt = $db->query("SELECT unique_id FROM file_uploads");
$validIds = [];
while ($row = $stmt->fetch_assoc()) {
    $validIds[] = $row['unique_id'];
}

$files = glob($cacheDir . '*');
foreach ($files as $file) {
    if (is_file($file)) {
        $uniqueId = basename($file);
        if (!in_array($uniqueId, $validIds)) {
            $size = filesize($file);
            if (unlink($file)) {
                $removed++;
                $freedSpace += $size;
                $totalSize -= $size;
                echo "Removed orphaned cache: {$uniqueId}" . PHP_EOL;
            }
        }
    }
}

echo PHP_EOL . "=== Summary ===" . PHP_EOL;
echo "Files removed: {$removed}" . PHP_EOL;
echo "Space freed: " . formatBytes($freedSpace) . PHP_EOL;
echo "Cache size now: " . formatBytes($totalSize) . PHP_EOL;

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
