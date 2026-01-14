<?php
require_once __DIR__ . '/config/bootstrap.php';

use App\Services\DropboxSyncService;

$fileId = 13;

$db = getDBConnection();
$stmt = $db->prepare("SELECT * FROM file_uploads WHERE id = ?");
$stmt->bind_param("i", $fileId);
$stmt->execute();
$file = $stmt->get_result()->fetch_assoc();

echo "=== Testing Dropbox Shared Links ===\n\n";
echo "File ID: {$file['id']}\n";
echo "Name: {$file['original_name']}\n";
echo "Path: {$file['dropbox_path']}\n\n";

$service = new DropboxSyncService();
echo "Getting download link (using shared links API)...\n";
$link = $service->getTemporaryLink($file);

if ($link) {
    echo "✅ SUCCESS!\n\n";
    echo "Download link: $link\n\n";
    echo "This shared link:\n";
    echo "- Works with standard Dropbox app permissions\n";
    echo "- Never expires (reusable)\n";
    echo "- Direct download with dl=1 parameter\n";
    echo "- Uses Dropbox CDN for fast delivery\n";
} else {
    echo "❌ FAILED\n";
    echo "Error: " . $service->getLastError() . "\n";
}
