<?php
require_once __DIR__ . '/../../config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

try {
    $stmt = $db->prepare("SELECT name, is_admin FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $sidebarUser = $stmt->get_result()->fetch_assoc();

    // Verify admin status
    if (!$sidebarUser['is_admin']) {
        header('Location: ../dashboard.php');
        exit;
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<div 
    x-cloak
    :class="{'translate-x-0': sidebarOpen, '-translate-x-full': !sidebarOpen}"
    class="fixed inset-y-0 left-0 w-64 bg-gray-800 transform transition-transform duration-200 ease-in-out lg:translate-x-0 z-20">
    <div class="flex items-center justify-between h-16 bg-gray-900 px-4">
        <a href="/dashboard" class="text-white text-xl font-bold">OneNetly Admin</a>
        <!-- Mobile Close Button -->
        <button @click="sidebarOpen = false" class="lg:hidden p-2 rounded-md text-gray-400 hover:text-white hover:bg-gray-700">
            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
    
    <nav class="mt-6">
        <div class="px-4 py-3">
            <p class="text-gray-400 text-sm">Welcome, <?php echo htmlspecialchars($sidebarUser['name']); ?></p>
        </div>

        <div class="space-y-2 px-4">
            <!-- Dashboard -->
            <a href="dashboard.php" class="flex items-center px-4 py-3 text-gray-300 hover:bg-gray-700 rounded-lg transition-colors <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'bg-gray-700' : ''; ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span>Dashboard</span>
            </a>

            <!-- User Management -->
            <a href="/admin/users.php" class="flex items-center px-4 py-3 text-gray-300 hover:bg-gray-700 rounded-lg transition-colors <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'bg-gray-700' : ''; ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <span>Users</span>
            </a>

            <!-- Files Management -->
            <a href="/admin/files.php" class="flex items-center px-4 py-3 text-gray-300 hover:bg-gray-700 rounded-lg transition-colors <?php echo basename($_SERVER['PHP_SELF']) === 'files.php' ? 'bg-gray-700' : ''; ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>Files</span>
            </a>

            <!-- Settings -->
            <a href="settings.php" class="flex items-center px-4 py-3 text-gray-300 hover:bg-gray-700 rounded-lg transition-colors <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'bg-gray-700' : ''; ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span>Settings</span>
            </a>

            <!-- Email Settings -->
            <a href="email-settings.php" class="flex items-center px-4 py-3 text-gray-300 hover:bg-gray-700 rounded-lg transition-colors <?php echo basename($_SERVER['PHP_SELF']) === 'email-settings.php' ? 'bg-gray-700' : ''; ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                <span>Email Settings</span>
            </a>

            <!-- Dropbox -->
            <a href="dropbox.php" class="flex items-center px-4 py-3 text-gray-300 hover:bg-gray-700 rounded-lg transition-colors <?php echo basename($_SERVER['PHP_SELF']) === 'dropbox.php' ? 'bg-gray-700' : ''; ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                </svg>
                <span>Dropbox</span>
            </a>

            <!-- Reports -->
            <a href="reports.php" class="flex items-center px-4 py-3 text-gray-300 hover:bg-gray-700 rounded-lg transition-colors <?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'bg-gray-700' : ''; ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span>Reports</span>
            </a>
            
        </div>

        <div class="px-4 mt-8">
            <hr class="border-gray-700">
        </div>

        <!-- Back to Main Site -->
        <div class="px-4 mt-4">
            <a href="../dashboard" class="flex items-center px-4 py-3 text-gray-300 hover:bg-gray-700 rounded-lg transition-colors">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12" />
                </svg>
                <span>Back to Site</span>
            </a>
        </div>

        <!-- Logout -->
        <div class="px-4 mt-2">
            <form action="../logout.php" method="POST">
                <button type="submit" class="w-full flex items-center px-4 py-3 text-gray-300 hover:bg-red-600 rounded-lg transition-colors">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span>Logout</span>
                </button>
            </form>
        </div>
    </nav>
</div>
