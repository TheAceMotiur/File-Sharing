<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - <?php echo getSiteName(); ?></title>
    <link rel="icon" type="image/png" href="/icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include __DIR__ . '/../partials/styles.php'; ?>
</head>
<body x-data="{ sidebarOpen: false }">
    <?php 
    $currentPage = 'profile';
    include __DIR__ . '/../partials/header.php'; 
    include __DIR__ . '/../partials/sidebar.php';
    ?>

    <div class="main-content">
        <div class="min-h-screen py-8">
        <div class="container mx-auto px-4 max-w-4xl">
            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                    <i class="fas fa-user-circle text-blue-600"></i>
                    Profile Settings
                </h1>
                <p class="text-gray-600 mt-2">Manage your account settings and preferences</p>
            </div>

        <?php if (isset($success)): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center gap-2 profile-card">
            <i class="fas fa-check-circle"></i>
            <span><?php echo $success; ?></span>
        </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center gap-2 profile-card">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
        <?php endif; ?>

        <!-- Profile Information -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6 profile-card">
            <h2 class="text-xl font-semibold mb-6 flex items-center gap-2">
                <i class="fas fa-user text-blue-600"></i>
                Profile Information
            </h2>

            <form method="POST" action="/profile">
                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-user-circle mr-1 text-gray-400"></i> Full Name
                    </label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           value="<?php echo htmlspecialchars($user['name']); ?>" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                           required>
                </div>

                <div class="mb-6">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-envelope mr-1 text-gray-400"></i> Email Address
                    </label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           value="<?php echo htmlspecialchars($user['email']); ?>" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                           required>
                </div>

                <button type="submit" 
                        class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 transition-all font-medium shadow-sm hover:shadow-md">
                    <i class="fas fa-save mr-2"></i>Update Profile
                </button>
            </form>
        </div>

        <!-- Account Information -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6 profile-card">
            <h2 class="text-xl font-semibold mb-4 flex items-center gap-2">
                <i class="fas fa-info-circle text-green-600"></i>
                Account Information
            </h2>
            
            <div class="space-y-3">
                <div class="flex justify-between py-2 border-b">
                    <span class="text-gray-600">Account Status:</span>
                    <span class="font-medium">
                        <?php if ($user['email_verified']): ?>
                            <span class="text-green-600"><i class="fas fa-check-circle"></i> Verified</span>
                        <?php else: ?>
                            <span class="text-yellow-600"><i class="fas fa-clock"></i> Pending Verification</span>
                        <?php endif; ?>
                    </span>
                </div>
                
                <div class="flex justify-between py-2 border-b">
                    <span class="text-gray-600">Account Type:</span>
                    <span class="font-medium">
                        <?php if (isset($user['is_premium']) && $user['is_premium']): ?>
                            <span class="text-purple-600"><i class="fas fa-crown"></i> Premium</span>
                        <?php else: ?>
                            <span class="text-gray-600">Free</span>
                        <?php endif; ?>
                    </span>
                </div>
                
                <div class="flex justify-between py-2 border-b">
                    <span class="text-gray-600">Member Since:</span>
                    <span class="font-medium"><?php echo date('F d, Y', strtotime($user['created_at'])); ?></span>
                </div>
                
                <div class="flex justify-between py-2">
                    <span class="text-gray-600">User ID:</span>
                    <span class="font-medium text-gray-500">#<?php echo $user['id']; ?></span>
                </div>
            </div>
        </div>

        <!-- Change Password -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4 flex items-center">
                <i class="fas fa-lock mr-2 text-orange-600"></i>
                Change Password
            </h2>
            
        <!-- Change Password -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6 profile-card">
            <h2 class="text-xl font-semibold mb-4 flex items-center gap-2">
                <i class="fas fa-lock text-orange-600"></i>
                Change Password
            </h2>
            
            <a href="/reset-password" 
               class="inline-flex items-center gap-2 bg-orange-600 text-white py-3 px-6 rounded-lg hover:bg-orange-700 transition-all font-medium shadow-sm hover:shadow-md">
                <i class="fas fa-key"></i>
                <span>Change Password</span>
            </a>
        </div>

        <!-- Danger Zone -->
        <div class="bg-red-50 border border-red-200 rounded-xl p-6 profile-card">
            <h2 class="text-xl font-semibold mb-4 flex items-center gap-2 text-red-700">
                <i class="fas fa-exclamation-triangle"></i>
                Danger Zone
            </h2>
            
            <p class="text-gray-700 mb-4">
                Once you delete your account, there is no going back. All your files and data will be permanently deleted.
            </p>
            
            <button onclick="confirmDelete()" 
                    class="inline-flex items-center gap-2 bg-red-600 text-white py-3 px-6 rounded-lg hover:bg-red-700 transition-all font-medium shadow-sm hover:shadow-md">
                <i class="fas fa-trash-alt"></i>
                <span>Delete Account</span>
            </button>
        </div>
    </div>
    </div>

    <?php include __DIR__ . '/../partials/footer.php'; ?>

    <script>
    function confirmDelete() {
        if (confirm('Are you sure you want to delete your account?\n\nThis will permanently delete:\n- Your profile\n- All uploaded files\n- All folders\n- All data\n\nThis action cannot be undone!')) {
            if (confirm('This is your final warning. Are you absolutely sure?')) {
                // Implement account deletion endpoint
                alert('Account deletion feature will be implemented.');
            }
        }
    }
    </script>
</body>
</html>
