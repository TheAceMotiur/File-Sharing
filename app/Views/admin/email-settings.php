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
        <?php $currentPage = 'email-settings'; include __DIR__ . '/partials/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto">
            <div class="p-8">
                <div class="mb-6">
                    <h2 class="text-3xl font-bold">Email Settings</h2>
                </div>

                <?php if (isset($success)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    Email settings saved successfully!
                </div>
                <?php endif; ?>

                <!-- Email Settings Form -->
                <div class="bg-white rounded-lg shadow p-6">
                    <form method="POST" class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                SMTP Host
                            </label>
                            <input type="text" name="smtp_host" 
                                   value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="smtp.gmail.com">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                SMTP Port
                            </label>
                            <input type="number" name="smtp_port" 
                                   value="<?php echo $settings['smtp_port'] ?? 587; ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                SMTP Username
                            </label>
                            <input type="text" name="smtp_username" 
                                   value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="your-email@gmail.com">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                SMTP Password
                            </label>
                            <input type="password" name="smtp_password" 
                                   value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="••••••••">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                From Email
                            </label>
                            <input type="email" name="from_email" 
                                   value="<?php echo htmlspecialchars($settings['from_email'] ?? ''); ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="noreply@yourdomain.com">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                From Name
                            </label>
                            <input type="text" name="from_name" 
                                   value="<?php echo htmlspecialchars($settings['from_name'] ?? 'OneNetly'); ?>"
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" name="smtp_secure" value="tls"
                                   <?php echo ($settings['smtp_secure'] ?? 'tls') === 'tls' ? 'checked' : ''; ?>
                                   class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <label class="ml-2 text-sm text-gray-700">
                                Use TLS encryption
                            </label>
                        </div>

                        <div>
                            <button type="submit" class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                                <i class="fas fa-save"></i> Save Email Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
