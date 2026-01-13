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
            // File not found anywhere
            http_response_code(404);
            die('File not found on server');
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
            $fileContent = $this->dropboxService->downloadFromDropbox($file);
            
            if (!$fileContent) {
                throw new \Exception('Failed to download from Dropbox');
            }
            
            // Store in cache for future requests
            $cacheFile = $this->storeInCache($file, $fileContent);
            
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
            // Log error
            error_log("Dropbox download failed for file {$file['id']}: " . $e->getMessage());
            http_response_code(500);
            die('Failed to download file from Dropbox. Please try again later.');
        }
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
