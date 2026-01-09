<?php
/**
 * Cron job to clean up orphaned local files (failed syncs or very old pending uploads)
 * Note: Successfully synced files are deleted immediately after sync completes
 * This cron only handles edge cases like failed syncs or stuck pending files
 * Run every hour: 0 * * * * php /path/to/cron/cleanup_local_files.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\DropboxSyncService;
use App\Database;

try {
    $db = Database::getInstance()->getConnection();
    $syncService = new DropboxSyncService($db);
    
    echo "Starting cleanup of old local files...\n";
    echo str_repeat("=", 50) . "\n\n";
    
    $result = $syncService->cleanupOldLocalFiles();
    
    echo "\n" . str_repeat("=", 50) . "\n";
    
    if ($result['success']) {
        echo "Cleanup completed successfully!\n";
        echo "Files cleaned: {$result['cleaned']}\n";
        echo "Space freed: " . number_format($result['space_freed'] / (1024*1024*1024), 2) . " GB\n";
    } else {
        echo "Cleanup failed: {$result['message']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
