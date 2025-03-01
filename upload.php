<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';
session_start();

use TusPhp\Tus\Server;

// Verify login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

try {
    if (!isset($_FILES['file'])) {
        throw new Exception('No file uploaded');
    }

    $upload = $_FILES['file'];
    if ($upload['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload failed with error code: ' . $upload['error']);
    }

    $server = new Server('file');
    $server->setUploadDir(__DIR__ . '/uploads');

    // Ensure upload directory exists and is writable
    if (!file_exists(__DIR__ . '/uploads')) {
        mkdir(__DIR__ . '/uploads', 0755, true);
    }

    // Handle completion callback
    $server->event()->addListener('tus-server.upload.complete', function ($event) {
        try {
            $file = $event->getFile();
            $filePath = $file->getPath();
            $fileSize = $file->getSize();
            $originalName = $file->getMetadata()['name'] ?? basename($filePath);
            $fileId = uniqid();

            $db = getDBConnection();
            
            // Get Dropbox account
            $dropbox = $db->query("SELECT * FROM dropbox_accounts LIMIT 1")->fetch_assoc();
            if (!$dropbox) {
                throw new Exception('No Dropbox account configured');
            }

            // Initialize Dropbox client
            $client = new Spatie\Dropbox\Client($dropbox['access_token']);

            // Upload to Dropbox
            $dropboxPath = "/{$fileId}/{$originalName}";
            $client->upload($dropboxPath, file_get_contents($filePath));

            // Save to database
            $stmt = $db->prepare("INSERT INTO file_uploads (
                file_id, file_name, size, upload_status, dropbox_path, 
                dropbox_account_id, uploaded_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?)");

            $status = 'completed';
            $stmt->bind_param("ssissii",
                $fileId,
                $originalName,
                $fileSize,
                $status,
                $dropboxPath,
                $dropbox['id'],
                $_SESSION['user_id']
            );

            if (!$stmt->execute()) {
                throw new Exception('Failed to save file information');
            }

            // Clean up local file
            unlink($filePath);

            // Return success response
            echo json_encode([
                'success' => true,
                'downloadLink' => "https://" . $_SERVER['HTTP_HOST'] . "/download/" . $fileId
            ]);

        } catch (Exception $e) {
            error_log("Upload error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    });

    $response = $server->serve();
    $response->send();

    // Send success response
    echo json_encode([
        'success' => true,
        'message' => 'File uploaded successfully',
        'fileId' => $fileId
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
