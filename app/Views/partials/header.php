<header class="bg-white border-b border-gray-200 fixed top-0 left-0 right-0 z-50 shadow-sm">
    <nav class="px-8 py-4">
        <div class="flex justify-between items-center gap-6">
            <!-- Hamburger Menu + Logo -->
            <div class="flex items-center gap-4">
                <?php if (isLoggedIn()): ?>
                <button @click="sidebarOpen = !sidebarOpen" class="md:hidden w-11 h-11 flex items-center justify-center rounded-full hover:bg-gray-100 transition-colors">
                    <i class="fas fa-bars text-gray-600 text-lg"></i>
                </button>
                <?php endif; ?>
                <a href="<?php echo isLoggedIn() ? '/dashboard' : '/'; ?>" class="flex items-center gap-3 hover:opacity-80 transition-opacity">
                    <i class="fas fa-cloud text-3xl text-blue-600"></i>
                    <h1 class="text-xl font-bold text-gray-800"><?php echo getSiteName(); ?></h1>
                </a>
            </div>
            
            <!-- Search Bar (Desktop) -->
            <?php if (isset($currentPage) && $currentPage === 'dashboard'): ?>
            <div class="flex-1 max-w-3xl mx-8 hidden md:block">
                <div class="relative">
                    <i class="fas fa-search absolute left-5 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="text" 
                           v-model="searchQuery"
                           placeholder="Search files..."
                           class="w-full pl-14 pr-5 py-3 bg-gray-50 rounded-xl border border-gray-200 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                </div>
            </div>
            <?php endif; ?>
            
            <!-- User Menu -->
            <div class="flex items-center gap-3">
                <?php if (isLoggedIn()): ?>
                    <?php if (isset($currentPage) && $currentPage === 'dashboard'): ?>
                    <!-- View Mode Toggle (Dashboard Only) -->
                    <button v-on:click="viewMode = viewMode === 'grid' ? 'list' : 'grid'" 
                            x-ignore
                            class="w-11 h-11 flex items-center justify-center rounded-full hover:bg-gray-100 transition-all duration-200">
                        <i v-bind:class="viewMode === 'grid' ? 'fas fa-th' : 'fas fa-list'" class="text-gray-600 text-lg"></i>
                    </button>
                    <?php endif; ?>
                    
                    <!-- Profile Dropdown -->
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" 
                                @click.away="open = false"
                                class="flex items-center gap-3 px-4 py-2 rounded-xl hover:bg-gray-100 transition-all duration-200">
                            <div class="w-9 h-9 bg-gradient-to-br from-blue-600 to-blue-700 rounded-full flex items-center justify-center text-white font-bold shadow-md">
                                <?php echo strtoupper(substr($_SESSION['user']['name'] ?? 'U', 0, 1)); ?>
                            </div>
                            <span class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($_SESSION['user']['name'] ?? 'User'); ?></span>
                            <i class="fas fa-chevron-down text-xs text-gray-500"></i>
                        </button>
                        
                        <!-- Dropdown Menu -->
                        <div x-show="open" 
                             x-transition
                             class="absolute right-0 mt-3 w-64 bg-white rounded-xl shadow-xl border border-gray-200 py-3 z-50">
                            <div class="px-5 py-4 border-b border-gray-100">
                                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($_SESSION['user']['name'] ?? 'User'); ?></p>
                                <p class="text-xs text-gray-500"><?php echo htmlspecialchars($_SESSION['user']['email'] ?? ''); ?></p>
                            </div>
                            <a href="/dashboard" class="flex items-center gap-3 px-5 py-3 text-sm text-gray-700 hover:bg-gray-50 transition-colors rounded-lg mx-2">
                                <i class="fas fa-folder w-5 text-base"></i>
                                <span class="font-medium">My Files</span>
                            </a>
                            <a href="/profile" class="flex items-center gap-3 px-5 py-3 text-sm text-gray-700 hover:bg-gray-50 transition-colors rounded-lg mx-2">
                                <i class="fas fa-user w-5 text-base"></i>
                                <span class="font-medium">Profile Settings</span>
                            </a>
                            <?php if (isAdmin()): ?>
                            <a href="/admin" class="flex items-center gap-3 px-5 py-3 text-sm text-gray-700 hover:bg-gray-50 transition-colors rounded-lg mx-2">
                                <i class="fas fa-cog w-5 text-base"></i>
                                <span class="font-medium">Admin Panel</span>
                            </a>
                            <?php endif; ?>
                            <hr class="my-3 border-gray-100">
                            <a href="/logout" class="flex items-center gap-3 px-5 py-3 text-sm text-red-600 hover:bg-red-50 transition-colors rounded-lg mx-2 font-medium">
                                <i class="fas fa-sign-out-alt w-5 text-base"></i>
                                <span>Sign Out</span>
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="/login" class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-blue-600">Login</a>
                    <a href="/register" class="px-4 py-2 text-sm font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-700">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
</header>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
