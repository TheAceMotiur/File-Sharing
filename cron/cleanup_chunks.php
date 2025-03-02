<?php
require_once __DIR__ . '/../config.php';

// Script to clean up abandoned chunk directories older than 24 hours
$chunksDir = __DIR__ . '/../chunks';

if (is_dir($chunksDir)) {
    $dirs = glob($chunksDir . '/*', GLOB_ONLYDIR);
    
    foreach ($dirs as $dir) {
        // Check if directory is older than 24 hours
        if (time() - filemtime($dir) > 86400) {
            // Remove old chunk directory
            $files = glob($dir . '/*');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
    }
}
