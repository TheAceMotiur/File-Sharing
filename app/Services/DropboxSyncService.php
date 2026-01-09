<?php

namespace App\Services;

use Spatie\Dropbox\Client as DropboxClient;

/**
 * Dropbox Sync Service
 * Handles file synchronization to Dropbox accounts
 */
class DropboxSyncService
{
    private $db;
    private $maxAccountSize;
    
    public function __construct($db = null)
    {
        if ($db === null) {
            $this->db = getDBConnection();
        } else {
            $this->db = $db;
        }
        $config = require __DIR__ . '/../../config/app.php';
        $this->maxAccountSize = $config['dropbox']['max_account_size'];
    }
    
    /**
     * Find available Dropbox account with enough space
     * 
     * @param int $fileSize File size in bytes
     * @return array|null Account info or null
     */
    public function findAvailableAccount(int $fileSize): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM dropbox_accounts 
            WHERE (used_storage + ?) <= ?
            ORDER BY used_storage ASC
            LIMIT 1
        ");
        $stmt->bind_param("ii", $fileSize, $this->maxAccountSize);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        return $result ?: null;
    }
    
    /**
     * Sync file to Dropbox
     * 
     * @param int $fileId File ID
     * @return bool Success status
     */
    public function syncFile(int $fileId): bool
    {
        try {
            // Get file info
            $stmt = $this->db->prepare("SELECT * FROM file_uploads WHERE id = ?");
            $stmt->bind_param("i", $fileId);
            $stmt->execute();
            $file = $stmt->get_result()->fetch_assoc();
            
            if (!$file || $file['sync_status'] === 'synced') {
                return false;
            }
            
            // Update status to syncing
            $this->updateSyncStatus($fileId, 'syncing');
            
            // Find available Dropbox account
            $account = $this->findAvailableAccount($file['size']);
            
            if (!$account) {
                $this->updateSyncStatus($fileId, 'failed');
                error_log("No available Dropbox account for file {$fileId}");
                return false;
            }
            
            // Get local file path
            $localPath = UPLOAD_DIR . $file['unique_id'] . '/' . $file['stored_name'];
            
            if (!file_exists($localPath)) {
                $this->updateSyncStatus($fileId, 'failed');
                error_log("Local file not found: {$localPath}");
                return false;
            }
            
            // Upload to Dropbox
            $dropboxPath = '/' . $file['unique_id'] . '/' . $file['stored_name'];
            $client = new DropboxClient($account['access_token']);
            
            // Read file and upload
            $fileContent = file_get_contents($localPath);
            $client->upload($dropboxPath, $fileContent, 'add');
            
            // Update database
            $stmt = $this->db->prepare("
                UPDATE file_uploads 
                SET dropbox_account_id = ?,
                    dropbox_path = ?,
                    storage_location = 'dropbox',
                    sync_status = 'synced',
                    synced_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("isi", $account['id'], $dropboxPath, $fileId);
            $stmt->execute();
            
            // Update account storage usage
            $stmt = $this->db->prepare("
                UPDATE dropbox_accounts 
                SET used_storage = used_storage + ?
                WHERE id = ?
            ");
            $stmt->bind_param("ii", $file['size'], $account['id']);
            $stmt->execute();
            
            // Delete local file immediately after successful sync
            $config = require __DIR__ . '/../../config/app.php';
            if ($config['dropbox']['delete_local_after_sync']) {
                $deleted = $this->deleteLocalFile($file['unique_id']);
                error_log("File {$fileId} synced to Dropbox. Local deletion: " . ($deleted ? 'SUCCESS' : 'FAILED'));
            }
            
            return true;
            
        } catch (\Exception $e) {
            error_log("Dropbox sync failed for file {$fileId}: " . $e->getMessage());
            $this->updateSyncStatus($fileId, 'failed');
            return false;
        }
    }
    
    /**
     * Update sync status
     * 
     * @param int $fileId
     * @param string $status
     */
    private function updateSyncStatus(int $fileId, string $status): void
    {
        $stmt = $this->db->prepare("UPDATE file_uploads SET sync_status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $fileId);
        $stmt->execute();
    }
    
    /**
     * Delete local file after sync
     * 
     * @param string $uniqueId
     * @return bool Success status
     */
    private function deleteLocalFile(string $uniqueId): bool
    {
        $dir = UPLOAD_DIR . $uniqueId;
        
        if (!is_dir($dir)) {
            return false;
        }
        
        // Delete all files in directory
        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        // Remove directory
        return rmdir($dir);
    }
    
    /**
     * Download file from Dropbox
     * 
     * @param array $file File info from database
     * @return string|false File contents or false
     */
    public function downloadFromDropbox(array $file)
    {
        try {
            // Get Dropbox account
            $stmt = $this->db->prepare("SELECT * FROM dropbox_accounts WHERE id = ?");
            $stmt->bind_param("i", $file['dropbox_account_id']);
            $stmt->execute();
            $account = $stmt->get_result()->fetch_assoc();
            
            if (!$account) {
                return false;
            }
            
            // Download from Dropbox
            $client = new DropboxClient($account['access_token']);
            return $client->download($file['dropbox_path']);
            
        } catch (\Exception $e) {
            error_log("Dropbox download failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get account storage stats
     * 
     * @return array
     */
    public function getAccountStats(): array
    {
        $result = $this->db->query("
            SELECT 
                id,
                used_storage,
                ROUND((used_storage / {$this->maxAccountSize}) * 100, 2) as usage_percent
            FROM dropbox_accounts
            ORDER BY used_storage ASC
        ");
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Clean up local files for failed syncs or orphaned uploads
     * Note: Successfully synced files are deleted immediately after sync
     * This only cleans up edge cases
     */
    public function cleanupOldLocalFiles(): array
    {
        try {
            // Only clean files that failed sync or are very old pending files
            // Don't touch already-synced files (they should be deleted immediately after sync)
            $stmt = $this->db->prepare("
                SELECT id, unique_id, original_name, size
                FROM file_uploads 
                WHERE (sync_status = 'failed' OR (sync_status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)))
                LIMIT 50
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            $files = $result->fetch_all(MYSQLI_ASSOC);
            
            $cleaned = 0;
            $totalFreed = 0;
            
            foreach ($files as $file) {
                $localPath = __DIR__ . '/../../uploads/' . $file['unique_id'];
                
                if (is_dir($localPath)) {
                    // Delete local file
                    $this->deleteLocalFile($file['unique_id']);
                    $cleaned++;
                    $totalFreed += $file['size'];
                    
                    echo "Cleaned: {$file['original_name']} (" . number_format($file['size'] / (1024*1024), 2) . " MB)\n";
                }
            }
            
            return [
                'success' => true,
                'cleaned' => $cleaned,
                'space_freed' => $totalFreed,
                'message' => "Cleaned {$cleaned} local files, freed " . number_format($totalFreed / (1024*1024*1024), 2) . " GB"
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Cleanup failed: ' . $e->getMessage()
            ];
        }
    }
}
