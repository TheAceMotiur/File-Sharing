<?php
/**
 * Test Download with Error Display
 */

require_once __DIR__ . '/../config/bootstrap.php';

use App\Services\DropboxSyncService;

$fileId = 13; // The file from the error message (166f3b912f7d)

$db = getDBConnection();
$stmt = $db->prepare("SELECT * FROM file_uploads WHERE id = ?");
$stmt->bind_param("i", $fileId);
$stmt->execute();
$file = $stmt->get_result()->fetch_assoc();

if (!$file) {
    die("File not found\n");
}

echo "Testing download for file: {$file['original_name']}\n";
echo "Unique ID: {$file['unique_id']}\n";
echo "Storage: {$file['storage_location']}\n";
echo "Sync Status: {$file['sync_status']}\n";
echo "Dropbox Account: {$file['dropbox_account_id']}\n";
echo "Dropbox Path: {$file['dropbox_path']}\n\n";

$service = new DropboxSyncService();
$result = $service->downloadFromDropbox($file);

if ($result === false) {
    $error = $service->getLastError();
    echo "❌ Download failed\n";
    echo "Error message that would be shown to user:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo $error . "\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
} else {
    $size = strlen($result);
    echo "✅ Download successful: " . number_format($size / 1024, 2) . " KB\n";
}
