<?php
require_once __DIR__ . '/../config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Include database configuration first
$db = getDBConnection();

// Get storage stats from file_uploads and dropbox accounts
$fileStats = $db->query("
    SELECT 
        COUNT(*) as total_files,
        COALESCE(SUM(size), 0) as total_size
    FROM file_uploads 
    WHERE upload_status = 'completed'
")->fetch_assoc();

// Get all Dropbox accounts with their storage info
$dropboxAccounts = $db->query("
    SELECT 
        access_token,
        app_key,
        app_secret 
    FROM dropbox_accounts
")->fetch_all(MYSQLI_ASSOC);

$totalStorage = 0;
$usedStorage = 0;

// Function to safely get Dropbox storage info with error handling
function getDropboxStorageInfo($accessToken) {
    $ch = curl_init('https://api.dropboxapi.com/2/users/get_space_usage');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json' 
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "{}");
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Check for valid response
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['allocation']['allocated']) && isset($data['used'])) {
            return $data;
        }
    }
    
    return null;
}

// Initialize storage variables
$totalStorage = 0;
$usedStorage = 0;

// Safely calculate storage across accounts
foreach ($dropboxAccounts as $account) {
    $spaceInfo = getDropboxStorageInfo($account['access_token']);
    if ($spaceInfo && isset($spaceInfo['allocation']['allocated']) && isset($spaceInfo['used'])) {
        $totalStorage += $spaceInfo['allocation']['allocated'];
        $usedStorage += $spaceInfo['used'];
    }
}

// Only calculate percentages if we have valid storage data
$totalStorageGB = round($totalStorage / (1024 * 1024 * 1024), 2);
$usedStorageGB = round($usedStorage / (1024 * 1024 * 1024), 2); 
$percentUsed = ($totalStorage > 0) ? ($usedStorage / $totalStorage * 100) : 0;

try {
    // Get user info including admin status
    $stmt = $db->prepare("SELECT name, email, created_at, email_verified, is_admin FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    // Verify admin status
    if (!$user['is_admin']) {
        header('Location: ../dashboard.php');
        exit;
    }

    // Admin stats
    $totalUsers = $db->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
    $verifiedUsers = $db->query("SELECT COUNT(*) as count FROM users WHERE email_verified = 1")->fetch_assoc()['count'];
    
    // Get recent users
    $recentUsers = $db->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

    // Fix the storage calculation
    $fileStats = $db->query("SELECT 
        COUNT(*) as total_files,
        COALESCE(SUM(size), 0) as total_size
        FROM file_uploads 
        WHERE upload_status = 'completed'")->fetch_assoc();

    $dropboxAccounts = $db->query("SELECT COUNT(*) as count FROM dropbox_accounts")->fetch_assoc()['count'];
    $totalStorage = $dropboxAccounts * 2; // 2GB per account
    $usedStorageGB = round(($fileStats['total_size'] / 1024 / 1024 / 1024), 2);
    $percentUsed = ($usedStorageGB / $totalStorage) * 100;

} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Add this before the HTML output
$currentPath = isset($_GET['path']) ? trim($_GET['path'], '/') : '';
$currentFullPath = $currentPath ? "/$currentPath/" : '/';

// Get files for current path
$files = $db->query("
    SELECT 
        fu.*, 
        u.name as uploader_name,
        COALESCE(COUNT(fr.id), 0) as report_count
    FROM file_uploads fu
    LEFT JOIN users u ON fu.uploaded_by = u.id 
    LEFT JOIN file_reports fr ON fu.file_id = fr.file_id
    WHERE fu.dropbox_path LIKE '$currentFullPath%'
    AND fu.upload_status = 'completed'
    GROUP BY fu.file_id
    ORDER BY fu.is_folder DESC, fu.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Process files into folder structure
$items = [];
foreach ($files as $file) {
    $relativePath = trim(str_replace($currentFullPath, '', $file['dropbox_path']), '/');
    $parts = explode('/', $relativePath);
    
    if (count($parts) == 1 || (empty($parts[0]) && count($parts) == 1)) {
        $items[] = $file;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="icon" type="image/png" href="../icon.png">
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
    <div 
        x-show="sidebarOpen" 
        @click="sidebarOpen = false" 
        class="fixed inset-0 z-10 bg-gray-900 opacity-50 transition-opacity lg:hidden">
    </div>

    <!-- Left Sidebar Navigation -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64 flex-1 p-4 lg:p-8">
        <!-- Welcome Section -->
        <div class="bg-white rounded-lg shadow-sm p-4 lg:p-6 mb-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                <div class="mb-4 lg:mb-0">
                    <h1 class="text-xl lg:text-2xl font-bold text-gray-800">
                        Welcome back, <?php echo htmlspecialchars($user['name']); ?>!
                    </h1>
                    <p class="text-gray-600 mt-1">Here's what's happening with your account today.</p>
                </div>
                <?php if (!$user['email_verified']): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800">Verify your email</h3>
                            <p class="text-sm text-yellow-700 mt-1">Please check your email to verify your account.</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-50">
                        <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-700">Account Status</h3>
                        <p class="text-gray-500 text-sm mt-1">
                            <?php echo $user['email_verified'] ? 'Verified' : 'Pending Verification'; ?>
                        </p>
                    </div>
                </div>
            </div>

            <?php if ($user['is_admin']): ?>
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-50">
                        <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-700">Total Users</h3>
                        <p class="text-gray-500 text-sm mt-1"><?php echo $totalUsers; ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-50">
                        <svg class="w-6 h-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-700">Verified Users</h3>
                        <p class="text-gray-500 text-sm mt-1"><?php echo $verifiedUsers; ?></p>
                    </div>
                </div>
            </div>

            <!-- Total Files Card -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-50">
                        <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-700">Total Files</h3>
                        <p class="text-gray-500 text-sm mt-1"><?php echo number_format($fileStats['total_files']); ?></p>
                    </div>
                </div>
            </div>

            <!-- Storage Quota Card -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-50">
                        <svg class="w-6 h-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-700">Storage Quota</h3>
                        <div class="space-y-2">
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div class="bg-purple-600 h-2.5 rounded-full" 
                                     style="width: <?php echo min($percentUsed, 100); ?>%">
                                </div>
                            </div>
                            <p class="text-gray-500 text-sm mt-1">
                                <?php echo $usedStorageGB; ?> GB used of <?php echo $totalStorage; ?> GB
                                <span class="text-purple-600 font-medium">
                                    (<?php echo number_format($percentUsed, 1); ?>%)
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Storage Quota Card -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-50">
                        <svg class="w-6 h-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-700">Storage Quota</h3>
                        <div class="space-y-2">
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div class="bg-purple-600 h-2.5 rounded-full" style="width: <?php echo min($percentUsed, 100); ?>%"></div>
                            </div>
                            <p class="text-gray-500 text-sm">
                                <?php echo $totalStorage; ?> GB Total
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($user['is_admin']): ?>
        <!-- Admin Section -->
        <div class="bg-white rounded-lg shadow-sm p-4 lg:p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Recent Users</h2>
            <div class="overflow-x-auto -mx-4 lg:mx-0">
                <div class="inline-block min-w-full align-middle">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($recentUsers as $user): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($user['name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($user['email_verified']): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Verified
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            Pending
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Replace the files table with this new file manager UI -->
        <div class="bg-white rounded-lg shadow-sm p-4 lg:p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-800">File Manager</h2>
                <nav class="flex" aria-label="Breadcrumb">
                    <ol class="flex items-center space-x-2">
                        <li>
                            <a href="?path=" class="text-gray-500 hover:text-gray-700">Root</a>
                        </li>
                        <?php if ($currentPath): ?>
                            <?php 
                            $parts = explode('/', $currentPath);
                            $buildPath = '';
                            foreach ($parts as $part): 
                                $buildPath .= '/' . $part;
                            ?>
                            <li class="flex items-center">
                                <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                                <a href="?path=<?= trim($buildPath, '/') ?>" class="ml-2 text-gray-500 hover:text-gray-700">
                                    <?= htmlspecialchars($part) ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ol>
                </nav>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                <?php foreach ($items as $item): ?>
                    <a href="<?= $item['is_folder'] ? '?path=' . trim($currentPath . '/' . $item['file_name'], '/') : '../download.php?id=' . $item['file_id'] ?>" 
                       class="group relative flex flex-col items-center p-4 rounded-lg border border-gray-200 hover:border-blue-500 hover:shadow-sm">
                        <?php if ($item['is_folder']): ?>
                            <svg class="w-12 h-12 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                            </svg>
                        <?php else: ?>
                            <svg class="w-12 h-12 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                        <?php endif; ?>
                        <span class="mt-2 text-sm text-center text-gray-600 group-hover:text-gray-900 truncate max-w-full px-2">
                            <?= htmlspecialchars($item['file_name']) ?>
                        </span>
                        <?php if ($item['report_count'] > 0): ?>
                            <span class="absolute top-2 right-2 bg-red-100 text-red-600 text-xs px-2 py-1 rounded-full">
                                <?= $item['report_count'] ?>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Handle mobile navigation
        document.addEventListener('alpine:init', () => {
            Alpine.data('navigation', () => ({
                sidebarOpen: false,
                toggleSidebar() {
                    this.sidebarOpen = !this.sidebarOpen;
                },
                closeSidebarOnMobile() {
                    if (window.innerWidth < 1024) {
                        this.sidebarOpen = false;
                    }
                }
            }))
        })
    </script>
</body>
</html>