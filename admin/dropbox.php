<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Spatie\Dropbox\Client as DropboxClient;

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    // Include database configuration
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

    // Handle CRUD operations
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'delete':
                    // Start transaction
                    $db->begin_transaction();
                    
                    try {
                        // Get account access token first
                        $stmt = $db->prepare("SELECT access_token FROM dropbox_accounts WHERE id = ?");
                        $stmt->bind_param("i", $_POST['id']);
                        $stmt->execute();
                        $account = $stmt->get_result()->fetch_assoc();
                        
                        if ($account) {
                            // Initialize Dropbox client
                            $client = new DropboxClient($account['access_token']);
                            
                            // Get all files associated with this account
                            $stmt = $db->prepare("SELECT file_id, file_name FROM file_uploads WHERE dropbox_account_id = ?");
                            $stmt->bind_param("i", $_POST['id']);
                            $stmt->execute();
                            $files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                            
                            // Delete each file from Dropbox
                            foreach ($files as $file) {
                                try {
                                    $client->delete("/{$file['file_id']}/{$file['file_name']}");
                                } catch (Exception $e) {
                                    // Log error but continue
                                    error_log("Failed to delete file from Dropbox: " . $e->getMessage());
                                }
                            }
                            
                            // Delete all files from database
                            $stmt = $db->prepare("DELETE FROM file_uploads WHERE dropbox_account_id = ?");
                            $stmt->bind_param("i", $_POST['id']);
                            $stmt->execute();
                            
                            // Delete the Dropbox account
                            $stmt = $db->prepare("DELETE FROM dropbox_accounts WHERE id = ?");
                            $stmt->bind_param("i", $_POST['id']);
                            $stmt->execute();
                        }
                        
                        $db->commit();
                        $success = "Dropbox account and all associated files removed successfully";
                        
                    } catch (Exception $e) {
                        $db->rollback();
                        $error = "Error removing account: " . $e->getMessage();
                    }
                    break;
                    
                case 'add':
                    $stmt = $db->prepare("INSERT INTO dropbox_accounts (app_key, app_secret, access_token, refresh_token) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", 
                        $_POST['app_key'],
                        $_POST['app_secret'],
                        $_POST['access_token'],
                        $_POST['refresh_token']
                    );
                    $stmt->execute();
                    $success = "Dropbox account added successfully";
                    break;

                case 'edit':
                    $stmt = $db->prepare("UPDATE dropbox_accounts SET 
                        app_key = ?, 
                        app_secret = ?, 
                        access_token = ?, 
                        refresh_token = ? 
                        WHERE id = ?");
                    $stmt->bind_param("ssssi", 
                        $_POST['app_key'],
                        $_POST['app_secret'],
                        $_POST['access_token'],
                        $_POST['refresh_token'],
                        $_POST['id']
                    );
                    $stmt->execute();
                    $success = "Dropbox account updated successfully";
                    break;

                case 'refresh_token':
                    $stmt = $db->prepare("UPDATE dropbox_accounts SET 
                        access_token = ?,
                        refresh_token = ? 
                        WHERE id = ?");
                    $stmt->bind_param("ssi", 
                        $_POST['new_access_token'],
                        $_POST['new_refresh_token'],
                        $_POST['id']
                    );
                    $stmt->execute();
                    $success = "Token refreshed successfully";
                    break;
            }
        }
    }

    // Get total count for pagination
    $total = $db->query("SELECT COUNT(*) as count FROM dropbox_accounts")->fetch_assoc()['count'];
    $totalPages = ceil($total / $limit);

    // Get dropbox accounts with pagination
    $accounts = $db->query("SELECT * FROM dropbox_accounts ORDER BY created_at DESC LIMIT $offset, $limit")->fetch_all(MYSQLI_ASSOC);

    function getDropboxStorageInfo($accessToken, $appKey, $appSecret) {
        // Get refresh token from database
        global $db;
        $stmt = $db->prepare("SELECT refresh_token FROM dropbox_accounts WHERE access_token = ? LIMIT 1");
        $stmt->bind_param("s", $accessToken);
        $stmt->execute();
        $result = $stmt->get_result();
        $account = $result->fetch_assoc();
        $refreshToken = $account['refresh_token'];

        // First, check if we need to refresh the token
        $ch = curl_init('https://api.dropboxapi.com/oauth2/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $appKey,
            'client_secret' => $appSecret
        ]));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        
        $tokenResponse = curl_exec($ch);
        
        $tokens = json_decode($tokenResponse, true);
        if (isset($tokens['access_token'])) {
            $accessToken = $tokens['access_token'];
        }
    
        // Now get storage info with the token
        $url = 'https://api.dropboxapi.com/2/users/get_space_usage';
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ];
    
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "{}");
        
        $response = curl_exec($ch);
        
        if ($response === false) {
            $error = curl_error($ch);
            error_log("Dropbox API Error: " . $error);
            return null;
        }
        
        $spaceInfo = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error: " . json_last_error_msg());
            return null;
        }
        
        return $spaceInfo;
    }
    
    // Update the foreach loop to pass all required parameters:
    foreach ($accounts as &$account) {
        $spaceInfo = getDropboxStorageInfo(
            $account['access_token'],
            $account['app_key'],
            $account['app_secret']
        );
        
        if ($spaceInfo) {
            $account['used'] = $spaceInfo['used'];
            $account['allocated'] = $spaceInfo['allocation']['allocated'];
        }
    }

    // Get total accounts for pagination
    $totalAccounts = $db->query("SELECT COUNT(*) as count FROM dropbox_accounts")->fetch_assoc()['count'];
    $totalPages = ceil($totalAccounts / $limit);

    // Get paginated accounts
    $accounts = $db->query("
        SELECT da.*, 
               COALESCE(SUM(fu.size), 0) as total_used_storage,
               COUNT(fu.file_id) as total_files
        FROM dropbox_accounts da
        LEFT JOIN file_uploads fu ON fu.dropbox_account_id = da.id 
        WHERE fu.upload_status = 'completed' OR fu.upload_status IS NULL
        GROUP BY da.id
        ORDER BY da.created_at DESC 
        LIMIT $offset, $limit
    ")->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dropbox Accounts - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50" x-data="{ sidebarOpen: false }">
    <!-- Mobile Sidebar Toggle Button -->
    <div class="lg:hidden fixed top-4 left-4 z-50">
        <button @click="sidebarOpen = !sidebarOpen" class="p-2 rounded-md text-gray-400 hover:text-white hover:bg-gray-700">
            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path :class="{'hidden': sidebarOpen, 'inline-flex': !sidebarOpen }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                <path :class="{'hidden': !sidebarOpen, 'inline-flex': sidebarOpen }" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <!-- Sidebar Overlay -->
    <div x-show="sidebarOpen" @click="sidebarOpen = false" class="fixed inset-0 z-10 bg-gray-900 opacity-50 transition-opacity lg:hidden"></div>

    <div class="flex min-h-screen">
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="lg:ml-64 flex-1 p-4 lg:p-8">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Dropbox Accounts</h1>
                <button onclick="document.getElementById('addModal').classList.remove('hidden')" 
                    class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">
                    Add New Account
                </button>
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

            <!-- Instructions to add a new Dropbox account -->
            <div class="bg-white rounded-lg shadow p-4 mb-6">
                <h2 class="text-lg font-bold mb-2">Instructions to add a new Dropbox account</h2>
                <ol class="list-decimal list-inside">
                    <li>Log in to your admin account and go to /admin/dropbox.php</li>
                    <li>Click "Add New Account" button</li>
                    <li>Enter your Dropbox App credentials:
                        <ul class="list-disc list-inside ml-4">
                            <li>App Key from your Dropbox API app</li>
                            <li>App Secret from your Dropbox API app</li>
                            <li>The system will automatically get access and refresh tokens</li>
                        </ul>
                    </li>
                    <li>Click "Connect Dropbox" to authorize the account</li>
                </ol>
            </div>

            <!-- Accounts Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">App Key</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Storage Usage</th>
                            <th class="px-6 py-3 bg-gray-50 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($accounts as $account): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php echo htmlspecialchars($account['app_key']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php echo date('M j, Y', strtotime($account['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="space-y-2">
                                    <?php 
                                    $maxStorage = 2 * 1024 * 1024 * 1024; // 2GB in bytes
                                    $usedStorage = $account['total_used_storage'] ?? 0;
                                    $percentUsed = ($usedStorage / $maxStorage) * 100;
                                    $usedGB = number_format($usedStorage / (1024 * 1024 * 1024), 2);
                                    ?>
                                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                                        <div class="bg-blue-600 h-2.5 rounded-full" 
                                             style="width: <?php echo min($percentUsed, 100); ?>%">
                                        </div>
                                    </div>
                                    <div class="text-sm text-gray-600">
                                        <?php echo "$usedGB GB used of 2 GB"; ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo number_format($account['total_files'] ?? 0); ?> files uploaded
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <button onclick="editAccount(<?php echo htmlspecialchars(json_encode($account)); ?>)"
                                    class="text-blue-600 hover:text-blue-900 mr-4">
                                    Edit
                                </button>
                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $account['id']; ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                    <div class="flex-1 flex justify-between sm:hidden">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo ($page - 1); ?>" 
                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Previous
                            </a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo ($page + 1); ?>" 
                               class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing page <span class="font-medium"><?php echo $page; ?></span> of
                                <span class="font-medium"><?php echo $totalPages; ?></span>
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <a href="?page=<?php echo $i; ?>" 
                                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?php echo $i === $page ? 'text-blue-600 border-blue-500' : 'text-gray-700 hover:bg-gray-50'; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                            </nav>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Add Account Modal -->
            <div id="addModal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center">
                <div class="bg-white rounded-lg p-8 max-w-md w-full">
                    <h2 class="text-xl font-bold mb-4">Add Dropbox Account</h2>
                    <form onsubmit="event.preventDefault(); initiateOAuth();">
                        <input type="hidden" name="action" value="add">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">App Key</label>
                                <input type="text" name="app_key" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">App Secret</label>
                                <input type="password" name="app_secret" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </div>
                            <div class="flex justify-end space-x-3">
                                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')"
                                    class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                                    Cancel
                                </button>
                                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">
                                    Connect Dropbox
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Edit Account Modal -->
            <div id="editModal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center">
                <div class="bg-white rounded-lg p-8 max-w-md w-full">
                    <h2 class="text-xl font-bold mb-4">Edit Dropbox Account</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">App Key</label>
                                <input type="text" name="app_key" id="edit_app_key" required 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">App Secret</label>
                                <input type="password" name="app_secret" id="edit_app_secret" required 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Access Token</label>
                                <input type="password" name="access_token" id="edit_access_token" required 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Refresh Token</label>
                                <input type="password" name="refresh_token" id="edit_refresh_token" required 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            </div>
                            <div class="flex justify-end space-x-3">
                                <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')"
                                    class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                                    Cancel
                                </button>
                                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">
                                    Update Account
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>
    function editAccount(account) {
        document.getElementById('edit_id').value = account.id;
        document.getElementById('edit_app_key').value = account.app_key;
        document.getElementById('edit_app_secret').value = account.app_secret;
        document.getElementById('edit_access_token').value = account.access_token;
        document.getElementById('edit_refresh_token').value = account.refresh_token;
        document.getElementById('editModal').classList.remove('hidden');
    }

    function initiateOAuth() {
        const appKey = document.querySelector('input[name="app_key"]').value;
        const appSecret = document.querySelector('input[name="app_secret"]').value;
        
        // Encode credentials in state parameter
        const state = btoa(JSON.stringify({
            app_key: appKey,
            app_secret: appSecret
        }));
        
        const redirectUri = encodeURIComponent(window.location.origin + '/callback.php');
        window.location.href = `https://www.dropbox.com/oauth2/authorize?client_id=${appKey}&response_type=code&redirect_uri=${redirectUri}&token_access_type=offline&state=${state}`;
    }
    </script>
</body>
</html>
