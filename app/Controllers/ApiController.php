<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\FileUpload;
use App\Models\Folder;
use App\Services\DropboxSyncService;

/**
 * API Controller
 * Handles API endpoints
 */
class ApiController extends Controller
{
    /**
     * Handle file upload
     */
    public function upload()
    {
        if (!$this->isPost()) {
            return $this->json(['error' => 'Method not allowed'], 405);
        }
        
        if (!isset($_FILES['file'])) {
            return $this->json(['error' => 'No file uploaded'], 400);
        }
        
        $file = $_FILES['file'];
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'Upload blocked by extension'
            ];
            $errorMsg = $errorMessages[$file['error']] ?? 'Unknown upload error';
            return $this->json(['error' => $errorMsg], 400);
        }
        
        // Check file size
        if ($file['size'] > MAX_FILE_SIZE) {
            return $this->json(['error' => 'File too large. Maximum size: ' . (MAX_FILE_SIZE / 1024 / 1024) . ' MB'], 400);
        }
        
        // Generate unique ID
        $uniqueId = bin2hex(random_bytes(6));
        $uploadDir = UPLOAD_DIR . $uniqueId;
        
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return $this->json(['error' => 'Failed to create upload directory'], 500);
            }
        }
        
        $originalName = basename($file['name']);
        $storedName = $originalName;
        $destination = $uploadDir . '/' . $storedName;
        
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return $this->json(['error' => 'Failed to save file'], 500);
        }
        
        // Save to database
        $fileModel = new FileUpload();
        $folderId = $this->post('folder_id');
        
        $fileId = $fileModel->create([
            'uploaded_by' => $_SESSION['user_id'],
            'unique_id' => $uniqueId,
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'size' => $file['size'],
            'mime_type' => $file['type'] ?: 'application/octet-stream',
            'folder_id' => $folderId ? (int)$folderId : null,
            'storage_location' => 'local',
            'sync_status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($fileId) {
            // Trigger Dropbox sync asynchronously
            $config = require __DIR__ . '/../../config/app.php';
            if ($config['dropbox']['auto_sync']) {
                $this->triggerDropboxSync($fileId);
            }
            
            return $this->json([
                'success' => true,
                'file' => [
                    'id' => $fileId,
                    'unique_id' => $uniqueId,
                    'name' => $originalName,
                    'size' => $file['size'],
                    'url' => '/download/' . $uniqueId
                ]
            ]);
        }
        
        return $this->json(['error' => 'Failed to save file info'], 500);
    }
    
    /**
     * Trigger Dropbox sync (async or immediate)
     */
    private function triggerDropboxSync(int $fileId): void
    {
        // Sync in background using ignore_user_abort
        ignore_user_abort(true);
        
        // Try to sync immediately (will be handled by cron if fails)
        try {
            $syncService = new DropboxSyncService();
            $syncService->syncFile($fileId);
        } catch (\Exception $e) {
            error_log("Immediate sync failed for file {$fileId}: " . $e->getMessage());
        }
    }
    
    /**
     * Delete file
     */
    public function delete()
    {
        if (!$this->isPost()) {
            return $this->json(['error' => 'Method not allowed'], 405);
        }
        
        $data = $this->getJsonInput();
        $fileId = $data['file_id'] ?? null;
        
        if (!$fileId) {
            return $this->json(['error' => 'File ID required'], 400);
        }
        
        $fileModel = new FileUpload();
        $file = $fileModel->find($fileId);
        
        if (!$file || $file['uploaded_by'] != $_SESSION['user_id']) {
            return $this->json(['error' => 'File not found'], 404);
        }
        
        // Permanent delete - removes from filesystem, Dropbox, and database
        if ($fileModel->permanentDelete($fileId)) {
            return $this->json(['success' => true]);
        }
        
        return $this->json(['error' => 'Failed to delete'], 500);
    }
    
    /**
     * Rename file
     */
    public function rename()
    {
        if (!$this->isPost()) {
            return $this->json(['error' => 'Method not allowed'], 405);
        }
        
        $data = $this->getJsonInput();
        $fileId = $data['file_id'] ?? null;
        $newName = $data['new_name'] ?? null;
        
        if (!$fileId || !$newName) {
            return $this->json(['error' => 'File ID and new name required'], 400);
        }
        
        $fileModel = new FileUpload();
        $file = $fileModel->find($fileId);
        
        if (!$file || $file['uploaded_by'] != $_SESSION['user_id']) {
            return $this->json(['error' => 'File not found'], 404);
        }
        
        if ($fileModel->update($fileId, ['original_name' => $newName])) {
            return $this->json(['success' => true]);
        }
        
        return $this->json(['error' => 'Failed to rename'], 500);
    }
    
    /**
     * Get folders
     */
    public function folders()
    {
        if ($this->isGet()) {
            $folderModel = new Folder();
            $folders = $folderModel->getByUser($_SESSION['user_id']);
            
            return $this->json(['success' => true, 'folders' => $folders]);
        }
        
        return $this->json(['error' => 'Method not allowed'], 405);
    }
    
    /**
     * Create folder
     */
    public function createFolder()
    {
        if (!$this->isPost()) {
            return $this->json(['error' => 'Method not allowed'], 405);
        }
        
        try {
            $data = $this->getJsonInput();
            $name = $data['name'] ?? null;
            
            if (!$name) {
                return $this->json(['error' => 'Folder name required'], 400);
            }
            
            $folderModel = new Folder();
            $folderId = $folderModel->create([
                'created_by' => $_SESSION['user_id'],
                'name' => $name,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            if ($folderId) {
                return $this->json([
                    'success' => true,
                    'folder' => [
                        'id' => $folderId,
                        'name' => $name,
                        'created_at' => date('Y-m-d H:i:s')
                    ]
                ]);
            }
            
            return $this->json(['error' => 'Failed to create folder'], 500);
        } catch (\Exception $e) {
            error_log('Create folder error: ' . $e->getMessage());
            return $this->json(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Rename folder
     */
    public function renameFolder()
    {
        $data = $this->getJsonInput();
        $folderId = $data['folder_id'] ?? null;
        $name = $data['name'] ?? null;
        
        if (!$folderId || !$name) {
            return $this->json(['error' => 'Folder ID and name required'], 400);
        }
        
        $folderModel = new Folder();
        
        // Check if folder belongs to user
        if (!$folderModel->belongsToUser((int)$folderId, $_SESSION['user_id'])) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }
        
        $success = $folderModel->update((int)$folderId, [
            'name' => $name,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($success) {
            return $this->json(['success' => true]);
        }
        
        return $this->json(['error' => 'Failed to rename folder'], 500);
    }
    
    /**
     * Delete folder
     */
    public function deleteFolder()
    {
        try {
            $data = $this->getJsonInput();
            $folderId = $data['folder_id'] ?? null;
            
            if (!$folderId) {
                return $this->json(['error' => 'Folder ID required'], 400);
            }
            
            $folderModel = new Folder();
            
            // Check if folder belongs to user
            if (!$folderModel->belongsToUser((int)$folderId, $_SESSION['user_id'])) {
                return $this->json(['error' => 'Unauthorized'], 403);
            }
            
            // Check if folder has files
            $folder = $folderModel->getWithFileCount((int)$folderId);
            if ($folder && (int)$folder['file_count'] > 0) {
                return $this->json(['error' => 'Folder contains ' . $folder['file_count'] . ' file(s). Please delete or move them first.'], 400);
            }
            
            $success = $folderModel->delete((int)$folderId);
            
            if ($success) {
                return $this->json(['success' => true]);
            }
            
            return $this->json(['error' => 'Failed to delete folder'], 500);
        } catch (\Exception $e) {
            error_log('Delete folder error: ' . $e->getMessage());
            return $this->json(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }
}
