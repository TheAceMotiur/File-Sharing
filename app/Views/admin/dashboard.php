<?php $currentPage = 'dashboard'; ?>
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
        <?php include __DIR__ . '/partials/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto">
            <div class="p-8">
        <h1 class="text-3xl font-bold mb-8">Admin Dashboard</h1>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-users text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-500 text-sm">Total Users</p>
                        <p class="text-2xl font-bold"><?php echo $totalUsers; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-check-circle text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-500 text-sm">Verified Users</p>
                        <p class="text-2xl font-bold"><?php echo $verifiedUsers; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <i class="fas fa-file text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-500 text-sm">Total Files</p>
                        <p class="text-2xl font-bold"><?php echo $totalFiles; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                        <i class="fas fa-hdd text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-gray-500 text-sm">Total Storage</p>
                        <p class="text-xl font-bold"><?php echo $totalStorage; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <a href="/admin/users" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition">
                <i class="fas fa-users text-3xl text-blue-500 mb-3"></i>
                <h3 class="text-lg font-bold">Manage Users</h3>
                <p class="text-gray-500">View and manage user accounts</p>
            </a>
            
            <a href="/admin/files" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition">
                <i class="fas fa-file text-3xl text-green-500 mb-3"></i>
                <h3 class="text-lg font-bold">Manage Files</h3>
                <p class="text-gray-500">View and manage uploaded files</p>
            </a>
            
            <a href="/admin/settings" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition">
                <i class="fas fa-cog text-3xl text-purple-500 mb-3"></i>
                <h3 class="text-lg font-bold">Settings</h3>
                <p class="text-gray-500">Configure site settings</p>
            </a>
        </div>

        <!-- Recent Users -->
        <div class="bg-white rounded-lg shadow mb-8">
            <div class="p-6 border-b">
                <h2 class="text-xl font-bold">Recent Users</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Joined</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($recentUsers as $user): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4"><?php echo htmlspecialchars($user['name']); ?></td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($user['email_verified']): ?>
                                    <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">Verified</span>
                                <?php else: ?>
                                    <span class="px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded">Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Files -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b">
                <h2 class="text-xl font-bold">Recent Files</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">File Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($recentFiles as $file): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4"><?php echo htmlspecialchars($file['original_name']); ?></td>
                            <td class="px-6 py-4"><?php echo htmlspecialchars($file['user_name'] ?? 'Unknown'); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?php echo formatFileSize($file['size']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?php echo date('M j, Y', strtotime($file['created_at'])); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
            </div>
        </div>
    </div>
</body>
</html>
