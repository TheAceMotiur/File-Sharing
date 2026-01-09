<?php $currentPage = 'cache'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Cache Management'; ?> - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <?php include __DIR__ . '/partials/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100">
    <div class="container mx-auto px-6 py-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Cache Management</h1>
                <p class="text-gray-600 mt-2">Monitor and manage file cache storage</p>
            </div>
            <div class="flex gap-2">
                <button onclick="refreshCache()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    <i class="fas fa-sync-alt mr-2"></i>Refresh
                </button>
                <button onclick="clearCache()" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                    <i class="fas fa-trash mr-2"></i>Clear Cache
                </button>
            </div>
        </div>

        <!-- Cache Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-database text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-600 text-sm">Total Size</p>
                        <p class="text-2xl font-bold text-gray-900" id="total-size">-</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-file text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-600 text-sm">Cached Files</p>
                        <p class="text-2xl font-bold text-gray-900" id="file-count">-</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <i class="fas fa-chart-line text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-600 text-sm">Hit Rate</p>
                        <p class="text-2xl font-bold text-gray-900" id="hit-rate">-</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                        <i class="fas fa-clock text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-600 text-sm">Avg Age</p>
                        <p class="text-2xl font-bold text-gray-900" id="avg-age">-</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cache Settings -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-bold text-gray-900">Cache Settings</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Maximum Cache Size</label>
                        <div class="flex items-center gap-2">
                            <span class="text-gray-900 text-lg font-semibold">500 MB</span>
                            <span class="text-gray-500 text-sm">(Configured in cleanup script)</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Maximum File Age</label>
                        <div class="flex items-center gap-2">
                            <span class="text-gray-900 text-lg font-semibold">7 days</span>
                            <span class="text-gray-500 text-sm">(Configured in cleanup script)</span>
                        </div>
                    </div>
                </div>
                <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                    <p class="text-sm text-blue-800">
                        <i class="fas fa-info-circle mr-2"></i>
                        Cache cleanup runs automatically via cron job. Files older than 7 days or when cache exceeds 500MB will be removed.
                    </p>
                </div>
            </div>
        </div>

        <!-- Cached Files Table -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-xl font-bold text-gray-900">Cached Files</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Access</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="cache-files" class="bg-white divide-y divide-gray-200">
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                <i class="fas fa-spinner fa-spin mr-2"></i>Loading cache files...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Load cache data on page load
document.addEventListener('DOMContentLoaded', function() {
    loadCacheData();
});

function loadCacheData() {
    fetch('/admin/cache/stats')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update overview cards
                document.getElementById('total-size').textContent = data.totalSize;
                document.getElementById('file-count').textContent = data.fileCount;
                document.getElementById('hit-rate').textContent = data.hitRate || 'N/A';
                document.getElementById('avg-age').textContent = data.avgAge;
                
                // Update table
                updateCacheTable(data.files);
            } else {
                showError('Failed to load cache data');
            }
        })
        .catch(error => {
            console.error('Error loading cache data:', error);
            showError('Failed to load cache data');
        });
}

function updateCacheTable(files) {
    const tbody = document.getElementById('cache-files');
    
    if (files.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                    No cached files found
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = files.map(file => `
        <tr>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono">${file.uniqueId}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${file.fileName || 'Unknown'}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${file.size}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${file.age}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${file.lastAccess}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">
                <button onclick="deleteCacheFile('${file.uniqueId}')" class="text-red-600 hover:text-red-900">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

function refreshCache() {
    loadCacheData();
    showSuccess('Cache data refreshed');
}

function clearCache() {
    Swal.fire({
        title: 'Clear All Cache?',
        text: 'This will remove all cached files. Files will be re-cached on next download.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, clear cache'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('/admin/cache/clear', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess(data.message);
                    loadCacheData();
                } else {
                    showError(data.message || 'Failed to clear cache');
                }
            })
            .catch(error => {
                console.error('Error clearing cache:', error);
                showError('Failed to clear cache');
            });
        }
    });
}

function deleteCacheFile(uniqueId) {
    Swal.fire({
        title: 'Delete Cache File?',
        text: 'This file will be re-cached on next download.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('/admin/cache/delete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ uniqueId: uniqueId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess('Cache file deleted');
                    loadCacheData();
                } else {
                    showError(data.message || 'Failed to delete cache file');
                }
            })
            .catch(error => {
                console.error('Error deleting cache file:', error);
                showError('Failed to delete cache file');
            });
        }
    });
}

function showSuccess(message) {
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: message,
        timer: 2000,
        showConfirmButton: false
    });
}

function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: message
    });
}
</script>

    </div>
</body>
</html>
