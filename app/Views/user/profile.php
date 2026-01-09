<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - <?php echo getSiteName(); ?></title>
    <link rel="icon" type="image/png" href="/icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include __DIR__ . '/../partials/header.php'; ?>

    <div class="container mx-auto px-4 py-8 max-w-2xl">
        <h1 class="text-3xl font-bold mb-8">Profile Settings</h1>

        <?php if (isset($success)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
        </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
        </div>
        <?php endif; ?>

        <!-- Profile Information -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold mb-6 flex items-center">
                <i class="fas fa-user mr-2 text-blue-600"></i>
                Profile Information
            </h2>

            <form method="POST" action="/profile">
                <div class="mb-4">
                    <label for="name" class="block text-gray-700 font-medium mb-2">
                        <i class="fas fa-user-circle mr-1"></i> Full Name
                    </label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           value="<?php echo htmlspecialchars($user['name']); ?>" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           required>
                </div>

                <div class="mb-6">
                    <label for="email" class="block text-gray-700 font-medium mb-2">
                        <i class="fas fa-envelope mr-1"></i> Email Address
                    </label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           value="<?php echo htmlspecialchars($user['email']); ?>" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           required>
                </div>

                <button type="submit" 
                        class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition duration-200 font-medium">
                    <i class="fas fa-save mr-2"></i>Update Profile
                </button>
            </form>
        </div>

        <!-- Account Information -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4 flex items-center">
                <i class="fas fa-info-circle mr-2 text-green-600"></i>
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
            
            <a href="/reset-password" 
               class="inline-block bg-orange-600 text-white py-2 px-6 rounded-lg hover:bg-orange-700 transition duration-200 font-medium">
                <i class="fas fa-key mr-2"></i>Change Password
            </a>
        </div>

        <!-- Danger Zone -->
        <div class="bg-red-50 border border-red-200 rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-4 flex items-center text-red-700">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                Danger Zone
            </h2>
            
            <p class="text-gray-700 mb-4">
                Once you delete your account, there is no going back. All your files and data will be permanently deleted.
            </p>
            
            <button onclick="confirmDelete()" 
                    class="bg-red-600 text-white py-2 px-6 rounded-lg hover:bg-red-700 transition duration-200 font-medium">
                <i class="fas fa-trash-alt mr-2"></i>Delete Account
            </button>
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
