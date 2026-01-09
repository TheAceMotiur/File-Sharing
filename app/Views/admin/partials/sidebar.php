<!-- Sidebar -->
<div class="w-64 bg-gray-800 text-white flex-shrink-0 overflow-y-auto">
    <div class="p-4 border-b border-gray-700">
        <h1 class="text-2xl font-bold">Admin Panel</h1>
    </div>
    <nav class="mt-2">
        <!-- Dashboard -->
        <a href="/admin" class="block px-4 py-3 hover:bg-gray-700 <?php echo ($currentPage ?? '') === 'dashboard' ? 'bg-gray-700 border-l-4 border-blue-500' : ''; ?>">
            <i class="fas fa-chart-line mr-2"></i> Dashboard
        </a>

        <!-- Content Management -->
        <div class="mt-2">
            <button onclick="toggleDropdown('content')" class="w-full text-left px-4 py-3 hover:bg-gray-700 flex items-center justify-between">
                <span><i class="fas fa-folder mr-2"></i> Content</span>
                <i class="fas fa-chevron-down transition-transform" id="content-icon"></i>
            </button>
            <div id="content-menu" class="bg-gray-900 hidden">
                <a href="/admin/files" class="block px-8 py-2 hover:bg-gray-700 text-sm <?php echo ($currentPage ?? '') === 'files' ? 'bg-gray-700' : ''; ?>">
                    <i class="fas fa-file mr-2"></i> Files
                </a>
                <a href="/admin/reports" class="block px-8 py-2 hover:bg-gray-700 text-sm <?php echo ($currentPage ?? '') === 'reports' ? 'bg-gray-700' : ''; ?>">
                    <i class="fas fa-flag mr-2"></i> Reports
                </a>
            </div>
        </div>

        <!-- User Management -->
        <a href="/admin/users" class="block px-4 py-3 hover:bg-gray-700 <?php echo ($currentPage ?? '') === 'users' ? 'bg-gray-700 border-l-4 border-blue-500' : ''; ?>">
            <i class="fas fa-users mr-2"></i> Users
        </a>

        <!-- Settings -->
        <div class="mt-2">
            <button onclick="toggleDropdown('settings')" class="w-full text-left px-4 py-3 hover:bg-gray-700 flex items-center justify-between">
                <span><i class="fas fa-cog mr-2"></i> Settings</span>
                <i class="fas fa-chevron-down transition-transform" id="settings-icon"></i>
            </button>
            <div id="settings-menu" class="bg-gray-900 hidden">
                <a href="/admin/settings" class="block px-8 py-2 hover:bg-gray-700 text-sm <?php echo ($currentPage ?? '') === 'settings' ? 'bg-gray-700' : ''; ?>">
                    <i class="fas fa-sliders-h mr-2"></i> Site Settings
                </a>
                <a href="/admin/email-settings" class="block px-8 py-2 hover:bg-gray-700 text-sm <?php echo ($currentPage ?? '') === 'email-settings' ? 'bg-gray-700' : ''; ?>">
                    <i class="fas fa-envelope mr-2"></i> Email Settings
                </a>
                <a href="/admin/dropbox" class="block px-8 py-2 hover:bg-gray-700 text-sm <?php echo ($currentPage ?? '') === 'dropbox' ? 'bg-gray-700' : ''; ?>">
                    <i class="fab fa-dropbox mr-2"></i> Dropbox
                </a>
            </div>
        </div>

        <!-- System -->
        <div class="mt-2">
            <button onclick="toggleDropdown('system')" class="w-full text-left px-4 py-3 hover:bg-gray-700 flex items-center justify-between">
                <span><i class="fas fa-server mr-2"></i> System</span>
                <i class="fas fa-chevron-down transition-transform" id="system-icon"></i>
            </button>
            <div id="system-menu" class="bg-gray-900 hidden">
                <a href="/admin/cron-jobs" class="block px-8 py-2 hover:bg-gray-700 text-sm <?php echo ($currentPage ?? '') === 'cron-jobs' ? 'bg-gray-700' : ''; ?>">
                    <i class="fas fa-clock mr-2"></i> Cron Jobs
                </a>
                <a href="/admin/cache" class="block px-8 py-2 hover:bg-gray-700 text-sm <?php echo ($currentPage ?? '') === 'cache' ? 'bg-gray-700' : ''; ?>">
                    <i class="fas fa-database mr-2"></i> Cache
                </a>
            </div>
        </div>

        <!-- Navigation -->
        <div class="mt-4 pt-4 border-t border-gray-700">
            <a href="/dashboard" class="block px-4 py-3 hover:bg-gray-700">
                <i class="fas fa-home mr-2"></i> Back to Site
            </a>
            <a href="/logout" class="block px-4 py-3 hover:bg-gray-700">
                <i class="fas fa-sign-out-alt mr-2"></i> Logout
            </a>
        </div>
    </nav>
</div>

<script>
function toggleDropdown(id) {
    const menu = document.getElementById(id + '-menu');
    const icon = document.getElementById(id + '-icon');
    
    if (menu.classList.contains('hidden')) {
        menu.classList.remove('hidden');
        icon.style.transform = 'rotate(180deg)';
    } else {
        menu.classList.add('hidden');
        icon.style.transform = 'rotate(0deg)';
    }
}

// Auto-expand dropdown if current page is in it
document.addEventListener('DOMContentLoaded', function() {
    const currentPage = '<?php echo $currentPage ?? ''; ?>';
    
    // Content dropdown
    if (['files', 'reports'].includes(currentPage)) {
        toggleDropdown('content');
    }
    
    // Settings dropdown
    if (['settings', 'email-settings', 'dropbox'].includes(currentPage)) {
        toggleDropdown('settings');
    }
    
    // System dropdown
    if (['cron-jobs', 'cache'].includes(currentPage)) {
        toggleDropdown('system');
    }
});
</script>
