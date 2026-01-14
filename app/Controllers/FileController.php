<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\FileUpload;
use App\Services\DropboxSyncService;

/**
 * File Controller
 * Handles file download, info, and report
 */
class FileController extends Controller
{
    private $fileModel;
    private $dropboxService;
    
    public function __construct()
    {
        $this->fileModel = new FileUpload();
        $this->dropboxService = new DropboxSyncService();
    }
    
    /**
     * Download file
     */
    public function download($uniqueId)
    {
        $file = $this->fileModel->findByUniqueId($uniqueId);
        
        if (!$file) {
            http_response_code(404);
            die('File not found');
        }
        
        // Check if direct download
        $download = $this->get('download') || $this->get('action') === 'download';
        
        if ($download) {
            $this->handleDownload($file);
        } else {
            // Show file info page
            $this->info($uniqueId);
        }
    }
    
    /**
     * Show file info
     */
    public function info($uniqueId)
    {
        $file = $this->fileModel->findByUniqueId($uniqueId);
        
        if (!$file) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404 - File Not Found']);
            return;
        }
        
        $data = [
            'title' => 'File Information',
            'file' => $file,
            'downloadUrl' => '/download/' . $uniqueId . '?download=1',
            'waitUrl' => '/wait/' . $uniqueId,
            'fileSize' => formatFileSize($file['size']),
            'uploadDate' => date('F j, Y', strtotime($file['created_at']))
        ];
        
        $this->view('file/info', $data);
    }
    
    /**
     * Show wait page before download
     */
    public function wait($uniqueId)
    {
        $file = $this->fileModel->findByUniqueId($uniqueId);
        
        if (!$file) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404 - File Not Found']);
            return;
        }
        
        $data = [
            'title' => 'Please Wait',
            'file' => $file,
            'downloadUrl' => '/download/' . $uniqueId . '?download=1'
        ];
        
        $this->view('file/wait', $data);
    }
    
    /**
     * Handle actual file download
     */
    private function handleDownload($file)
    {
        // Increment download count
        $this->fileModel->incrementDownloads($file['id']);
        
        // Step 1: Check cache first
        $cachedFile = $this->getCachedFile($file);
        if ($cachedFile) {
            error_log("Serving file {$file['id']} from cache");
            $this->serveFromCache($cachedFile, $file);
            return;
        }
        
        // Step 2: Try Dropbox if file is synced
        if ($file['storage_location'] === 'dropbox' && $file['sync_status'] === 'synced') {
            error_log("File {$file['id']} not in cache, trying Dropbox...");
            $fileContent = $this->dropboxService->downloadFromDropbox($file);
            
            if ($fileContent) {
                // Download successful, cache it and serve
                error_log("Downloaded file {$file['id']} from Dropbox, caching and serving...");
                $this->storeInCache($file, $fileContent);
                $this->serveContent($fileContent, $file, 'dropbox');
                return;
            }
            
            // Capture the error from Dropbox service
            $dropboxError = $this->dropboxService->getLastError();
            
            error_log("Failed to download file {$file['id']} from Dropbox, checking local as fallback...");
            
            // Dropbox failed, check local as fallback
            $localPath = UPLOAD_DIR . $file['unique_id'] . '/' . $file['stored_name'];
            if (file_exists($localPath)) {
                error_log("Found file {$file['id']} in local storage as fallback, re-syncing to Dropbox...");
                
                // Mark as pending to trigger re-sync
                $db = getDBConnection();
                $stmt = $db->prepare("UPDATE file_uploads SET sync_status = 'pending', storage_location = 'local' WHERE id = ?");
                $stmt->bind_param("i", $file['id']);
                $stmt->execute();
                
                // Serve from local and let cron re-sync
                $fileContent = file_get_contents($localPath);
                if ($fileContent !== false) {
                    $this->storeInCache($file, $fileContent);
                    $this->serveContent($fileContent, $file, 'local-fallback');
                    return;
                }
            }
        }
        
        // Step 3: Try local upload folder
        $localPath = UPLOAD_DIR . $file['unique_id'] . '/' . $file['stored_name'];
        if (file_exists($localPath)) {
            error_log("Found file {$file['id']} in local storage, caching and serving...");
            $fileContent = file_get_contents($localPath);
            
            if ($fileContent !== false) {
                $this->storeInCache($file, $fileContent);
                $this->serveContent($fileContent, $file, 'local');
                return;
            }
        }
        
        // File not found anywhere
        error_log("File {$file['id']} not found in cache, Dropbox, or local storage");
        $dropboxError = $this->dropboxService->getLastError();
        $this->showFileNotFoundError($file, $dropboxError);
    }
    
    /**
     * Serve file content directly
     */
    private function serveContent($content, $file, $source = 'unknown')
    {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: public');
        header('X-Served-From: ' . $source);
        
        echo $content;
        exit;
    }
    
    /**
     * Check if file exists in cache
     */
    private function getCachedFile($file)
    {
        $cacheDir = __DIR__ . '/../../cache/';
        $cacheFile = $cacheDir . $file['unique_id'];
        
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        if (file_exists($cacheFile)) {
            // Verify cache file size matches
            if (filesize($cacheFile) === (int)$file['size']) {
                return $cacheFile;
            } else {
                // Cache corrupted, delete it
                unlink($cacheFile);
            }
        }
        
        return null;
    }
    
    /**
     * Serve file from cache
     */
    private function serveFromCache($cacheFile, $file)
    {
        // Set headers for download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
        header('Content-Length: ' . filesize($cacheFile));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: public');
        header('Accept-Ranges: bytes');
        header('X-Served-From: cache');
        
        // Stream file with range support
        $this->streamFile($cacheFile);
        exit;
    }
    
    /**
     * Store file in cache
     */
    private function storeInCache($file, $content)
    {
        $cacheDir = __DIR__ . '/../../cache/';
        $cacheFile = $cacheDir . $file['unique_id'];
        
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        // Write to cache
        file_put_contents($cacheFile, $content);
        
        return $cacheFile;
    }
    
    /**
     * Download file from Dropbox (simplified - returns content only)
     */
    private function downloadFromDropbox($file)
    {
        try {
            error_log("=== Dropbox Download Debug ===");
            error_log("File ID: {$file['id']} ({$file['original_name']})");
            error_log("Unique ID: {$file['unique_id']}");
            error_log("Storage: {$file['storage_location']}, Sync: {$file['sync_status']}");
            error_log("Dropbox Path: {$file['dropbox_path']}");
            error_log("Account ID: {$file['dropbox_account_id']}");
            
            $fileContent = $this->dropboxService->downloadFromDropbox($file);
            
            if ($fileContent === false) {
                error_log("Dropbox download returned FALSE for file {$file['id']}");
                return false;
            }
            
            if ($fileContent === null) {
                error_log("Dropbox download returned NULL for file {$file['id']}");
                return false;
            }
            
            $size = strlen($fileContent);
            error_log("Successfully downloaded {$size} bytes from Dropbox for file {$file['id']}");
            return $fileContent;
            
        } catch (\Exception $e) {
            error_log("Exception during Dropbox download for file {$file['id']}: " . $e->getMessage());
            error_log("Exception trace: " . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * Show detailed error when file is not found
     */
    private function showFileNotFoundError($file, $dropboxError = null)
    {
        http_response_code(404);
        echo '<!DOCTYPE html>';
        echo '<html><head><title>File Download Error</title>';
        echo '<style>body{font-family:Arial,sans-serif;max-width:700px;margin:50px auto;padding:20px;}' ;
        echo 'h1{color:#dc3545;}ul{line-height:1.8;}.code{background:#f5f5f5;padding:2px 6px;border-radius:3px;font-family:monospace;}';
        echo '.error-box{background:#fff3cd;border-left:4px solid #ffc107;padding:15px;margin:20px 0;border-radius:4px;}';
        echo '.error-title{font-weight:bold;color:#856404;margin-bottom:8px;}';
        echo '.error-message{color:#856404;}</style></head><body>';
        echo '<h1>⚠️ File Download Error</h1>';
        echo '<p>The requested file could not be downloaded.</p>';
        echo '<p><strong>File:</strong> ' . htmlspecialchars($file['original_name']) . '</p>';
        echo '<p><strong>Status:</strong> Storage: <span class="code">' . htmlspecialchars($file['storage_location']) . '</span>, ';
        echo 'Sync: <span class="code">' . htmlspecialchars($file['sync_status']) . '</span></p>';
        
        // Show specific Dropbox error if available
        if ($dropboxError) {
            echo '<div class="error-box">';
            echo '<div class="error-title">🔴 Specific Error:</div>';
            echo '<div class="error-message">' . $dropboxError . '</div>';
            echo '</div>';
            
            // Show helpful instructions based on error type
            if (strpos($dropboxError, 'token') !== false || strpos($dropboxError, 'Access token') !== false) {
                echo '<p><strong>This is a Dropbox authentication issue.</strong> The access token may be expired or invalid.</p>';
                echo '<p><strong>Administrator Actions:</strong></p>';
                echo '<ol>';
                echo '<li>Run the token refresh script: <span class="code">php cron/refresh_dropbox_tokens.php</span></li>';
                echo '<li>If refresh fails, re-authenticate the Dropbox account in Admin → Dropbox Settings</li>';
                echo '<li>Try downloading the file again after token refresh</li>';
                echo '</ol>';
            } else if (strpos($dropboxError, 'not found') !== false || strpos($dropboxError, 'path') !== false) {
                echo '<p><strong>This is a file location issue.</strong> The file may have been moved or deleted from Dropbox.</p>';
                echo '<p><strong>Administrator Actions:</strong></p>';
                echo '<ol>';
                echo '<li>Run the repair script: <span class="code">php cron/fix_broken_dropbox_files.php</span></li>';
                echo '<li>Check file status: <span class="code">php cron/verify_file_status.php ' . $file['id'] . '</span></li>';
                echo '<li>Consider re-uploading the file if original is available</li>';
                echo '</ol>';
            } else {
                echo '<p><strong>Administrator Actions:</strong></p>';
                echo '<ol>';
                echo '<li>Check error logs for more details</li>';
                echo '<li>Verify Dropbox account connection in Admin panel</li>';
                echo '<li>Run diagnostic: <span class="code">php cron/verify_file_status.php ' . $file['id'] . '</span></li>';
                echo '</ol>';
            }
        } else {
            echo '<p>Possible causes:</p>';
            echo '<ul>';
            echo '<li>The file was marked as synced but is missing from Dropbox</li>';
            echo '<li>The file was deleted from local storage before sync completed</li>';
            echo '<li>There was a data integrity issue during file management</li>';
            echo '</ul>';
            echo '<p><strong>Administrator Actions:</strong></p>';
            echo '<ol>';
            echo '<li>Run the repair script: <span class="code">php cron/fix_broken_dropbox_files.php</span></li>';
            echo '<li>Check the file status: <span class="code">php cron/verify_file_status.php ' . $file['id'] . '</span></li>';
            echo '<li>If the file cannot be recovered, consider removing this database entry</li>';
            echo '</ol>';
        }
        
        echo '<p style="margin-top:30px;"><a href="/" style="color:#007bff;text-decoration:none;">← Return to Home</a></p>';
        echo '</body></html>';
        exit;
    }
    
    /**
     * Download file from local storage (and cache it)
     */
    private function downloadFromLocal($file)
    {
        $filePath = UPLOAD_DIR . $file['unique_id'] . '/' . $file['stored_name'];
        
        if (!file_exists($filePath)) {
            http_response_code(404);
            die('File not found on server');
        }
        
        // Store in cache for future requests
        $content = file_get_contents($filePath);
        if ($content !== false) {
            $this->storeInCache($file, $content);
        }
        
        // Set headers for download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: public');
        header('Accept-Ranges: bytes');
        header('X-Served-From: local');
        
        // Handle range requests for resume support
        $this->streamFile($filePath);
        exit;
    }
    
    /**
     * Stream file with range support
     */
    private function streamFile($filePath)
    {
        $fileSize = filesize($filePath);
        $start = 0;
        $end = $fileSize - 1;
        
        // Handle range request
        if (isset($_SERVER['HTTP_RANGE'])) {
            list($unit, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
            if ($unit == 'bytes') {
                list($start, $end) = explode('-', $range);
                $start = max(intval($start), 0);
                $end = ($end) ? min(intval($end), $fileSize - 1) : $fileSize - 1;
                
                http_response_code(206);
                header("Content-Range: bytes $start-$end/$fileSize");
            }
        }
        
        $length = $end - $start + 1;
        header("Content-Length: $length");
        
        // Stream the file
        $fp = fopen($filePath, 'rb');
        fseek($fp, $start);
        
        $buffer = 1024 * 1024; // 1MB chunks
        while (!feof($fp) && ($pos = ftell($fp)) <= $end) {
            if ($pos + $buffer > $end) {
                $buffer = $end - $pos + 1;
            }
            echo fread($fp, $buffer);
            flush();
        }
        
        fclose($fp);
    }
    
    /**
     * Report file
     */
    public function report($uniqueId)
    {
        $file = $this->fileModel->findByUniqueId($uniqueId);
        
        if (!$file) {
            return $this->json(['error' => 'File not found'], 404);
        }
        
        if ($this->isPost()) {
            try {
                $reason = trim($this->post('reason'));
                $email = trim($this->post('email'));
                
                if (empty($reason)) {
                    return $this->json(['error' => 'Reason is required'], 400);
                }
                
                $reportModel = $this->model('Report');
                $reportId = $reportModel->createReport([
                    'file_id' => $file['id'],
                    'reason' => $reason,
                    'reporter_email' => $email ?: null
                ]);
                
                if ($reportId) {
                    return $this->json(['success' => true, 'message' => 'Report submitted']);
                } else {
                    return $this->json(['error' => 'Failed to submit report'], 500);
                }
            } catch (\Exception $e) {
                error_log('Report submission error: ' . $e->getMessage());
                return $this->json(['error' => 'Database error: ' . $e->getMessage()], 500);
            }
        }
        
        $this->view('file/report', [
            'title' => 'Report File',
            'file' => $file
        ]);
    }
}
