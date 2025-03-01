<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';
session_start();

use TusPhp\Tus\Server;
use Spatie\Dropbox\Client as DropboxClient;

// Verify login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$server = new Server('file');
$server->setUploadDir(__DIR__ . '/uploads');

// Handle completion callback
$server->event()->addListener('tus-server.upload.complete', function ($event) {
    $file = $event->getFile();
    $filePath = $file->getPath();
    $fileSize = $file->getSize();
    $originalName = $file->getMetadata()['name'] ?? basename($filePath);
    $fileId = uniqid();

    try {
        $db = getDBConnection();

        // Get Dropbox account
        $dropbox = $db->query("SELECT * FROM dropbox_accounts LIMIT 1")->fetch_assoc();
        $client = new DropboxClient($dropbox['access_token']);

        // Upload to Dropbox
        $dropboxPath = "/{$fileId}/{$originalName}";
        $client->upload($dropboxPath, file_get_contents($filePath), 'add');

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
        $stmt->execute();

        // Clean up local file
        unlink($filePath);

        // Return success response
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'downloadLink' => "https://" . $_SERVER['HTTP_HOST'] . "/download/" . $fileId
        ]);
    } catch (Exception $e) {
        error_log("Upload error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
});

$response = $server->serve();
$response->send();
