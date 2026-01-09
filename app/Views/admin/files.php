<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <?php $currentPage = 'files'; include __DIR__ . '/partials/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto">
            <div class="p-8">
                <div class="mb-6">
                    <h2 class="text-3xl font-bold">Manage Files</h2>
                </div>

                <!-- Search -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <form method="GET" class="flex gap-4">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search ?? ''); ?>" 
                               placeholder="Search files..." 
                               class="flex-1 px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button type="submit" class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <?php if (!empty($search)): ?>
                        <a href="/admin/files" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
                            Clear
                        </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Files Table -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">File Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Downloads</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Uploaded</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($files)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                    No files found
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($files as $file): ?>
                            <tr>
                                <td class="px-6 py-4 text-sm">#<?php echo $file['id']; ?></td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($file['original_name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo $file['unique_id']; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <?php echo htmlspecialchars($file['user_name'] ?? 'Unknown'); ?>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <?php echo formatFileSize($file['size']); ?>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <?php echo $file['downloads'] ?? 0; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($file['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <a href="/download/<?php echo $file['unique_id']; ?>" 
                                       class="text-blue-600 hover:text-blue-900 mr-3" target="_blank">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <a href="/info/<?php echo $file['unique_id']; ?>" 
                                       class="text-green-600 hover:text-green-900 mr-3" target="_blank">
                                        <i class="fas fa-info-circle"></i>
                                    </a>
                                    <button onclick="deleteFile(<?php echo $file['id']; ?>)" 
                                            class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
    function deleteFile(id) {
        if (confirm('Are you sure you want to permanently delete this file? This will remove it from:\n- Database\n- File system\n- Dropbox (if synced)\n\nThis action cannot be undone!')) {
            fetch('/admin/deleteFile', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ file_id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('File deleted successfully from all locations');
                    location.reload();
                } else {
                    alert('Failed to delete file: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                alert('Error deleting file: ' + error.message);
            });
        }
    }
    </script>
</body>
</html>
