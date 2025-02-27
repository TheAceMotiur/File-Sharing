<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Spatie\Dropbox\Client as DropboxClient;

try {
    $db = getDBConnection();
    
    // 1. Delete expired files (older than 180 days)
    $stmt = $db->query("SELECT fu.file_id, fu.file_name, fu.upload_status 
                       FROM file_uploads fu
                       LEFT JOIN users u ON fu.uploaded_by = u.id
                       WHERE u.premium = 0 
                       AND (fu.expires_at < CURRENT_TIMESTAMP 
                            OR fu.created_at < DATE_SUB(NOW(), INTERVAL 180 DAY))
                       AND fu.upload_status = 'completed'");
    
    // Get Dropbox client
    $dropbox = $db->query("SELECT access_token FROM dropbox_accounts LIMIT 1")->fetch_assoc();
    $client = new DropboxClient($dropbox['access_token']);
    
    while ($file = $stmt->fetch_assoc()) {
        try {
            // Delete from Dropbox
            $dropboxPath = "/{$file['file_id']}";
            $client->delete($dropboxPath);
            
            // Delete from database
            $db->query("DELETE FROM file_uploads WHERE file_id = '{$file['file_id']}'");
            
            // Delete any cache files
            $cachePath = __DIR__ . '/../cache/' . $file['file_id'];
            if (file_exists($cachePath)) {
                unlink($cachePath);
            }
            
            echo "Deleted expired file: {$file['file_name']}\n";
        } catch (Exception $e) {
            echo "Error deleting file {$file['file_name']}: {$e->getMessage()}\n";
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