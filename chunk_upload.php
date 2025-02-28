<?php
require_once __DIR__ . '/config.php';
session_start();

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

// Set maximum execution time to handle large uploads
set_time_limit(3600);

header('Content-Type: application/json');

try {
    // Initialize upload directory
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'init') {
        // Parse JSON data
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['fileId']) || !isset($data['fileName']) || !isset($data['totalChunks'])) {
            throw new Exception('Missing required parameters');
        }
        
        $fileId = $data['fileId'];
        
        // Create temporary directory for chunks
        $tempDir = __DIR__ . '/temp/' . $fileId;
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        echo json_encode(['success' => true, 'message' => 'Upload initialized']);
        exit;
    }
    
    // Handle chunk upload
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['chunk'])) {
        if (!isset($_POST['fileId']) || !isset($_POST['chunkIndex'])) {
            throw new Exception('Missing required parameters');
        }
        
        $fileId = $_POST['fileId'];
        $chunkIndex = $_POST['chunkIndex'];
        $chunk = $_FILES['chunk'];
        
        if ($chunk['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Chunk upload failed');
        }
        
        // Ensure temp directory exists
        $tempDir = __DIR__ . '/temp/' . $fileId;
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        // Save the chunk
        $chunkPath = $tempDir . '/chunk_' . str_pad($chunkIndex, 5, '0', STR_PAD_LEFT);
        if (!move_uploaded_file($chunk['tmp_name'], $chunkPath)) {
            throw new Exception('Failed to save chunk');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Chunk uploaded successfully'
        ]);
        exit;
    }
    
    throw new Exception('Invalid request');
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}
?>
