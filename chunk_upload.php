<?php
require_once __DIR__ . '/config.php';
session_start();
require_once __DIR__ . '/vendor/autoload.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Login required to upload files'
    ]);
    exit;
}

header('Content-Type: application/json');

try {
    // Handle initialization request
    if ($_SERVER['CONTENT_TYPE'] === 'application/json') {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (!$data || !isset($data['action']) || $data['action'] !== 'init' || 
            !isset($data['fileId']) || !isset($data['fileName'])) {
            throw new Exception('Invalid initialization request');
        }
        
        // Create temp directory if it doesn't exist
        $tempBaseDir = __DIR__ . '/temp';
        if (!is_dir($tempBaseDir)) {
            if (!mkdir($tempBaseDir, 0755, true)) {
                throw new Exception('Failed to create temp directory');
            }
        }
        
        // Create directory for this file's chunks
        $tempDir = $tempBaseDir . '/' . $data['fileId'];
        if (!mkdir($tempDir, 0755, true)) {
            throw new Exception('Failed to create directory for chunks');
        }
        
        // Record upload session in database for tracking
        $db = getDBConnection();
        $stmt = $db->prepare("INSERT INTO upload_sessions (
            file_id, 
            file_name, 
            total_size,
            total_chunks,
            user_id, 
            status
        ) VALUES (?, ?, ?, ?, ?, ?)");
        
        $status = 'in_progress';
        $stmt->bind_param("ssiiis", 
            $data['fileId'],
            $data['fileName'],
            $data['fileSize'],
            $data['totalChunks'],
            $_SESSION['user_id'],
            $status
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to record upload session: ' . $stmt->error);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Chunk upload initialized',
            'fileId' => $data['fileId']
        ]);
        exit;
    }
    
    // Handle chunk upload
    if (!isset($_POST['fileId']) || !isset($_POST['chunkIndex']) || !isset($_FILES['chunk'])) {
        throw new Exception('Missing required chunk upload parameters');
    }
    
    $fileId = $_POST['fileId'];
    $chunkIndex = (int)$_POST['chunkIndex'];
    $chunk = $_FILES['chunk'];
    
    // Validate chunk upload
    if ($chunk['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
        ];
        throw new Exception('Chunk upload failed: ' . 
            ($errorMessages[$chunk['error']] ?? 'Unknown error code: ' . $chunk['error']));
    }
    
    $tempDir = __DIR__ . '/temp/' . $fileId;
    if (!is_dir($tempDir)) {
        throw new Exception('Upload session not initialized properly');
    }
    
    // Save the chunk
    $chunkPath = $tempDir . '/chunk_' . $chunkIndex;
    if (!move_uploaded_file($chunk['tmp_name'], $chunkPath)) {
        throw new Exception('Failed to save chunk file');
    }
    
    // Update the session record
    $db = getDBConnection();
    $stmt = $db->prepare("UPDATE upload_sessions SET uploaded_chunks = uploaded_chunks + 1, last_activity = NOW() WHERE file_id = ?");
    $stmt->bind_param("s", $fileId);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'Chunk uploaded successfully',
        'chunkIndex' => $chunkIndex
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    
    // Log the error
    error_log('Chunk upload error: ' . $e->getMessage());
}
