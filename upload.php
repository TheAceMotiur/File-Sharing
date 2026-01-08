<?php
// Start output buffering to prevent any output before JSON
ob_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';
use Spatie\Dropbox\Client as DropboxClient;

// Clean any previous output
ob_end_clean();

// Set JSON response header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Login required to upload files'
    ]);
    exit;
}

// Helper function to remove chunk directory
function removeChunkDirectory($chunksDir) {
    if (is_dir($chunksDir)) {
        $files = glob($chunksDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($chunksDir);
    }
}

// Handle chunk uploads
if (isset($_POST['chunk'])) {
    $chunksDir = '';
    try {
        $chunk = $_POST['chunk'];
        $totalChunks = $_POST['totalChunks'];
        $currentChunk = $_POST['currentChunk'];
        $fileId = $_POST['fileId'];
        $fileName = $_POST['fileName'];
        $fileSize = $_POST['totalSize'];

        // Validate total file size (2GB limit)
        if ($fileSize > 2 * 1024 * 1024 * 1024) {
            throw new Exception('File size exceeds 2 GB limit');
        }

        // Create temporary directory for chunks if not exists
        $chunksDir = __DIR__ . '/chunks/' . $fileId;
        if (!file_exists($chunksDir)) {
            mkdir($chunksDir, 0777, true);
        }

        // Save chunk
        $chunkFile = $chunksDir . '/' . $currentChunk;
        file_put_contents($chunkFile, base64_decode($chunk));

        // Check if all chunks are received
        if ($currentChunk == $totalChunks - 1) {
            // Combine chunks
            $finalFile = fopen($chunksDir . '/' . $fileName, 'wb');
            
            try {
                for ($i = 0; $i < $totalChunks; $i++) {
                    $chunkContent = file_get_contents($chunksDir . '/' . $i);
                    if ($chunkContent === false) {
                        throw new Exception('Failed to read chunk ' . $i);
                    }
                    fwrite($finalFile, $chunkContent);
                    unlink($chunksDir . '/' . $i);
                }
                fclose($finalFile);

                // Get file mime type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $chunksDir . '/' . $fileName);

                // Validate file type
                $allowedTypes = [
                    // Images
                    'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
                    // Audio
                    'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4', 'audio/aac',
                    // Video
                    'video/mp4', 'video/mpeg', 'video/webm', 'video/quicktime', 'video/x-msvideo',
                    // Archives
                    'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed',
                    'application/x-tar', 'application/gzip',
                    // Documents
                    'application/pdf', 'image/vnd.djvu',
                    // Other media
                    'application/vnd.apple.mpegurl', 'application/x-mpegurl'
                ];

                if (!in_array($mimeType, $allowedTypes)) {
                    // Cleanup and throw error
                    removeChunkDirectory($chunksDir);
                    throw new Exception('File type not allowed');
                }

                // Save to local storage first
                $db = getDBConnection();
                
                // Create uploads directory if not exists
                $uploadsDir = __DIR__ . '/uploads/' . $fileId;
                if (!file_exists($uploadsDir)) {
                    mkdir($uploadsDir, 0777, true);
                }
                
                // Move file to permanent local storage
                $localPath = $uploadsDir . '/' . $fileName;
                rename($chunksDir . '/' . $fileName, $localPath);

                // Save to database with local storage
                $folderId = isset($_POST['folder_id']) && $_POST['folder_id'] !== '' ? (int)$_POST['folder_id'] : null;
                
                $stmt = $db->prepare("INSERT INTO file_uploads (
                    file_id, file_name, size, upload_status, local_path, 
                    storage_location, uploaded_by, folder_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                
                $status = 'completed';
                $storageLocation = 'local';
                
                if ($folderId) {
                    $stmt->bind_param("ssisssii", 
                        $fileId,
                        $fileName,
                        $fileSize,
                        $status,
                        $localPath,
                        $storageLocation,
                        $_SESSION['user_id'],
                        $folderId
                    );
                } else {
                    $stmt->bind_param("ssisssi", 
                        $fileId,
                        $fileName,
                        $fileSize,
                        $status,
                        $localPath,
                        $storageLocation,
                        $_SESSION['user_id']
                    );
                }
                $stmt->execute();

                // Cleanup chunks directory
                removeChunkDirectory($chunksDir);

                echo json_encode([
                    'success' => true,
                    'downloadLink' => "https://" . $_SERVER['HTTP_HOST'] . "/download/" . $fileId
                ]);
                exit;
            } catch (Exception $e) {
                if (is_resource($finalFile)) {
                    fclose($finalFile);
                }
                removeChunkDirectory($chunksDir);
                throw $e;
            }
        }

        echo json_encode(['success' => true, 'chunk' => $currentChunk]);
        exit;

    } catch (Exception $e) {
        // Clean up on error
        if (!empty($chunksDir)) {
            removeChunkDirectory($chunksDir);
        }
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Handle regular file uploads (from dashboard)
try {
    if (!isset($_FILES['files'])) {
        throw new Exception('No files uploaded');
    }

    $uploadedFiles = [];
    $errors = [];
    
    // Get folder_id if provided
    $folderId = isset($_POST['folder_id']) && $_POST['folder_id'] !== '' ? (int)$_POST['folder_id'] : null;

    // Handle multiple files - normalize the $_FILES array structure
    $files = $_FILES['files'];
    
    // Check if it's a single file or multiple files
    if (is_array($files['name'])) {
        $fileCount = count($files['name']);
    } else {
        // Single file - normalize to array format
        $files = [
            'name' => [$files['name']],
            'type' => [$files['type']],
            'tmp_name' => [$files['tmp_name']],
            'error' => [$files['error']],
            'size' => [$files['size']]
        ];
        $fileCount = 1;
    }
    
    for ($i = 0; $i < $fileCount; $i++) {
        try {
            // Check for upload errors
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $errorMsg = 'File upload failed';
                switch ($files['error'][$i]) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $errorMsg = 'File too large';
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $errorMsg = 'File upload incomplete';
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $errorMsg = 'No file uploaded';
                        break;
                }
                throw new Exception($errorMsg . ': ' . $files['name'][$i]);
            }

            // Get file mime type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $files['tmp_name'][$i]);
            
            // Get file extension
            $extension = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));

            // Validate file type
            $allowedTypes = [
                // Images
                'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/jpg',
                // Audio
                'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4', 'audio/aac',
                // Video
                'video/mp4', 'video/mpeg', 'video/webm', 'video/quicktime', 'video/x-msvideo',
                // Archives
                'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed',
                'application/x-tar', 'application/gzip', 'application/x-zip-compressed',
                // Documents
                'application/pdf', 'image/vnd.djvu',
                // Other media
                'application/vnd.apple.mpegurl', 'application/x-mpegurl',
                // Octet stream (generic binary)
                'application/octet-stream'
            ];
            
            $allowedExtensions = [
                'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
                'mp3', 'wav', 'ogg', 'm4a', 'aac',
                'mp4', 'mpeg', 'webm', 'mov', 'avi',
                'zip', 'rar', '7z', 'tar', 'gz',
                'pdf', 'djvu',
                'm3u8', 'm3u'
            ];

            // Check both MIME type and extension
            $validMime = in_array($mimeType, $allowedTypes);
            $validExtension = in_array($extension, $allowedExtensions);
            
            if (!$validMime && !$validExtension) {
                throw new Exception('File type not allowed (MIME: ' . $mimeType . ', Ext: .' . $extension . '): ' . $files['name'][$i]);
            }
            
            // If MIME is octet-stream, rely on extension validation
            if ($mimeType === 'application/octet-stream' && !$validExtension) {
                throw new Exception('File type not allowed: ' . $files['name'][$i]);
            }

            // Validate file size (2GB limit)
            if ($files['size'][$i] > 2 * 1024 * 1024 * 1024) {
                throw new Exception('File size exceeds 2 GB limit: ' . $files['name'][$i]);
            }

            // Generate unique file ID
            $fileId = uniqid();
            
            // Create uploads directory if not exists
            $uploadsDir = __DIR__ . '/uploads/' . $fileId;
            if (!file_exists($uploadsDir)) {
                mkdir($uploadsDir, 0777, true);
            }
            
            // Move uploaded file to local storage
            $localPath = $uploadsDir . '/' . $files['name'][$i];
            move_uploaded_file($files['tmp_name'][$i], $localPath);
            
            // Save file info to database
            $db = getDBConnection();
            
            $status = 'completed';
            $storageLocation = 'local';
            
            if ($folderId) {
                $stmt = $db->prepare("INSERT INTO file_uploads (
                    file_id, 
                    file_name, 
                    size, 
                    upload_status, 
                    local_path,
                    storage_location,
                    uploaded_by,
                    folder_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->bind_param("ssisssii",
                    $fileId,
                    $files['name'][$i],
                    $files['size'][$i],
                    $status,
                    $localPath,
                    $storageLocation,
                    $_SESSION['user_id'],
                    $folderId
                );
            } else {
                $stmt = $db->prepare("INSERT INTO file_uploads (
                    file_id, 
                    file_name, 
                    size, 
                    upload_status, 
                    local_path,
                    storage_location,
                    uploaded_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->bind_param("ssisssi",
                    $fileId,
                    $files['name'][$i],
                    $files['size'][$i],
                    $status,
                    $localPath,
                    $storageLocation,
                    $_SESSION['user_id']
                );
            }
            $stmt->execute();

            $uploadedFiles[] = [
                'file_id' => $fileId,
                'file_name' => $files['name'][$i],
                'downloadLink' => "https://" . $_SERVER['HTTP_HOST'] . "/download/" . $fileId
            ];

        } catch (Exception $e) {
            $errors[] = [
                'file' => $files['name'][$i],
                'error' => $e->getMessage()
            ];
        }
    }
    
    if (count($uploadedFiles) > 0) {
        echo json_encode([
            'success' => true,
            'files' => $uploadedFiles,
            'errors' => $errors,
            'uploaded_count' => count($uploadedFiles),
            'error_count' => count($errors)
        ]);
    } else {
        $errorMessages = array_map(function($err) {
            return is_array($err) ? $err['file'] . ': ' . $err['error'] : $err;
        }, $errors);
        throw new Exception('All uploads failed: ' . implode(', ', $errorMessages));
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'files_received' => isset($_FILES['files']),
            'post_data' => array_keys($_POST)
        ]
    ]);
}
