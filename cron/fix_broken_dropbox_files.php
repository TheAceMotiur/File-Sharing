<?php
/**
 * Fix broken Dropbox file records
 * This script finds files marked as synced but not actually in Dropbox
 * and attempts to repair them
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/bootstrap.php';

use App\Services\DropboxSyncService;

echo "=== Fixing Broken Dropbox File Records ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

$db = getDBConnection();
$syncService = new DropboxSyncService();

// Get all files marked as synced to Dropbox
$result = $db->query("
    SELECT * FROM file_uploads 
    WHERE storage_location = 'dropbox' 
    AND sync_status = 'synced'
    AND dropbox_account_id IS NOT NULL
    ORDER BY id
");

$totalFiles = $result->num_rows;
$brokenFiles = 0;
$fixedFiles = 0;
$unfixableFiles = 0;

echo "Checking {$totalFiles} files marked as synced...\n\n";

while ($file = $result->fetch_assoc()) {
    echo "File #{$file['id']}: {$file['original_name']}\n";
    
    // Get account info
    $stmt = $db->prepare("SELECT * FROM dropbox_accounts WHERE id = ?");
    $stmt->bind_param("i", $file['dropbox_account_id']);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    
    if (!$account || empty($account['access_token'])) {
        echo "  ⚠️  Account issue - skipping\n\n";
        continue;
    }
    
    try {
        $client = new \Spatie\Dropbox\Client($account['access_token']);
        
        // Try to get file metadata from Dropbox
        try {
            $metadata = $client->getMetadata($file['dropbox_path']);
            echo "  ✓ File exists in Dropbox (" . round($metadata['size'] / (1024*1024), 2) . " MB)\n";
        } catch (\Spatie\Dropbox\Exceptions\BadRequest $e) {
            if (strpos($e->getMessage(), 'path/not_found') !== false) {
                echo "  ❌ File NOT in Dropbox - attempting to fix...\n";
                $brokenFiles++;
                
                // Check if file exists locally
                $localPath = UPLOAD_DIR . $file['unique_id'] . '/' . $file['stored_name'];
                
                if (file_exists($localPath)) {
                    echo "  ✓ Found local copy - re-syncing to Dropbox...\n";
                    
                    // Reset sync status and try again
                    $stmt = $db->prepare("UPDATE file_uploads SET sync_status = 'pending', storage_location = 'local' WHERE id = ?");
                    $stmt->bind_param("i", $file['id']);
                    $stmt->execute();
                    
                    // Try to sync
                    if ($syncService->syncFile($file['id'])) {
                        echo "  ✅ Successfully re-synced!\n";
                        $fixedFiles++;
                    } else {
                        echo "  ❌ Re-sync failed\n";
                        $unfixableFiles++;
                    }
                } else {
                    echo "  ❌ Local file also missing - marking as failed\n";
                    
                    // Mark as failed so it doesn't break downloads
                    $stmt = $db->prepare("UPDATE file_uploads SET sync_status = 'failed', storage_location = 'local' WHERE id = ?");
                    $stmt->bind_param("i", $file['id']);
                    $stmt->execute();
                    
                    $unfixableFiles++;
                }
            } else {
                echo "  ⚠️  Dropbox error: {$e->getMessage()}\n";
            }
        }
    } catch (\Exception $e) {
        echo "  ❌ Error: {$e->getMessage()}\n";
    }
    
    echo "\n";
}

echo str_repeat("=", 50) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 50) . "\n";
echo "Total files checked: {$totalFiles}\n";
echo "Broken files found: {$brokenFiles}\n";
echo "Successfully fixed: {$fixedFiles}\n";
echo "Unable to fix: {$unfixableFiles}\n";

if ($fixedFiles > 0) {
    echo "\n✅ {$fixedFiles} file(s) have been repaired and should now download correctly!\n";
}

if ($unfixableFiles > 0) {
    echo "\n⚠️  {$unfixableFiles} file(s) could not be repaired (missing from both local and Dropbox)\n";
    echo "These files have been marked as 'failed' to prevent download errors.\n";
}

$db->close();
