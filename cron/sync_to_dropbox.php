<?php
/**
 * Cron job to sync pending files to Dropbox
 * Run this every minute: * * * * * php /path/to/sync_to_dropbox.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/bootstrap.php';

use App\Services\DropboxSyncService;

$db = getDBConnection();
$syncService = new DropboxSyncService();

echo "[" . date('Y-m-d H:i:s') . "] Starting Dropbox sync...\n";

// Get pending files
$result = $db->query("
    SELECT id, unique_id, original_name, size, sync_status
    FROM file_uploads 
    WHERE sync_status IN ('pending', 'failed')
    AND storage_location = 'local'
    ORDER BY created_at ASC
    LIMIT 10
");

$synced = 0;
$failed = 0;

while ($file = $result->fetch_assoc()) {
    echo "Syncing file {$file['id']}: {$file['original_name']} (" . formatFileSize($file['size']) . ")...\n";
    
    if ($syncService->syncFile($file['id'])) {
        echo "  ✓ Synced successfully\n";
        $synced++;
    } else {
        echo "  ✗ Sync failed\n";
        $failed++;
    }
}

echo "\nSync complete: {$synced} synced, {$failed} failed\n";

// Show account stats
echo "\n--- Dropbox Account Stats ---\n";
$stats = $syncService->getAccountStats();
foreach ($stats as $account) {
    $used = formatFileSize($account['used_storage']);
    echo "Account {$account['id']}: {$used} ({$account['usage_percent']}%)\n";
}

$db->close();
