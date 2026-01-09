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
        <?php $currentPage = 'settings'; include __DIR__ . '/partials/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto">
            <div class="p-8">
                <div class="mb-6">
                    <h2 class="text-3xl font-bold">Site Settings</h2>
                </div>

                <?php if (isset($success)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    Settings saved successfully!
                </div>
                <?php endif; ?>

                <!-- Settings Form -->
                <div class="bg-white rounded-lg shadow p-6">
                    <form method="POST" class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Site Name
                            </label>
                            <input type="text" name="site_name" 
                                   value="<?php echo htmlspecialchars($settings['site_name'] ?? 'OneNetly'); ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Max Upload Size (MB)
                            </label>
                            <input type="number" name="max_upload_size" 
                                   value="<?php echo $settings['max_upload_size'] ?? 100; ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                User Storage Limit (GB)
                            </label>
                            <input type="number" name="storage_limit" 
                                   value="<?php echo $settings['storage_limit'] ?? 10; ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   required>
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" name="require_verification" value="1"
                                   <?php echo !empty($settings['require_verification']) ? 'checked' : ''; ?>
                                   class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <label class="ml-2 text-sm text-gray-700">
                                Require email verification
                            </label>
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" name="enable_uploads" value="1"
                                   <?php echo !empty($settings['enable_uploads']) ? 'checked' : ''; ?>
                                   class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <label class="ml-2 text-sm text-gray-700">
                                Enable file uploads
                            </label>
                        </div>

                        <div>
                            <button type="submit" class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                                <i class="fas fa-save"></i> Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
