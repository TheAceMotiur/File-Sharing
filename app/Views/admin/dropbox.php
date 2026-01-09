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
        <?php $currentPage = 'dropbox'; include __DIR__ . '/partials/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto">
            <div class="p-8">
                <div class="mb-6">
                    <h2 class="text-3xl font-bold">Dropbox Settings</h2>
                </div>

                <?php if (isset($success)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($success); ?>
                </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <!-- Connected Accounts List -->
                <?php if (!empty($accounts)): ?>
                <div class="bg-white rounded-lg shadow mb-6">
                    <div class="p-6 border-b">
                        <h3 class="text-xl font-semibold">Connected Dropbox Accounts</h3>
                    </div>
                    <div class="divide-y">
                        <?php foreach ($accounts as $account): ?>
                        <div class="p-6 flex items-center justify-between hover:bg-gray-50">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                                        <i class="fab fa-dropbox"></i> Account #<?php echo $account['id']; ?>
                                    </span>
                                    <?php if (!empty($account['access_token'])): ?>
                                    <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm">
                                        <i class="fas fa-check-circle"></i> Connected
                                    </span>
                                    <?php else: ?>
                                    <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm">
                                        <i class="fas fa-exclamation-circle"></i> Not Connected
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <div class="space-y-1 text-sm text-gray-600">
                                    <div><span class="font-medium">App Key:</span> <?php echo substr($account['app_key'], 0, 20) . '...'; ?></div>
                                    <?php if (!empty($account['access_token'])): ?>
                                    <div><span class="font-medium">Token:</span> <?php echo substr($account['access_token'], 0, 20) . '...'; ?></div>
                                    <?php endif; ?>
                                    <div><span class="font-medium">Added:</span> <?php echo date('M d, Y g:i A', strtotime($account['created_at'])); ?></div>
                                    <?php
                                    $usedGB = $account['used_storage'] / (1024 * 1024 * 1024);
                                    $percentage = ($account['used_storage'] / (2 * 1024 * 1024 * 1024)) * 100;
                                    $progressColor = $percentage > 90 ? 'bg-red-500' : ($percentage > 70 ? 'bg-yellow-500' : 'bg-green-500');
                                    ?>
                                    <div class="mt-2">
                                        <div class="flex justify-between text-xs mb-1">
                                            <span class="font-medium">Storage:</span>
                                            <span><?php echo number_format($usedGB, 2); ?> / 2 GB (<?php echo number_format($percentage, 1); ?>%)</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="<?php echo $progressColor; ?> h-2 rounded-full" style="width: <?php echo min($percentage, 100); ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <?php if (empty($account['access_token'])): ?>
                                <button onclick="initiateOAuth(<?php echo $account['id']; ?>, '<?php echo htmlspecialchars($account['app_key']); ?>', '<?php echo htmlspecialchars($account['app_secret']); ?>')" 
                                        class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 text-sm">
                                    <i class="fab fa-dropbox"></i> Connect
                                </button>
                                <?php endif; ?>
                                <a href="/admin/dropbox?action=delete&id=<?php echo $account['id']; ?>" 
                                   onclick="return confirm('Are you sure you want to delete this Dropbox account?')"
                                   class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 text-sm">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Add New Dropbox Account -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-xl font-semibold mb-4">
                        <i class="fas fa-plus-circle"></i> Add New Dropbox Account
                    </h3>
                    
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                App Key <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="app_key" required
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="Enter Dropbox App Key">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                App Secret <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="app_secret" required
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="Enter Dropbox App Secret">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Access Token (Optional)
                            </label>
                            <input type="text" name="access_token"
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="Leave empty to use OAuth flow">
                            <p class="text-sm text-gray-500 mt-1">
                                You can add the account first, then connect it via OAuth
                            </p>
                        </div>

                        <div>
                            <button type="submit" class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                                <i class="fas fa-plus"></i> Add Dropbox Account
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Help Section -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-blue-900 mb-2">
                        <i class="fas fa-info-circle"></i> How to Setup Multiple Dropbox Accounts
                    </h3>
                    <ol class="list-decimal list-inside space-y-2 text-blue-800">
                        <li>Create a Dropbox App at <a href="https://www.dropbox.com/developers/apps" target="_blank" class="underline">Dropbox Developers</a></li>
                        <li>Copy the App Key and App Secret for each account</li>
                        <li>Add redirect URI: <code class="bg-white px-2 py-1 rounded"><?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/dropbox/callback'; ?></code></li>
                        <li>Add the account using the form above</li>
                        <li>Click "Connect" button to authorize via OAuth</li>
                        <li>Repeat for each additional Dropbox account you want to add</li>
                    </ol>
                    <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded">
                        <p class="text-sm text-yellow-800">
                            <i class="fas fa-lightbulb"></i> <strong>Tip:</strong> Multiple accounts allow you to distribute files across different Dropbox accounts for better storage management and redundancy.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function initiateOAuth(accountId, appKey, appSecret) {
        if (!appKey || !appSecret) {
            alert('App Key and App Secret are required');
            return;
        }
        
        const state = btoa(JSON.stringify({
            account_id: accountId,
            app_key: appKey,
            app_secret: appSecret
        }));
        
        const host = '<?php echo $_SERVER['HTTP_HOST']; ?>';
        const redirectUri = encodeURIComponent('https://' + host + '/dropbox/callback');
        const authUrl = `https://www.dropbox.com/oauth2/authorize?client_id=${appKey}&redirect_uri=${redirectUri}&response_type=code&state=${state}&token_access_type=offline`;
        
        window.location.href = authUrl;
    }
    </script>
</body>
</html>