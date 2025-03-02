<?php
require_once __DIR__ . '/../config.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: ../login.php');
    exit;
}

$backupDir = __DIR__ . '/../backups';
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0777, true);
}

try {
    $db = getDBConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        switch ($_POST['action']) {
            case 'backup':
                // Create backup filename with timestamp
                $timestamp = date('Y-m-d_H-i-s');
                $backupName = "backup_{$timestamp}";
                $backupPath = "{$backupDir}/{$backupName}";
                mkdir($backupPath);

                // Backup files
                $excludeDirs = ['cache', 'chunks', 'backups', 'vendor'];
                $rootDir = __DIR__ . '/..';
                $zip = new ZipArchive();
                $zipFile = "{$backupPath}/files.zip";
                
                if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
                    $files = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($rootDir),
                        RecursiveIteratorIterator::LEAVES_ONLY
                    );

                    foreach ($files as $file) {
                        if ($file->isDir()) continue;
                        
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen($rootDir) + 1);
                        
                        // Skip excluded directories
                        $skipFile = false;
                        foreach ($excludeDirs as $excludeDir) {
                            if (strpos($relativePath, $excludeDir . DIRECTORY_SEPARATOR) === 0) {
                                $skipFile = true;
                                break;
                            }
                        }
                        
                        if (!$skipFile && $file->isFile()) {
                            $zip->addFile($filePath, $relativePath);
                        }
                    }
                    $zip->close();
                }

                // Backup database
                $tables = [];
                $result = $db->query("SHOW TABLES");
                while ($row = $result->fetch_array()) {
                    $tables[] = $row[0];
                }

                $sqlFile = "{$backupPath}/database.sql";
                $handle = fopen($sqlFile, 'w');

                // Add SQL header with timestamp
                fwrite($handle, "-- Backup generated on " . date('Y-m-d H:i:s') . "\n\n");

                foreach ($tables as $table) {
                    // Get create table statement
                    $result = $db->query("SHOW CREATE TABLE {$table}");
                    $row = $result->fetch_array();
                    fwrite($handle, "\n\n" . $row[1] . ";\n\n");

                    // Get table data
                    $result = $db->query("SELECT * FROM {$table}");
                    while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
                        $values = array_map(function($value) use ($db) {
                            return $value === null ? 'NULL' : "'" . $db->real_escape_string($value) . "'";
                        }, $row);
                        
                        fwrite($handle, "INSERT INTO {$table} VALUES (" . implode(', ', $values) . ");\n");
                    }
                }
                fclose($handle);

                $success = "Backup created successfully";
                break;

            case 'restore':
                if (!isset($_FILES['backup_file'])) {
                    throw new Exception('No backup file provided');
                }

                $tempDir = sys_get_temp_dir() . '/restore_' . uniqid();
                mkdir($tempDir);

                $zip = new ZipArchive();
                if ($zip->open($_FILES['backup_file']['tmp_name']) === TRUE) {
                    $zip->extractTo($tempDir);
                    $zip->close();

                    // Restore database
                    if (file_exists($tempDir . '/database.sql')) {
                        $sql = file_get_contents($tempDir . '/database.sql');
                        $db->multi_query($sql);
                        while ($db->more_results()) {
                            $db->next_result();
                        }
                    }

                    // Restore files
                    if (file_exists($tempDir . '/files.zip')) {
                        $zip = new ZipArchive();
                        if ($zip->open($tempDir . '/files.zip') === TRUE) {
                            $zip->extractTo(__DIR__ . '/..');
                            $zip->close();
                        }
                    }

                    // Cleanup
                    array_map('unlink', glob("$tempDir/*.*"));
                    rmdir($tempDir);

                    $success = "Restore completed successfully";
                }
                break;

            case 'delete':
                $backupPath = $backupDir . '/' . $_POST['backup_name'];
                if (file_exists($backupPath)) {
                    array_map('unlink', glob("$backupPath/*.*"));
                    rmdir($backupPath);
                    $success = "Backup deleted successfully";
                }
                break;
        }
    }

    // Get list of backups
    $backups = [];
    if (file_exists($backupDir)) {
        foreach (scandir($backupDir) as $backup) {
            if ($backup === '.' || $backup === '..') continue;
            
            $path = $backupDir . '/' . $backup;
            if (is_dir($path)) {
                $backups[] = [
                    'name' => $backup,
                    'date' => date('Y-m-d H:i:s', filectime($path)),
                    'size' => formatSize(dirSize($path))
                ];
            }
        }
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}

// Helper function to calculate directory size
function dirSize($dir) {
    $size = 0;
    foreach (glob(rtrim($dir, '/').'/*', GLOB_NOSORT) as $each) {
        $size += is_file($each) ? filesize($each) : dirSize($each);
    }
    return $size;
}

// Helper function to format size
function formatSize($size) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($size >= 1024) {
        $size /= 1024;
        $i++;
    }
    return round($size, 2) . ' ' . $units[$i];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup & Restore - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <!-- Include sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <div class="lg:ml-64 p-8">
        <h1 class="text-2xl font-bold mb-6">Backup & Restore</h1>

        <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Backup Section -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">Create Backup</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="backup">
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        Create New Backup
                    </button>
                </form>
            </div>

            <!-- Restore Section -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">Restore Backup</h2>
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="action" value="restore">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Select Backup File (.zip)
                        </label>
                        <input type="file" name="backup_file" accept=".zip" required
                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>
                    <button type="submit" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600"
                            onclick="return confirm('Warning: This will overwrite current data. Are you sure?')">
                        Restore from Backup
                    </button>
                </form>
            </div>
        </div>

        <!-- Existing Backups -->
        <div class="mt-8">
            <h2 class="text-xl font-semibold mb-4">Existing Backups</h2>
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Backup Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date Created</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($backups as $backup): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($backup['name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($backup['date']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($backup['size']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <a href="download_backup.php?name=<?php echo urlencode($backup['name']); ?>" 
                                   class="text-blue-600 hover:text-blue-900 mr-4">Download</a>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="backup_name" value="<?php echo htmlspecialchars($backup['name']); ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-900"
                                            onclick="return confirm('Are you sure you want to delete this backup?')">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
