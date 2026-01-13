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
        
        // Check cache first
        $cachedFile = $this->getCachedFile($file);
        if ($cachedFile) {
            $this->serveFromCache($cachedFile, $file);
            return;
        }
        
        // Determine download source: check if file exists locally first
        $localPath = UPLOAD_DIR . $file['unique_id'] . '/' . $file['stored_name'];
        $localExists = file_exists($localPath);
        
        // Priority: Local file if exists, otherwise Dropbox if synced
        if ($localExists) {
            // File still available locally (not synced yet or sync failed)
            $this->downloadFromLocal($file);
        } elseif ($file['storage_location'] === 'dropbox' && $file['sync_status'] === 'synced') {
            // File synced and removed from local, download from Dropbox
            $this->downloadFromDropbox($file);
        } else {
            // File not found anywhere - show detailed error
            $this->showFileNotFoundError($file);
        }
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
     * Download file from Dropbox (and cache it)
     */
    private function downloadFromDropbox($file)
    {
        try {
            error_log("Starting Dropbox download for file {$file['id']} (unique_id: {$file['unique_id']})");
            error_log("File storage_location: {$file['storage_location']}, sync_status: {$file['sync_status']}");
            
            $fileContent = $this->dropboxService->downloadFromDropbox($file);
            
            if (!$fileContent) {
                error_log("Dropbox download returned empty for file {$file['id']}");
                throw new \Exception('Download returned empty content. The file may have been removed or the access token expired.');
            }
            
            error_log("Successfully downloaded " . strlen($fileContent) . " bytes from Dropbox for file {$file['id']}");
            
            // Store in cache for future requests
            $cacheFile = $this->storeInCache($file, $fileContent);
            error_log("Cached file {$file['id']} for future downloads");
            
            // Set headers for download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
            header('Content-Length: ' . strlen($fileContent));
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: public');
            header('X-Served-From: dropbox');
            
            echo $fileContent;
            exit;
            
        } catch (\Exception $e) {
            // Log detailed error
            error_log("Dropbox download failed for file {$file['id']}: " . $e->getMessage());
            error_log("File details - ID: {$file['id']}, Unique ID: {$file['unique_id']}, Dropbox Path: {$file['dropbox_path']}, Account: {$file['dropbox_account_id']}");
            
            // Show user-friendly error
            http_response_code(500);
            echo '<!DOCTYPE html>';
            echo '<html><head><title>Download Failed</title>';
            echo '<style>body{font-family:Arial,sans-serif;max-width:600px;margin:50px auto;padding:20px;}';
            echo 'h1{color:#dc3545;}ul{line-height:1.8;}</style></head><body>';
            echo '<h1>⚠️ Download Failed</h1>';
            echo '<p>We were unable to download the file from Dropbox. This could be due to:</p>';
            echo '<ul>';
            echo '<li><strong>Expired or missing access token</strong> - The administrator needs to reconnect the Dropbox account</li>';
            echo '<li><strong>File removed from Dropbox</strong> - The file may have been deleted</li>';
            echo '<li><strong>Dropbox API connectivity issues</strong> - Temporary service disruption</li>';
            echo '<li><strong>Invalid Dropbox account configuration</strong> - Please contact the administrator</li>';
            echo '</ul>';
            echo '<p><strong>What to do:</strong></p>';
            echo '<ol>';
            echo '<li>Try again in a few minutes</li>';
            echo '<li>If the problem persists, contact the website administrator</li>';
            echo '<li>Administrators: Check the Dropbox settings page and ensure all accounts are properly connected</li>';
            echo '</ol>';
            echo '<p style="margin-top:30px;"><a href="/" style="color:#007bff;text-decoration:none;">← Return to Home</a></p>';
            echo '</body></html>';
            exit;
        }
    }
    
    /**
     * Show detailed error when file is not found
     */
    private function showFileNotFoundError($file)
    {
        http_response_code(404);
        echo '<!DOCTYPE html>';
        echo '<html><head><title>File Not Found</title>';
        echo '<style>body{font-family:Arial,sans-serif;max-width:600px;margin:50px auto;padding:20px;}' ;
        echo 'h1{color:#dc3545;}ul{line-height:1.8;}.code{background:#f5f5f5;padding:2px 6px;border-radius:3px;font-family:monospace;}</style></head><body>';
        echo '<h1>⚠️ File Not Found</h1>';
        echo '<p>The requested file could not be found in any storage location.</p>';
        echo '<p><strong>File:</strong> ' . htmlspecialchars($file['original_name']) . '</p>';
        echo '<p><strong>Status:</strong> Storage: <span class="code">' . htmlspecialchars($file['storage_location']) . '</span>, ';
        echo 'Sync: <span class="code">' . htmlspecialchars($file['sync_status']) . '</span></p>';
        echo '<p>This typically means:</p>';
        echo '<ul>';
        echo '<li>The file was marked as synced but is missing from Dropbox</li>';
        echo '<li>The file was deleted from local storage before sync completed</li>';
        echo '<li>There was a data integrity issue during file management</li>';
        echo '</ul>';
        echo '<p><strong>Administrator Action Required:</strong></p>';
        echo '<ol>';
        echo '<li>Run the repair script: <span class="code">php cron/fix_broken_dropbox_files.php</span></li>';
        echo '<li>Check the file status: <span class="code">php cron/verify_file_status.php ' . $file['id'] . '</span></li>';
        echo '<li>If the file cannot be recovered, consider removing this database entry</li>';
        echo '</ol>';
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
