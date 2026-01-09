<?php
/**
 * Cache Statistics
 * Shows cache hit/miss information
 */

$cacheDir = __DIR__ . '/../cache/';

echo "=== Cache Statistics ===" . PHP_EOL . PHP_EOL;

// Get cache directory info
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
    echo "Cache directory created: {$cacheDir}" . PHP_EOL;
}

$files = glob($cacheDir . '*');
$totalSize = 0;
$fileCount = 0;
$fileDetails = [];

foreach ($files as $file) {
    if (is_file($file)) {
        $size = filesize($file);
        $mtime = filemtime($file);
        $age = time() - $mtime;
        
        $totalSize += $size;
        $fileCount++;
        
        $fileDetails[] = [
            'id' => basename($file),
            'size' => $size,
            'age_days' => round($age / 86400, 1),
            'last_accessed' => date('Y-m-d H:i:s', $mtime)
        ];
    }
}

// Sort by age (newest first)
usort($fileDetails, function($a, $b) {
    return $a['age_days'] - $b['age_days'];
});

echo "Total cached files: {$fileCount}" . PHP_EOL;
echo "Total cache size: " . formatBytes($totalSize) . PHP_EOL;
echo "Cache directory: {$cacheDir}" . PHP_EOL;
echo PHP_EOL;

if ($fileCount > 0) {
    echo "=== Recent Cache Files ===" . PHP_EOL;
    foreach (array_slice($fileDetails, 0, 10) as $file) {
        echo sprintf(
            "ID: %s | Size: %s | Age: %.1f days | Last: %s" . PHP_EOL,
            $file['id'],
            formatBytes($file['size']),
            $file['age_days'],
            $file['last_accessed']
        );
    }
    
    if ($fileCount > 10) {
        echo "... and " . ($fileCount - 10) . " more files" . PHP_EOL;
    }
} else {
    echo "No cached files found." . PHP_EOL;
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
