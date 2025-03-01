<div class="relative min-h-screen flex flex-col">

        <!-- Main Content -->
        <div class="flex-1">
            <!-- Top Navigation Bar -->
            <nav class="bg-white shadow">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between h-16">
                        <div class="flex">                           
                            <!-- Logo -->
                            <div class="flex-shrink-0 flex items-center">
                                <a href="/" class="text-xl font-bold text-blue-600">
                                    <?php echo SITE_NAME; ?>
                                </a>
                            </div>
                        </div>
 
                        <!-- Right Navigation -->
                        <div class="flex items-center space-x-4">
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <!-- Dashboard Button -->
                                <a href="/dashboard" class="text-gray-700 hover:text-gray-900 px-3 py-2">
                                    <span class="flex items-center">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                        </svg>
                                        Dashboard
                                    </span>
                                </a>

                                <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                                    <!-- Admin Button -->
                                    <a href="/admin/dashboard.php" 
                                       class="text-gray-700 hover:text-gray-900 px-3 py-2 relative group">
                                        <span class="flex items-center">
                                            <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z" />
                                            </svg>
                                        </span>
                                    </a>
                                <?php endif; ?>

                                <!-- User Dropdown -->
                                <div class="relative" x-data="{ open: false }">
                                    <button @click="open = !open" class="flex items-center space-x-2 text-gray-700 hover:text-gray-900">
                                        <span><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></span>
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>
                                    
                                    <!-- Dropdown Menu -->
                                    <div x-show="open" 
                                         @click.away="open = false"
                                         class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1">
                                        <a href="/profile" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Profile</a>
                                        <a href="/keys" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">API Keys</a>
                                        <form action="/logout" method="POST">
                                            <button type="submit" class="block w-full text-left px-4 py-2 text-gray-700 hover:bg-gray-100">
                                                Logout
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php else: ?>
                                <a href="/login" class="text-gray-700 hover:text-gray-900 px-3 py-2">Login</a>
                                <a href="/register" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">Register</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </nav>