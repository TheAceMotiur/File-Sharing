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
            AND access_token IS NOT NULL
            AND access_token != ''
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
            
            // Validate access token
            if (empty($account['access_token'])) {
                $this->updateSyncStatus($fileId, 'failed');
                error_log("Dropbox sync failed for file {$fileId}: Access token is empty for account {$account['id']}. Admin needs to connect this account via OAuth.");
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
                try {
                    $deleted = $this->deleteLocalFile($file['unique_id']);
                    if ($deleted) {
                        error_log("File {$fileId} synced to Dropbox. Local files deleted successfully.");
                    } else {
                        error_log("File {$fileId} synced to Dropbox. WARNING: Local file deletion failed - directory may not exist or may not be empty.");
                    }
                } catch (\Exception $e) {
                    error_log("File {$fileId} synced to Dropbox. ERROR deleting local files: " . $e->getMessage());
                }
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
            error_log("Directory does not exist: {$dir}");
            return false;
        }
        
        try {
            // Delete all files in directory (including hidden files)
            $files = glob($dir . '/{,.}[!.,!..]*', GLOB_MARK|GLOB_BRACE);
            if ($files === false) {
                $files = [];
            }
            
            $deletedCount = 0;
            foreach ($files as $file) {
                if (is_file($file)) {
                    if (unlink($file)) {
                        $deletedCount++;
                    } else {
                        error_log("Failed to delete file: {$file}");
                    }
                } elseif (is_dir($file)) {
                    // Recursively delete subdirectories
                    $this->deleteDirectory($file);
                }
            }
            
            // Remove directory
            if (rmdir($dir)) {
                error_log("Successfully deleted directory: {$dir} (removed {$deletedCount} files)");
                return true;
            } else {
                // Check if directory still has contents
                $remaining = scandir($dir);
                $remaining = array_diff($remaining, ['.', '..']);
                error_log("Failed to remove directory: {$dir}. Remaining items: " . implode(', ', $remaining));
                return false;
            }
        } catch (\Exception $e) {
            error_log("Exception while deleting directory {$dir}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Recursively delete a directory
     * 
     * @param string $dir
     * @return bool
     */
    private function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = glob($dir . '/{,.}[!.,!..]*', GLOB_MARK|GLOB_BRACE);
        if ($files === false) {
            $files = [];
        }
        
        foreach ($files as $file) {
            if (is_dir($file)) {
                $this->deleteDirectory($file);
            } else {
                unlink($file);
            }
        }
        
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
            // Validate file has Dropbox info
            if (empty($file['dropbox_account_id']) || empty($file['dropbox_path'])) {
                error_log("Dropbox download failed for file {$file['id']}: Missing Dropbox account or path");
                return false;
            }
            
            // Get Dropbox account
            $stmt = $this->db->prepare("SELECT * FROM dropbox_accounts WHERE id = ?");
            $stmt->bind_param("i", $file['dropbox_account_id']);
            $stmt->execute();
            $account = $stmt->get_result()->fetch_assoc();
            
            if (!$account) {
                error_log("Dropbox download failed for file {$file['id']}: Dropbox account {$file['dropbox_account_id']} not found");
                return false;
            }
            
            // Check if access token is empty
            if (empty($account['access_token'])) {
                error_log("Dropbox download failed for file {$file['id']}: Access token is empty for account {$account['id']}. Admin needs to connect this account via OAuth.");
                return false;
            }
            
            // Proactively refresh token if expired or expiring soon (within 1 hour)
            $needsRefresh = false;
            if (isset($account['token_expires_at'])) {
                $expiresAt = strtotime($account['token_expires_at']);
                $oneHourFromNow = time() + 3600;
                
                if ($expiresAt < $oneHourFromNow) {
                    $needsRefresh = true;
                    error_log("Token for account {$account['id']} expires soon or has expired. Refreshing in background...");
                }
            }
            
            // Refresh token in background if needed
            if ($needsRefresh) {
                // Try to refresh the token
                if ($this->refreshAccessToken($account['id'])) {
                    // Get updated account with new token
                    $stmt = $this->db->prepare("SELECT * FROM dropbox_accounts WHERE id = ?");
                    $stmt->bind_param("i", $file['dropbox_account_id']);
                    $stmt->execute();
                    $account = $stmt->get_result()->fetch_assoc();
                    error_log("Token refreshed successfully for account {$account['id']}");
                } else {
                    error_log("Background token refresh failed for account {$account['id']}, attempting download anyway...");
                }
            }
            
            // Download from Dropbox
            error_log("Attempting Dropbox download for file {$file['id']} ({$file['original_name']}) from path: {$file['dropbox_path']}");
            
            $client = new DropboxClient($account['access_token']);
            $resource = $client->download($file['dropbox_path']);
            
            if ($resource === false || $resource === null) {
                error_log("Dropbox download returned empty resource for file {$file['id']}");
                return false;
            }
            
            // Convert resource stream to string content
            $content = stream_get_contents($resource);
            
            // Close the resource
            if (is_resource($resource)) {
                fclose($resource);
            }
            
            if ($content === false || empty($content)) {
                error_log("Dropbox download: Failed to read stream content for file {$file['id']}");
                return false;
            }
            
            $downloadedSize = strlen($content);
            error_log("Dropbox download successful for file {$file['id']}: " . number_format($downloadedSize / (1024*1024), 2) . " MB");
            
            return $content;
            
        } catch (\Spatie\Dropbox\Exceptions\BadRequest $e) {
            $errorMsg = $e->getMessage();
            error_log("Dropbox BadRequest for file {$file['id']}: {$errorMsg}");
            
            // If file not found, mark as failed to prevent repeated download attempts
            if (strpos($errorMsg, 'path/not_found') !== false) {
                error_log("File {$file['id']} not found in Dropbox (path: {$file['dropbox_path']}). Marking as failed.");
                $stmt = $this->db->prepare("UPDATE file_uploads SET sync_status = 'failed', storage_location = 'local' WHERE id = ?");
                $stmt->bind_param("i", $file['id']);
                $stmt->execute();
            }
            return false;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $statusCode = $response ? $response->getStatusCode() : 'unknown';
            $body = $response ? $response->getBody()->getContents() : 'no response';
            error_log("Dropbox ClientException for file {$file['id']} (HTTP {$statusCode}): " . $e->getMessage());
            error_log("Response body: " . $body);
            
            // If 401 (unauthorized), try to refresh token
            if ($statusCode == 401 && !empty($file['dropbox_account_id'])) {
                error_log("Attempting to refresh token for account {$file['dropbox_account_id']}");
                $this->refreshAccessToken($file['dropbox_account_id']);
            }
            return false;
        } catch (\Exception $e) {
            error_log("Dropbox download exception for file {$file['id']}: " . get_class($e) . " - " . $e->getMessage());
            if (method_exists($e, 'getTraceAsString')) {
                error_log("Stack trace: " . $e->getTraceAsString());
            }
            return false;
        }
    }
    
    /**
     * Refresh Dropbox access token
     * 
     * @param int $accountId
     * @return bool
     */
    private function refreshAccessToken(int $accountId): bool
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM dropbox_accounts WHERE id = ?");
            $stmt->bind_param("i", $accountId);
            $stmt->execute();
            $account = $stmt->get_result()->fetch_assoc();
            
            if (!$account || empty($account['refresh_token'])) {
                error_log("Cannot refresh token for account {$accountId}: No refresh token available");
                return false;
            }
            
            // Get app credentials from the account itself (not from dropbox_settings)
            $appKey = $account['app_key'] ?? null;
            $appSecret = $account['app_secret'] ?? null;
            
            if (!$appKey || !$appSecret) {
                error_log("Cannot refresh token for account {$accountId}: Missing app_key or app_secret");
                return false;
            }
            
            // Request new access token
            $client = new \GuzzleHttp\Client();
            $response = $client->post('https://api.dropbox.com/oauth2/token', [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $account['refresh_token'],
                    'client_id' => $appKey,
                    'client_secret' => $appSecret,
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            if (isset($data['access_token'])) {
                // Update access token
                $expiresAt = date('Y-m-d H:i:s', time() + $data['expires_in']);
                $stmt = $this->db->prepare("UPDATE dropbox_accounts SET access_token = ?, token_expires_at = ? WHERE id = ?");
                $stmt->bind_param("ssi", $data['access_token'], $expiresAt, $accountId);
                $stmt->execute();
                
                error_log("Successfully refreshed token for account {$accountId}, expires at {$expiresAt}");
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            error_log("Token refresh failed for account {$accountId}: " . $e->getMessage());
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
                AND storage_location = 'local'
                LIMIT 50
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            $files = $result->fetch_all(MYSQLI_ASSOC);
            
            $cleaned = 0;
            $totalFreed = 0;
            
            foreach ($files as $file) {
                $localPath = UPLOAD_DIR . $file['unique_id'];
                
                if (is_dir($localPath)) {
                    // Delete local file
                    if ($this->deleteLocalFile($file['unique_id'])) {
                        $cleaned++;
                        $totalFreed += $file['size'];
                        echo "Cleaned: {$file['original_name']} (" . number_format($file['size'] / (1024*1024), 2) . " MB)\n";
                    } else {
                        echo "Failed to clean: {$file['original_name']}\n";
                    }
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
