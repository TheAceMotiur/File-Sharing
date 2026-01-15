<!-- Sidebar -->
<div class="sidebar" :class="{'show': sidebarOpen}">
    <div class="p-6">
        <a href="/dashboard"
           class="w-full flex items-center justify-center gap-3 px-5 py-3.5 bg-blue-600 text-white rounded-full hover:bg-blue-700 hover:shadow-lg transition-all duration-200 mb-8 font-medium">
            <div class="w-7 h-7 flex items-center justify-center">
                <i class="fas fa-plus text-lg"></i>
            </div>
            <span>New</span>
        </a>
        
        <nav class="space-y-2">
            <a href="/dashboard" 
               class="flex items-center gap-3 px-4 py-2 rounded-lg transition-colors <?php echo (isset($currentPage) && $currentPage === 'dashboard') ? 'bg-blue-50 text-blue-600' : 'text-gray-700 hover:bg-gray-100'; ?>">
                <i class="fas fa-folder w-5"></i>
                <span>My Files</span>
            </a>
            <a href="/profile" 
               class="flex items-center gap-3 px-4 py-2 rounded-lg transition-colors <?php echo (isset($currentPage) && $currentPage === 'profile') ? 'bg-blue-50 text-blue-600' : 'text-gray-700 hover:bg-gray-100'; ?>">
                <i class="fas fa-user w-5"></i>
                <span>Profile</span>
            </a>
            <?php if (isAdmin()): ?>
            <a href="/admin" 
               class="flex items-center gap-3 px-4 py-2 rounded-lg transition-colors text-gray-700 hover:bg-gray-100">
                <i class="fas fa-cog w-5"></i>
                <span>Admin</span>
            </a>
            <?php endif; ?>
        </nav>
        
        <hr class="my-4 border-gray-200">
        
        <div class="px-5 py-3">
            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Storage</p>
            <div class="space-y-3">
                <?php
                // Get storage info
                $userId = $_SESSION['user']['id'] ?? 0;
                if ($userId) {
                    require_once __DIR__ . '/../../Models/User.php';
                    $userModel = new \App\Models\User();
                    $storageUsed = $userModel->getStorageUsage($userId);
                    $storageMax = 5 * 1024 * 1024 * 1024; // 5GB
                    $storagePercent = min(100, ($storageUsed / $storageMax) * 100);
                } else {
                    $storageUsed = 0;
                    $storagePercent = 0;
                }
                ?>
                <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 h-2 rounded-full transition-all duration-300" style="width: <?php echo round($storagePercent); ?>%"></div>
                </div>
                <p class="text-xs text-gray-600 font-medium">
                    <?php echo formatFileSize($storageUsed); ?> of <?php echo formatFileSize(5 * 1024 * 1024 * 1024); ?> used
                </p>
            </div>
        </div>
    </div>
</div>
