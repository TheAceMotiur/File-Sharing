<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Spatie\Dropbox\Client as DropboxClient;

try {
    $db = getDBConnection();
    
    // Get files that are stored locally and need to be synced
    $stmt = $db->query("
        SELECT fu.*, da.access_token, da.id as account_id
        FROM file_uploads fu
        LEFT JOIN dropbox_accounts da ON fu.dropbox_account_id = da.id
        WHERE fu.storage_location = 'local' 
        AND fu.upload_status = 'completed'
        AND fu.local_path IS NOT NULL
        LIMIT 10
    ");
    
    while ($file = $stmt->fetch_assoc()) {
        echo "Syncing file: {$file['file_name']} (ID: {$file['file_id']})\n";
        
        try {
            // Update status to syncing
            $updateStmt = $db->prepare("UPDATE file_uploads SET storage_location = 'syncing' WHERE id = ?");
            $updateStmt->bind_param("i", $file['id']);
            $updateStmt->execute();
            
            $localPath = $file['local_path'];
            
            // Check if local file exists
            if (!file_exists($localPath)) {
                throw new Exception("Local file not found: {$localPath}");
            }
            
            // Get or assign a Dropbox account
            if (!$file['dropbox_account_id']) {
                $dropboxAccount = $db->query("
                    SELECT da.*, 
                           COALESCE(SUM(fu.size), 0) as used_storage
                    FROM dropbox_accounts da
                    LEFT JOIN file_uploads fu ON fu.dropbox_account_id = da.id 
                        AND fu.storage_location = 'dropbox'
                    GROUP BY da.id
                    HAVING used_storage < 2147483648 OR used_storage IS NULL
                    LIMIT 1
                ")->fetch_assoc();
                
                if (!$dropboxAccount) {
                    throw new Exception('No Dropbox storage available');
                }
                
                $file['access_token'] = $dropboxAccount['access_token'];
                $file['account_id'] = $dropboxAccount['id'];
            }
            
            // Upload to Dropbox
            $client = new DropboxClient($file['access_token']);
            $dropboxPath = "/{$file['file_id']}/{$file['file_name']}";
            
            // Upload file in chunks
            $handle = fopen($localPath, 'rb');
            $fileSize = filesize($localPath);
            
            if ($fileSize < 150 * 1024 * 1024) {
                // Files smaller than 150MB - single upload
                $fileContents = file_get_contents($localPath);
                $client->upload($dropboxPath, $fileContents, 'overwrite');
            } else {
                // Large files - chunked upload
                $chunkSize = 10 * 1024 * 1024; // 10MB chunks
                $cursor = $client->uploadSessionStart(fread($handle, $chunkSize));
                
                while (!feof($handle)) {
                    $client->uploadSessionAppend(fread($handle, $chunkSize), $cursor);
                }
                
                $client->uploadSessionFinish('', $cursor, $dropboxPath);
            }
            
            fclose($handle);
            
            // Update database - mark as synced to Dropbox
            $updateStmt = $db->prepare("
                UPDATE file_uploads 
                SET storage_location = 'dropbox',
                    dropbox_path = ?,
                    dropbox_account_id = ?,
                    local_path = NULL,
                    synced_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->bind_param("sii", $dropboxPath, $file['account_id'], $file['id']);
            $updateStmt->execute();
            
            // Delete local file after successful sync
            if (file_exists($localPath)) {
                if (unlink($localPath)) {
                    echo "✓ Synced and removed local file: {$file['file_name']}\n";
                    
                    // Try to remove empty directory
                    $localDir = dirname($localPath);
                    if (is_dir($localDir)) {
                        $files = scandir($localDir);
                        // Check if directory is empty (only . and ..)
                        if (count($files) == 2) {
                            if (rmdir($localDir)) {
                                echo "  ✓ Removed empty directory\n";
                            }
                        }
                    }
                } else {
                    echo "  ⚠ Warning: Could not delete local file (permission issue?)\n";
                }
            } else {
                echo "  ℹ Local file already removed\n";
            }
            
        } catch (Exception $e) {
            echo "✗ Error syncing {$file['file_name']}: " . $e->getMessage() . "\n";
            
            // Revert status back to local on error
            $updateStmt = $db->prepare("UPDATE file_uploads SET storage_location = 'local' WHERE id = ?");
            $updateStmt->bind_param("i", $file['id']);
            $updateStmt->execute();
        }
    }
    
    echo "Sync completed.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
