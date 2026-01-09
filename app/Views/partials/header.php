<header class="bg-white shadow">
    <nav class="container mx-auto px-4 py-4">
        <div class="flex justify-between items-center">
            <a href="/" class="text-2xl font-bold text-blue-600">
                <?php echo getSiteName(); ?>
            </a>
            
            <div class="flex items-center space-x-4">
                <?php if (isLoggedIn()): ?>
                    <a href="/dashboard" class="text-gray-700 hover:text-blue-600">Dashboard</a>
                    <a href="/upload" class="text-gray-700 hover:text-blue-600">Upload</a>
                    <a href="/profile" class="text-gray-700 hover:text-blue-600">Profile</a>
                    <?php if (isAdmin()): ?>
                        <a href="/admin" class="text-gray-700 hover:text-blue-600">Admin</a>
                    <?php endif; ?>
                    <a href="/logout" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">Logout</a>
                <?php else: ?>
                    <a href="/login" class="text-gray-700 hover:text-blue-600">Login</a>
                    <a href="/register" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
</header>
