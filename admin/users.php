<?php
require_once __DIR__ . '/../config.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

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

    // Handle user actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $userId = $_POST['user_id'] ?? 0;

        switch ($action) {
            case 'verify':
                $stmt = $db->prepare("UPDATE users SET email_verified = 1 WHERE id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                break;
                
                case 'delete':
                // First delete related records from password_resets
                $stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                
                // Delete related records from api_keys 
                $stmt = $db->prepare("DELETE FROM api_keys WHERE user_id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                
                // Delete related records from file_reports
                $stmt = $db->prepare("DELETE FROM file_reports WHERE reported_by = ? OR resolved_by = ?");
                $stmt->bind_param("ii", $userId, $userId);
                $stmt->execute();
                
                // Finally delete the user
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                break;
                
            case 'toggle_admin':
                $stmt = $db->prepare("UPDATE users SET is_admin = NOT is_admin WHERE id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                break;
                
                case 'toggle_premium':
                    $stmt = $db->prepare("UPDATE users SET premium = NOT premium WHERE id = ?");
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    break;
        }
        
        // Redirect back with success message
        header('Location: users.php?success=1');
        exit;
    }

    // Get users list with pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $total = $db->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
    $totalPages = ceil($total / $limit);

    $users = $db->query("SELECT id, name, email, is_admin, premium, email_verified, created_at 
                        FROM users 
                        ORDER BY created_at DESC 
                        LIMIT $offset, $limit")->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
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
        <!-- Include Admin Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="lg:ml-64 flex-1 p-4 lg:p-8">
            <div class="mb-6 flex justify-between items-center">
                <h2 class="text-2xl font-bold text-gray-800">User Management</h2>
                <span class="text-sm text-gray-600">Total Users: <?php echo $total; ?></span>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    Action completed successfully
                </div>
            <?php endif; ?>

            <!-- Users Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                User
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Role
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Premium
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Joined
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($user['name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($user['email']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
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
                                    <?php echo $user['is_admin'] ? 'Admin' : 'User'; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if ($user['premium']): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Premium
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                            Standard
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-2">
                                        <?php if (!$user['email_verified']): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="verify">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="text-green-600 hover:text-green-900">
                                                    Verify
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <!-- Add Files Button -->
                                        <a href="files.php?user=<?php echo $user['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-900 inline-flex items-center mr-3">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            Files
                                        </a>
                                
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="toggle_admin">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="text-blue-600 hover:text-blue-900">
                                                <?php echo $user['is_admin'] ? 'Remove Admin' : 'Make Admin'; ?>
                                            </button>
                                        </form>
                                        
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="toggle_premium">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="text-green-600 hover:text-green-900 mr-3">
                                                <?php echo $user['premium'] ? 'Remove Premium' : 'Make Premium'; ?>
                                            </button>
                                        </form>
                                
                                        <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-900">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
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
                                <a href="?page=<?php echo $page - 1; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    Previous
                                </a>
                            <?php endif; ?>
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    Next
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Showing
                                    <span class="font-medium"><?php echo $offset + 1; ?></span>
                                    to
                                    <span class="font-medium"><?php echo min($offset + $limit, $total); ?></span>
                                    of
                                    <span class="font-medium"><?php echo $total; ?></span>
                                    results
                                </p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <a href="?page=<?php echo $i; ?>" 
                                           class="<?php echo $i === $page ? 'bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?> relative inline-flex items-center px-4 py-2 border text-sm font-medium">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                </nav>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>