<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Spatie\Dropbox\Client as DropboxClient;

try {
    $db = getDBConnection();
    
    // 1. Delete expired files (older than 180 days)
    $stmt = $db->query("SELECT fu.id, fu.unique_id, fu.original_name, fu.stored_name, fu.dropbox_path, fu.dropbox_account_id 
                       FROM file_uploads fu
                       LEFT JOIN users u ON fu.uploaded_by = u.id
                       WHERE u.premium = 0 
                       AND fu.created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)
                       AND fu.deleted_at IS NULL");
    
    // Get Dropbox clients
    $dropboxAccounts = [];
    $result = $db->query("SELECT id, access_token FROM dropbox_accounts");
    while ($row = $result->fetch_assoc()) {
        $dropboxAccounts[$row['id']] = new DropboxClient($row['access_token']);
    }
    
    $deletedCount = 0;
    while ($file = $stmt->fetch_assoc()) {
        try {
            // Delete from Dropbox if synced
            if ($file['dropbox_account_id'] && isset($dropboxAccounts[$file['dropbox_account_id']]) && $file['dropbox_path']) {
                try {
                    $dropboxAccounts[$file['dropbox_account_id']]->delete($file['dropbox_path']);
                } catch (Exception $e) {
                    echo "Warning: Could not delete from Dropbox: {$e->getMessage()}\n";
                }
            }
            
            // Delete local file
            $localPath = __DIR__ . '/../uploads/' . $file['unique_id'];
            if (is_dir($localPath)) {
                $files = glob($localPath . '/*');
                foreach ($files as $f) {
                    if (is_file($f)) unlink($f);
                }
                rmdir($localPath);
            }
            
            // Soft delete in database
            $deleteStmt = $db->prepare("UPDATE file_uploads SET deleted_at = NOW() WHERE id = ?");
            $deleteStmt->bind_param("i", $file['id']);
            $deleteStmt->execute();
            
            $deletedCount++;
            echo "Deleted expired file: {$file['original_name']}\n";
        } catch (Exception $e) {
            echo "Error deleting file {$file['original_name']}: {$e->getMessage()}\n";
            continue;
        }
    }

    // 2. Delete cache records older than 7 days
    $stmt = $db->query("SELECT file_id, cache_path FROM file_downloads 
                       WHERE last_cached < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    
    while ($row = $stmt->fetch_assoc()) {
        // Delete cached file
        if (file_exists($row['cache_path'])) {
            unlink($row['cache_path']);
        }
    }
    
    // Clear cache database records
    $db->query("DELETE FROM file_downloads 
                WHERE last_cached < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    
    // 3. Clean up orphaned database records
    $db->query("DELETE FROM file_reports WHERE file_id NOT IN (SELECT file_id FROM file_uploads)");
    $db->query("DELETE FROM file_downloads WHERE file_id NOT IN (SELECT file_id FROM file_uploads)");
    
    echo "Cleanup completed successfully\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}