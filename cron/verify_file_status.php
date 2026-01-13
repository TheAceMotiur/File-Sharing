<?php
/**
 * Verify file status in database and local/Dropbox storage
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/bootstrap.php';

if ($argc < 2) {
    echo "Usage: php verify_file_status.php <file_id>\n";
    exit(1);
}

$fileId = (int)$argv[1];

$db = getDBConnection();

echo "=== File Status Verification ===\n";
echo "File ID: {$fileId}\n\n";

// Get file info
$stmt = $db->prepare("SELECT * FROM file_uploads WHERE id = ?");
$stmt->bind_param("i", $fileId);
$stmt->execute();
$file = $stmt->get_result()->fetch_assoc();

if (!$file) {
    echo "❌ File not found in database\n";
    exit(1);
}

echo "Database Info:\n";
echo str_repeat("-", 50) . "\n";
echo "  Original Name: {$file['original_name']}\n";
echo "  Unique ID: {$file['unique_id']}\n";
echo "  Size: " . round($file['size'] / (1024*1024), 2) . " MB\n";
echo "  Storage Location: {$file['storage_location']}\n";
echo "  Sync Status: {$file['sync_status']}\n";
echo "  Dropbox Account: " . ($file['dropbox_account_id'] ?: 'None') . "\n";
echo "  Dropbox Path: " . ($file['dropbox_path'] ?: 'None') . "\n";
echo "\n";

// Check local file
echo "Local Storage:\n";
echo str_repeat("-", 50) . "\n";
$localPath = UPLOAD_DIR . $file['unique_id'] . '/' . $file['stored_name'];
if (file_exists($localPath)) {
    $localSize = filesize($localPath);
    echo "  ✓ File exists locally\n";
    echo "  Path: {$localPath}\n";
    echo "  Size: " . round($localSize / (1024*1024), 2) . " MB\n";
    echo "  Matches DB: " . ($localSize == $file['size'] ? "✓ Yes" : "❌ No") . "\n";
} else {
    echo "  ❌ File not found locally\n";
    echo "  Expected path: {$localPath}\n";
}
echo "\n";

// Check Dropbox
echo "Dropbox Storage:\n";
echo str_repeat("-", 50) . "\n";

if ($file['dropbox_account_id'] && $file['dropbox_path']) {
    $stmt = $db->prepare("SELECT * FROM dropbox_accounts WHERE id = ?");
    $stmt->bind_param("i", $file['dropbox_account_id']);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    
    if ($account && !empty($account['access_token'])) {
        echo "  Account ID: {$account['id']}\n";
        echo "  Path: {$file['dropbox_path']}\n";
        echo "  Attempting to verify file exists...\n";
        
        try {
            $client = new \Spatie\Dropbox\Client($account['access_token']);
            
            // Try to get metadata
            try {
                $metadata = $client->getMetadata($file['dropbox_path']);
                echo "  ✓ File exists in Dropbox\n";
                echo "  Dropbox Size: " . round($metadata['size'] / (1024*1024), 2) . " MB\n";
                echo "  Matches DB: " . ($metadata['size'] == $file['size'] ? "✓ Yes" : "❌ No") . "\n";
            } catch (\Spatie\Dropbox\Exceptions\BadRequest $e) {
                if (strpos($e->getMessage(), 'path/not_found') !== false) {
                    echo "  ❌ File NOT found in Dropbox (path/not_found)\n";
                    echo "  This means:\n";
                    echo "  - File was deleted from Dropbox\n";
                    echo "  - Path in database is incorrect\n";
                    echo "  - File was never successfully uploaded\n";
                } else {
                    echo "  ❌ Error: " . $e->getMessage() . "\n";
                }
            }
        } catch (\Exception $e) {
            echo "  ❌ Error checking Dropbox: " . $e->getMessage() . "\n";
        }
    } else {
        echo "  ❌ Dropbox account not found or no access token\n";
    }
} else {
    echo "  ⚠️  File not configured for Dropbox storage\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "RECOMMENDATIONS\n";
echo str_repeat("=", 50) . "\n";

if ($file['storage_location'] === 'dropbox' && $file['sync_status'] === 'synced') {
    if (!file_exists($localPath)) {
        echo "⚠️  File marked as synced but not found in Dropbox\n";
        echo "Options:\n";
        echo "1. If file exists locally elsewhere, re-sync it\n";
        echo "2. Mark file as failed and re-upload\n";
        echo "3. Delete the database entry if file is lost\n";
    }
}

$db->close();
