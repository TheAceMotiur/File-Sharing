<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - <?php echo getSiteName(); ?></title>
    <link rel="icon" type="image/png" href="/icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/vue@3.3.4/dist/vue.global.prod.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        [v-cloak] { display: none; }
        .item-card { transition: all 0.2s; }
        .item-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .folder-icon { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    </style>
</head>
<body class="bg-gray-50">
    <?php include __DIR__ . '/../partials/header.php'; ?>

    <div id="app" v-cloak class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-file text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-500 text-sm">Total Files</p>
                        <p class="text-2xl font-bold"><?php echo count($files); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-folder text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-500 text-sm">Folders</p>
                        <p class="text-2xl font-bold"><?php echo count($folders); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <i class="fas fa-hdd text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-500 text-sm">Storage Used</p>
                        <p class="text-2xl font-bold"><?php echo formatFileSize($storageUsed); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                        <i class="fas fa-crown text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-500 text-sm">Account</p>
                        <p class="text-xl font-bold"><?php echo $user['premium'] ? 'Premium' : 'Free'; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-bold">My Files</h2>
                <a href="/upload" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    <i class="fas fa-upload mr-2"></i>Upload Files
                </a>
            </div>
        </div>

        <!-- Folders -->
        <?php if (!empty($folders)): ?>
        <div class="mb-8">
            <h3 class="text-lg font-bold mb-4">Folders</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                <?php foreach ($folders as $folder): ?>
                <a href="/dashboard?folder=<?php echo $folder['id']; ?>" 
                   class="bg-white rounded-lg shadow p-4 hover:shadow-lg transition">
                    <div class="text-center">
                        <i class="fas fa-folder text-4xl text-blue-500 mb-2"></i>
                        <p class="text-sm font-medium truncate"><?php echo htmlspecialchars($folder['name']); ?></p>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Files -->
        <div>
            <h3 class="text-lg font-bold mb-4">
                <?php echo $currentFolder ? htmlspecialchars($currentFolder['name']) : 'All Files'; ?>
            </h3>
            
            <?php if (empty($files)): ?>
            <div class="bg-white rounded-lg shadow p-12 text-center">
                <i class="fas fa-folder-open text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 text-lg">No files yet</p>
                <a href="/upload" class="text-blue-500 hover:text-blue-700 mt-2 inline-block">
                    Upload your first file
                </a>
            </div>
            <?php else: ?>
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($files as $file): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <i class="fas fa-file text-blue-500 mr-3"></i>
                                    <span class="font-medium"><?php echo htmlspecialchars($file['original_name']); ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?php echo formatFileSize($file['size']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?php echo date('M j, Y', strtotime($file['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <a href="/download/<?php echo $file['unique_id']; ?>" 
                                   class="text-blue-600 hover:text-blue-800 mr-3" target="_blank">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="/download/<?php echo $file['unique_id']; ?>?download=1" 
                                   class="text-green-600 hover:text-green-800 mr-3">
                                    <i class="fas fa-download"></i>
                                </a>
                                <button onclick="deleteFile(<?php echo $file['id']; ?>)" 
                                        class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function deleteFile(fileId) {
        if (!confirm('Are you sure you want to delete this file?')) return;
        
        fetch('/api/delete', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({file_id: fileId})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to delete file');
            }
        });
    }
    </script>

    <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
