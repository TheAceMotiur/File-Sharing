<?php
require __DIR__ . '/../config/bootstrap.php';

$db = getDBConnection();
$result = $db->query('SELECT id, unique_id, original_name, storage_location, sync_status FROM file_uploads WHERE id = 13');

if ($result && $result->num_rows > 0) {
    $file = $result->fetch_assoc();
    echo "✓ File exists:\n";
    echo "  ID: {$file['id']}\n";
    echo "  Unique ID: {$file['unique_id']}\n";
    echo "  Name: {$file['original_name']}\n";
    echo "  Storage: {$file['storage_location']}\n";
    echo "  Sync: {$file['sync_status']}\n";
} else {
    echo "✗ File ID 13 not found in database\n\n";
    echo "Recent files:\n";
    $result2 = $db->query('SELECT id, unique_id, original_name FROM file_uploads ORDER BY id DESC LIMIT 5');
    while ($row = $result2->fetch_assoc()) {
        echo "  ID: {$row['id']} - {$row['unique_id']} - {$row['original_name']}\n";
    }
}
