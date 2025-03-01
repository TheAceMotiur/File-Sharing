<?php
require_once __DIR__ . '/../config.php';
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../utils/dropbox_helper.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

try {
    $db = getDBConnection();
    
    // Verify admin status
    $stmt = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if (!$user['is_admin']) {
        header('Location: ../dashboard.php');
        exit;
    }
    
    $message = null;
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_status':
                    // Update storage status manually
                    if (isset($_POST['account_id'])) {
                        $accountId = $_POST['account_id'];
                        $stmt = $db->prepare("SELECT * FROM dropbox_accounts WHERE id = ?");
                        $stmt->bind_param("i", $accountId);
                        $stmt->execute();
                        $account = $stmt->get_result()->fetch_assoc();
                        
                        if ($account) {
                            try {
                                $spaceInfo = DropboxHelper::getAccountSpaceInfo($account);
                                
                                $usedSpace = $spaceInfo['used'];
                                $totalSpace = $spaceInfo['allocation']['allocated'];
                                $availableSpace = $totalSpace - $usedSpace;
                                $isFull = ($availableSpace < 10 * 1024 * 1024); // Mark as full if less than 10MB left
                                
                                // Update database
                                $stmt = $db->prepare("UPDATE dropbox_accounts SET 
                                    is_full = ?, 
                                    last_space_check = NOW(),
                                    available_space = ?,
                                    total_space = ?
                                    WHERE id = ?");
                                    
                                $stmt->bind_param("iiis", 
                                    $isFull,
                                    $availableSpace,
                                    $totalSpace,
                                    $account['id']
                                );
                                
                                $stmt->execute();
                                $message = ['type' => 'success', 'text' => 'Account storage status updated successfully.'];
                            } catch (Exception $e) {
                                $message = ['type' => 'error', 'text' => 'Failed to update storage status: ' . $e->getMessage()];
                            }
                        }
                    }
                    break;
                    
                case 'add_account':
                    // Add new Dropbox account
                    $appKey = $_POST['app_key'];
                    $appSecret = $_POST['app_secret'];
                    $accessToken = $_POST['access_token'];
                    $refreshToken = $_POST['refresh_token'];
                    
                    // Verify tokens by trying to get account info
                    try {
                        $client = new Spatie\Dropbox\Client($accessToken);
                        $accountInfo = $client->rpcEndpointRequest('users/get_current_account');
                        $accountData = json_decode($accountInfo, true);
                        
                        if (!isset($accountData['account_id'])) {
                            throw new Exception('Unable to verify account credentials');
                        }
                        
                        // Check if this account already exists
                        $stmt = $db->prepare("SELECT * FROM dropbox_accounts WHERE app_key = ? OR account_id = ?");
                        $stmt->bind_param("ss", $appKey, $accountData['account_id']);
                        $stmt->execute();
                        if ($stmt->get_result()->num_rows > 0) {
                            throw new Exception('This Dropbox account is already registered');
                        }
                        
                        // Insert the new account
                        $stmt = $db->prepare("INSERT INTO dropbox_accounts 
                            (app_key, app_secret, access_token, refresh_token, account_id, account_email, display_name) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)");
                        
                        $email = $accountData['email'] ?? 'unknown';
                        $name = $accountData['name']['display_name'] ?? 'Dropbox Account';
                        $stmt->bind_param("sssssss", 
                            $appKey, 
                            $appSecret, 
                            $accessToken, 
                            $refreshToken, 
                            $accountData['account_id'],
                            $email,
                            $name
                        );
                        
                        $stmt->execute();
                        
                        // Get space info and update it
                        $spaceInfo = DropboxHelper::getAccountSpaceInfo([
                            'access_token' => $accessToken,
                            'refresh_token' => $refreshToken,
                            'app_key' => $appKey,
                            'app_secret' => $appSecret,
                            'id' => $db->insert_id
                        ]);
                        
                        $usedSpace = $spaceInfo['used'];
                        $totalSpace = $spaceInfo['allocation']['allocated'];
                        $availableSpace = $totalSpace - $usedSpace;
                        $isFull = ($availableSpace < 10 * 1024 * 1024); // Mark as full if less than 10MB
                        
                        $stmt = $db->prepare("UPDATE dropbox_accounts SET 
                            is_full = ?, 
                            last_space_check = NOW(),
                            available_space = ?,
                            total_space = ?
                            WHERE id = ?");
                        
                        $id = $db->insert_id;
                        $stmt->bind_param("iiii", 
                            $isFull,
                            $availableSpace,
                            $totalSpace,
                            $id
                        );
                        
                        $stmt->execute();
                        
                        $message = ['type' => 'success', 'text' => 'New Dropbox account added successfully.'];
                    } catch (Exception $e) {
                        $message = ['type' => 'error', 'text' => 'Failed to add Dropbox account: ' . $e->getMessage()];
                    }
                    break;
                    
                case 'toggle_account':
                    // Enable/disable account
                    if (isset($_POST['account_id'])) {
                        $accountId = $_POST['account_id'];
                        $isActive = isset($_POST['is_active']) ? 1 : 0;
                        
                        $stmt = $db->prepare("UPDATE dropbox_accounts SET is_active = ? WHERE id = ?");
                        $stmt->bind_param("ii", $isActive, $accountId);
                        
                        if ($stmt->execute()) {
                            $status = $isActive ? 'enabled' : 'disabled';
                            $message = ['type' => 'success', 'text' => "Account {$status} successfully."];
                        } else {
                            $message = ['type' => 'error', 'text' => 'Failed to update account status: ' . $db->error];
                        }
                    }
                    break;
                    
                case 'delete_account':
                    // Delete account
                    if (isset($_POST['account_id'])) {
                        $accountId = $_POST['account_id'];
                        
                        // Check if there are files using this account
                        $stmt = $db->prepare("SELECT COUNT(*) as file_count FROM file_uploads WHERE dropbox_account_id = ?");
                        $stmt->bind_param("i", $accountId);
                        $stmt->execute();
                        $result = $stmt->get_result()->fetch_assoc();
                        
                        if ($result['file_count'] > 0) {
                            $message = ['type' => 'error', 'text' => "Cannot delete account with ID {$accountId} because it has {$result['file_count']} files associated with it."];
                        } else {
                            $stmt = $db->prepare("DELETE FROM dropbox_accounts WHERE id = ?");
                            $stmt->bind_param("i", $accountId);
                            
                            if ($stmt->execute()) {
                                $message = ['type' => 'success', 'text' => 'Account deleted successfully.'];
                            } else {
                                $message = ['type' => 'error', 'text' => 'Failed to delete account: ' . $db->error];
                            }
                        }
                    }
                    break;
                
                case 'refresh_token':
                    // Manually refresh an account's token
                    if (isset($_POST['account_id'])) {
                        $accountId = $_POST['account_id'];
                        
                        $stmt = $db->prepare("SELECT * FROM dropbox_accounts WHERE id = ?");
                        $stmt->bind_param("i", $accountId);
                        $stmt->execute();
                        $account = $stmt->get_result()->fetch_assoc();
                        
                        if ($account) {
                            try {
                                $updatedAccount = DropboxHelper::refreshToken($account);
                                $message = ['type' => 'success', 'text' => 'Token refreshed successfully.'];
                            } catch (Exception $e) {
                                $message = ['type' => 'error', 'text' => 'Failed to refresh token: ' . $e->getMessage()];
                            }
                        }
                    }
                    break;
                
                case 'update_all':
                    // Update all storage statuses
                    exec('php ' . __DIR__ . '/../cron/update_storage_status.php', $output, $returnVar);
                    
                    if ($returnVar === 0) {
                        $message = ['type' => 'success', 'text' => 'All account storage statuses updated.'];
                    } else {
                        $message = ['type' => 'error', 'text' => 'Failed to update all accounts: ' . implode("\n", $output)];
                    }
                    break;
            }
        }
    }
    
    // Get all Dropbox accounts
    $accounts = $db->query("
        SELECT da.*, 
               COALESCE(SUM(fu.size), 0) as used_storage_bytes,
               COUNT(fu.id) as file_count
        FROM dropbox_accounts da
        LEFT JOIN file_uploads fu ON fu.dropbox_account_id = da.id AND fu.upload_status = 'completed'
        GROUP BY da.id
        ORDER BY da.id ASC
    ")->fetch_all(MYSQLI_ASSOC);
    
    // Get total storage stats
    $stats = $db->query("
        SELECT 
            COUNT(*) as total_accounts,
            SUM(IF(is_active = 1 AND is_full = 0, 1, 0)) as available_accounts,
            SUM(IF(is_full = 1, 1, 0)) as full_accounts,
            SUM(available_space) as total_available_space,
            SUM(total_space) as total_capacity
        FROM dropbox_accounts
    ")->fetch_assoc();
    
    // Get recent uploads
    $recentUploads = $db->query("
        SELECT fu.*, u.username, da.app_key 
        FROM file_uploads fu
        JOIN users u ON fu.uploaded_by = u.id
        JOIN dropbox_accounts da ON fu.dropbox_account_id = da.id
        ORDER BY fu.upload_date DESC
        LIMIT 10
    ")->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storage Management - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include '../admin/header.php'; ?>
    
    <div class="container mx-auto px-4 py-6">
        <h1 class="text-2xl font-bold mb-6">Storage Management</h1>
        
        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded <?php echo $message['type'] === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
                <?php echo $message['text']; ?>
            </div>
        <?php endif; ?>
        
        <!-- Storage Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-gray-500 text-sm font-medium">Total Accounts</h3>
                <p class="text-2xl font-bold"><?php echo number_format($stats['total_accounts']); ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-gray-500 text-sm font-medium">Available Accounts</h3>
                <p class="text-2xl font-bold"><?php echo number_format($stats['available_accounts']); ?></p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-gray-500 text-sm font-medium">Total Storage</h3>
                <p class="text-2xl font-bold"><?php echo number_format($stats['total_capacity'] / (1024 * 1024 * 1024), 1); ?> GB</p>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h3 class="text-gray-500 text-sm font-medium">Available Storage</h3>
                <p class="text-2xl font-bold"><?php echo number_format($stats['total_available_space'] / (1024 * 1024 * 1024), 1); ?> GB</p>
            </div>
        </div>
        
        <div class="mb-8">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Dropbox Accounts</h2>
                <div>
                    <form method="post" class="inline-block mr-2">
                        <input type="hidden" name="action" value="update_all">
                        <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                            <i class="fas fa-sync-alt mr-1"></i> Update All
                        </button>
                    </form>
                    <button type="button" onclick="toggleAddAccountForm()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                        <i class="fas fa-plus mr-1"></i> Add Account
                    </button>
                </div>
            </div>
            
            <!-- Add Account Form (Hidden by Default) -->
            <div id="addAccountForm" class="hidden bg-white p-6 rounded-lg shadow-md mb-6">
                <h3 class="text-lg font-semibold mb-4">Add New Dropbox Account</h3>
                <form method="post" class="space-y-4">
                    <input type="hidden" name="action" value="add_account">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">App Key</label>
                            <input type="text" name="app_key" required class="w-full p-2 border border-gray-300 rounded">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">App Secret</label>
                            <input type="text" name="app_secret" required class="w-full p-2 border border-gray-300 rounded">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Access Token</label>
                            <input type="text" name="access_token" required class="w-full p-2 border border-gray-300 rounded">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Refresh Token</label>
                            <input type="text" name="refresh_token" required class="w-full p-2 border border-gray-300 rounded">
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="toggleAddAccountForm()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                            Add Account
                        </button>
                    </div>
                </form>
                
                <div class="mt-4 text-sm text-gray-600">
                    <p class="font-medium mb-1">How to get Dropbox credentials:</p>
                    <ol class="list-decimal list-inside">
                        <li>Create a Dropbox App at <a href="https://www.dropbox.com/developers/apps" target="_blank" class="text-blue-600 hover:underline">https://www.dropbox.com/developers/apps</a></li>
                        <li>Choose "Scoped Access" and "Full Dropbox" access</li>
                        <li>In Permissions, add "files.content.write" and "files.content.read"</li>
                        <li>Generate an access token (set "No expiration")</li>
                        <li>For the refresh token, add "offline.access" permission</li>
                    </ol>
                </div>
            </div>
            
            <!-- Accounts Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white rounded-lg overflow-hidden shadow-md">
                    <thead class="bg-gray-100 text-gray-700">
                        <tr>
                            <th class="py-3 px-4 text-left">ID</th>
                            <th class="py-3 px-4 text-left">App Key</th>
                            <th class="py-3 px-4 text-left">Email</th>
                            <th class="py-3 px-4 text-left">Status</th>
                            <th class="py-3 px-4 text-left">Space</th>
                            <th class="py-3 px-4 text-left">Files</th>
                            <th class="py-3 px-4 text-left">Last Check</th>
                            <th class="py-3 px-4 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($accounts)): ?>
                            <tr>
                                <td colspan="8" class="py-4 px-6 text-center text-gray-500">No accounts found</td>
                            </tr>
                        <?php endif; ?>
                        
                        <?php foreach ($accounts as $account): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-3 px-4"><?php echo $account['id']; ?></td>
                                <td class="py-3 px-4">
                                    <span class="font-mono text-sm"><?php echo substr($account['app_key'], 0, 8) . '...'; ?></span>
                                </td>
                                <td class="py-3 px-4"><?php echo htmlspecialchars($account['account_email']); ?></td>
                                <td class="py-3 px-4">
                                    <?php if (!$account['is_active']): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            Disabled
                                        </span>
                                    <?php elseif ($account['is_full']): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            Full
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Available
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4">
                                    <?php 
                                        $usedGB = number_format($account['used_storage_bytes'] / (1024 * 1024 * 1024), 2);
                                        $totalGB = number_format($account['total_space'] / (1024 * 1024 * 1024), 2);
                                        $percentUsed = $account['total_space'] > 0 ? round(($account['used_storage_bytes'] / $account['total_space']) * 100) : 0;
                                    ?>
                                    <div class="text-sm">
                                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                                            <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?php echo $percentUsed; ?>%"></div>
                                        </div>
                                        <div class="mt-1">
                                            <?php echo $usedGB; ?> GB / <?php echo $totalGB; ?> GB
                                            <span class="text-gray-500">(<?php echo $percentUsed; ?>%)</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <?php echo number_format($account['file_count']); ?>
                                </td>
                                <td class="py-3 px-4 text-sm">
                                    <?php if ($account['last_space_check']): ?>
                                        <?php echo date('Y-m-d H:i', strtotime($account['last_space_check'])); ?>
                                    <?php else: ?>
                                        Never
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex space-x-2">
                                        <!-- Toggle active status -->
                                        <form method="post" class="inline-block">
                                            <input type="hidden" name="action" value="toggle_account">
                                            <input type="hidden" name="account_id" value="<?php echo $account['id']; ?>">
                                            <input type="hidden" name="is_active" value="<?php echo $account['is_active'] ? '0' : '1'; ?>">
                                            <button type="submit" class="text-sm p-1 <?php echo $account['is_active'] ? 'text-yellow-600 hover:text-yellow-700' : 'text-green-600 hover:text-green-700'; ?>" title="<?php echo $account['is_active'] ? 'Disable Account' : 'Enable Account'; ?>">
                                                <i class="fas <?php echo $account['is_active'] ? 'fa-pause' : 'fa-play'; ?>"></i>
                                            </button>
                                        </form>
                                        
                                        <!-- Update storage status -->
                                        <form method="post" class="inline-block">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="account_id" value="<?php echo $account['id']; ?>">
                                            <button type="submit" class="text-sm p-1 text-blue-600 hover:text-blue-700" title="Update Storage Status">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        </form>
                                        
                                        <!-- Refresh token -->
                                        <form method="post" class="inline-block">
                                            <input type="hidden" name="action" value="refresh_token">
                                            <input type="hidden" name="account_id" value="<?php echo $account['id']; ?>">
                                            <button type="submit" class="text-sm p-1 text-purple-600 hover:text-purple-700" title="Refresh Token">
                                                <i class="fas fa-key"></i>
                                            </button>
                                        </form>
                                        
                                        <!-- Delete account (only if no files) -->
                                        <?php if ($account['file_count'] == 0): ?>
                                            <form method="post" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this account?');">
                                                <input type="hidden" name="action" value="delete_account">
                                                <input type="hidden" name="account_id" value="<?php echo $account['id']; ?>">
                                                <button type="submit" class="text-sm p-1 text-red-600 hover:text-red-700" title="Delete Account">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Recent Uploads -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold mb-4">Recent Uploads</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white rounded-lg overflow-hidden shadow-md">
                    <thead class="bg-gray-100 text-gray-700">
                        <tr>
                            <th class="py-3 px-4 text-left">ID</th>
                            <th class="py-3 px-4 text-left">File Name</th>
                            <th class="py-3 px-4 text-left">Size</th>
                            <th class="py-3 px-4 text-left">Account</th>
                            <th class="py-3 px-4 text-left">User</th>
                            <th class="py-3 px-4 text-left">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($recentUploads)): ?>
                            <tr>
                                <td colspan="6" class="py-4 px-6 text-center text-gray-500">No recent uploads</td>
                            </tr>
                        <?php endif; ?>
                        
                        <?php foreach ($recentUploads as $upload): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-3 px-4"><?php echo $upload['id']; ?></td>
                                <td class="py-3 px-4 truncate max-w-xs">
                                    <?php echo htmlspecialchars($upload['file_name']); ?>
                                </td>
                                <td class="py-3 px-4">
                                    <?php echo formatFileSize($upload['size']); ?>
                                </td>
                                <td class="py-3 px-4 font-mono text-sm">
                                    <?php echo substr($upload['app_key'], 0, 8) . '...'; ?>
                                </td>
                                <td class="py-3 px-4">
                                    <?php echo htmlspecialchars($upload['username']); ?>
                                </td>
                                <td class="py-3 px-4 text-sm">
                                    <?php echo date('Y-m-d H:i', strtotime($upload['upload_date'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        function toggleAddAccountForm() {
            const form = document.getElementById('addAccountForm');
            form.classList.toggle('hidden');
        }
    </script>
</body>
</html>

<?php
// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>