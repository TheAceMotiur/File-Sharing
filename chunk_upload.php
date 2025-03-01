<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/upload_helper.php';
session_start();

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Login required to upload files'
    ]);
    logUploadActivity('Upload attempt failed - user not logged in', 'error');
    exit;
}

// Set maximum execution time to handle large uploads
set_time_limit(3600);
ini_set('memory_limit', '512M');

// Always set proper content type for JSON responses
header('Content-Type: application/json');

try {
    // Initialize upload directory
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // For JSON initialization request
        $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
        
        if (strpos($contentType, 'application/json') !== false) {
            // Get and decode JSON input
            $inputJSON = file_get_contents('php://input');
            $data = json_decode($inputJSON, true);
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON: ' . json_last_error_msg());
            }
            
            if (!isset($data['action']) || $data['action'] !== 'init') {
                throw new Exception('Invalid action');
            }
            
            if (!isset($data['fileId']) || !isset($data['fileName']) || !isset($data['totalChunks'])) {
                throw new Exception('Missing required parameters');
            }
            
            $fileId = $data['fileId'];
            
            // Create temporary directory for chunks
            $tempDir = __DIR__ . '/temp/' . $fileId;
            if (!is_dir($tempDir)) {
                if (!mkdir($tempDir, 0755, true)) {
                    throw new Exception('Failed to create temporary directory');
                }
            }
            
            // Return success response
            echo json_encode(['success' => true, 'message' => 'Upload initialized']);
            logUploadActivity('Upload initialized for fileId: ' . $fileId, 'info');
            exit;
        }
        // For chunk upload request
        elseif (isset($_FILES['chunk'])) {
            if (!isset($_POST['fileId']) || !isset($_POST['chunkIndex'])) {
                throw new Exception('Missing required parameters for chunk upload');
            }
            
            $fileId = $_POST['fileId'];
            $chunkIndex = $_POST['chunkIndex'];
            $chunk = $_FILES['chunk'];
            
            if ($chunk['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Chunk upload failed: ' . getUploadErrorMessage($chunk['error']));
            }
            
            // Ensure temp directory exists
            $tempDir = __DIR__ . '/temp/' . $fileId;
            if (!is_dir($tempDir)) {
                if (!mkdir($tempDir, 0755, true)) {
                    throw new Exception('Failed to create temporary directory for chunk');
                }
            }
            
            // Save the chunk with low-memory approach
            $chunkPath = $tempDir . '/chunk_' . str_pad($chunkIndex, 5, '0', STR_PAD_LEFT);
            
            // Use direct move_uploaded_file which is more efficient
            if (!move_uploaded_file($chunk['tmp_name'], $chunkPath)) {
                throw new Exception('Failed to save chunk: Permission denied or disk full');
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Chunk uploaded successfully',
                'chunkIndex' => $chunkIndex
            ]);
            logUploadActivity('Chunk ' . $chunkIndex . ' uploaded for fileId: ' . $fileId, 'info');
            exit;
        } else {
            throw new Exception('Invalid request format');
        }
    } else {
        throw new Exception('Only POST requests are allowed');
    }
    
} catch (Exception $e) {
    error_log('Chunk upload error: ' . $e->getMessage());
    logUploadActivity('Chunk upload error: ' . $e->getMessage(), 'error');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}

// Helper function to get upload error messages
function getUploadErrorMessage($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
            return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
        case UPLOAD_ERR_FORM_SIZE:
            return 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form';
        case UPLOAD_ERR_PARTIAL:
            return 'The uploaded file was only partially uploaded';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing a temporary folder';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk';
        case UPLOAD_ERR_EXTENSION:
            return 'A PHP extension stopped the file upload';
        default:
            return 'Unknown upload error';
    }
}
?>
