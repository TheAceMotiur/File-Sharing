<?php
// Start output buffering
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Spatie\Dropbox\Client as DropboxClient;

// Clean any previous output
ob_end_clean();

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $db = getDBConnection();
    
    if (!isset($_POST['file_id'])) {
        throw new Exception('File ID is required');
    }
    
    $fileId = $_POST['file_id'];
    
    // Get file information
    $stmt = $db->prepare("
        SELECT f.*, da.access_token as dropbox_token 
        FROM file_uploads f
        LEFT JOIN dropbox_accounts da ON f.dropbox_account_id = da.id
        WHERE f.file_id = ? AND f.uploaded_by = ?
    ");
    $stmt->bind_param("si", $fileId, $_SESSION['user_id']);
    $stmt->execute();
    $file = $stmt->get_result()->fetch_assoc();
    
    if (!$file) {
        throw new Exception('File not found or you do not have permission to delete it');
    }
    
    $deletedFrom = [];
    
    // Delete from local storage
    if (!empty($file['local_path']) && file_exists($file['local_path'])) {
        if (unlink($file['local_path'])) {
            $deletedFrom[] = 'local storage';
            
            // Try to remove the directory if empty
            $dir = dirname($file['local_path']);
            if (is_dir($dir) && count(scandir($dir)) == 2) { // only . and ..
                @rmdir($dir);
            }
        }
    }
    
    // Delete from Dropbox if exists
    if (!empty($file['dropbox_path']) && !empty($file['dropbox_token'])) {
        try {
            $client = new DropboxClient($file['dropbox_token']);
            $client->delete($file['dropbox_path']);
            $deletedFrom[] = 'Dropbox';
        } catch (Exception $e) {
            // Log but don't fail - file might already be deleted from Dropbox
            error_log("Dropbox deletion error: " . $e->getMessage());
        }
    }
    
    // Delete from cache
    $cacheDir = __DIR__ . '/../cache';
    $cachePath = $cacheDir . '/' . $fileId;
    if (file_exists($cachePath)) {
        if (unlink($cachePath)) {
            $deletedFrom[] = 'cache';
        }
    }
    
    // Delete from database
    $stmt = $db->prepare("DELETE FROM file_uploads WHERE file_id = ?");
    $stmt->bind_param("s", $fileId);
    $stmt->execute();
    $deletedFrom[] = 'database';
    
    // Delete related records
    $stmt = $db->prepare("DELETE FROM file_downloads WHERE file_id = ?");
    $stmt->bind_param("s", $fileId);
    $stmt->execute();
    
    $stmt = $db->prepare("DELETE FROM file_reports WHERE file_id = ?");
    $stmt->bind_param("s", $fileId);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'File deleted successfully',
        'deleted_from' => $deletedFrom
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
