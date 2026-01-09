<?php

namespace App\Models;

use App\Core\Model;

/**
 * FileUpload Model
 */
class FileUpload extends Model
{
    protected $table = 'file_uploads';
    
    /**
     * Get files by user ID
     * 
     * @param int $userId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getByUser(int $userId, int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE uploaded_by = ? AND deleted_at IS NULL 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("iii", $userId, $limit, $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get file by unique ID
     * 
     * @param string $uniqueId
     * @return array|null
     */
    public function findByUniqueId(string $uniqueId): ?array
    {
        return $this->findBy('unique_id', $uniqueId);
    }
    
    /**
     * Get files by folder ID
     * 
     * @param int $folderId
     * @return array
     */
    public function getByFolder(int $folderId): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE folder_id = ? AND deleted_at IS NULL 
                ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $folderId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Soft delete a file
     * 
     * @param int $id
     * @return bool
     */
    public function softDelete(int $id): bool
    {
        return $this->update($id, ['deleted_at' => date('Y-m-d H:i:s')]);
    }
    
    /**
     * Increment download count
     * 
     * @param int $id
     * @return bool
     */
    public function incrementDownloads(int $id): bool
    {
        $sql = "UPDATE {$this->table} 
                SET downloads = downloads + 1, 
                    last_download_at = NOW() 
                WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
    
    /**
     * Get total files count for user
     * 
     * @param int $userId
     * @return int
     */
    public function countByUser(int $userId): int
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} 
                WHERE uploaded_by = ? AND deleted_at IS NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return (int)$result['total'];
    }
    
    /**
     * Search files by name
     * 
     * @param int $userId
     * @param string $search
     * @return array
     */
    public function search(int $userId, string $search): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE uploaded_by = ? AND original_name LIKE ? AND deleted_at IS NULL 
                ORDER BY created_at DESC";
        $searchTerm = "%{$search}%";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("is", $userId, $searchTerm);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Permanently delete file from everywhere
     * 
     * @param int $id
     * @return bool
     */
    public function permanentDelete(int $id): bool
    {
        $file = $this->find($id);
        if (!$file) {
            return false;
        }
        
        $errors = [];
        
        // Delete from Dropbox FIRST (if synced)
        if (!empty($file['dropbox_path']) && !empty($file['dropbox_account_id'])) {
            if (!$this->deleteFromDropbox($file)) {
                $errors[] = 'Dropbox deletion failed';
                error_log("Failed to delete file {$id} from Dropbox: {$file['dropbox_path']}");
            } else {
                error_log("Successfully deleted file {$id} from Dropbox: {$file['dropbox_path']}");
            }
        }
        
        // Delete from local filesystem
        $uploadPath = "uploads/{$file['unique_id']}";
        if (is_dir($uploadPath)) {
            if (!$this->deleteDirectory($uploadPath)) {
                $errors[] = 'Local file deletion failed';
            }
        }
        
        // Delete chunks if any
        $chunksPath = "chunks/{$file['unique_id']}";
        if (is_dir($chunksPath)) {
            $this->deleteDirectory($chunksPath);
        }
        
        // Delete from database
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        
        if (!$success) {
            $errors[] = 'Database deletion failed';
        }
        
        // Log any errors but still return true if database deletion succeeded
        if (!empty($errors) && $success) {
            error_log("File {$id} deleted with warnings: " . implode(', ', $errors));
        }
        
        return $success;
    }
    
    /**
     * Recursively delete directory
     * 
     * @param string $dir
     * @return bool
     */
    private function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        
        return rmdir($dir);
    }
    
    /**
     * Delete file from Dropbox
     * 
     * @param array $file
     * @return bool
     */
    private function deleteFromDropbox(array $file): bool
    {
        try {
            $db = getDBConnection();
            
            // Get Dropbox account details
            $stmt = $db->prepare("SELECT * FROM dropbox_accounts WHERE id = ?");
            $stmt->bind_param("i", $file['dropbox_account_id']);
            $stmt->execute();
            $account = $stmt->get_result()->fetch_assoc();
            
            if (!$account || empty($account['access_token'])) {
                return false;
            }
            
            // Initialize Dropbox client
            $client = new \Spatie\Dropbox\Client($account['access_token']);
            
            // Delete the entire folder (not just the file)
            // Dropbox path is like /uniqueid/filename.ext
            // We want to delete /uniqueid/ folder entirely
            $folderPath = '/' . $file['unique_id'];
            
            try {
                // Try to delete the folder
                $client->delete($folderPath);
                error_log("Deleted Dropbox folder: {$folderPath}");
            } catch (\Exception $e) {
                // If folder deletion fails, try to delete just the file
                error_log("Folder deletion failed, trying file: {$e->getMessage()}");
                if (!empty($file['dropbox_path'])) {
                    $client->delete($file['dropbox_path']);
                    error_log("Deleted Dropbox file: {$file['dropbox_path']}");
                }
            }
            
            // Update account storage usage
            $stmt = $db->prepare("UPDATE dropbox_accounts SET used_storage = used_storage - ? WHERE id = ?");
            $stmt->bind_param("ii", $file['size'], $file['dropbox_account_id']);
            $stmt->execute();
            
            return true;
        } catch (\Exception $e) {
            error_log("Dropbox delete failed: " . $e->getMessage());
            return false;
        }
    }
}
