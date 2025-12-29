<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Add autoloader
use Spatie\Dropbox\Client as DropboxClient; // Add namespace import

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: /login.php');
    exit;
}

$db = getDBConnection();

try {
    // Handle report actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $reportId = $_POST['report_id'] ?? '';
        
        switch ($_POST['action']) {
            case 'resolve':
                $stmt = $db->prepare("
                    UPDATE file_reports 
                    SET status = 'resolved', 
                        resolved_by = ?, 
                        resolved_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->bind_param("ii", $_SESSION['user_id'], $reportId);
                $stmt->execute();
                break;
                
            case 'delete':
                // First get the file_id
                $stmt = $db->prepare("SELECT file_id FROM file_reports WHERE id = ?");
                $stmt->bind_param("i", $reportId);
                $stmt->execute();
                $fileId = $stmt->get_result()->fetch_assoc()['file_id'];
                
                // Delete the file from Dropbox
                $dropbox = $db->query("SELECT access_token FROM dropbox_accounts LIMIT 1")->fetch_assoc();
                $client = new DropboxClient($dropbox['access_token']); // Use aliased class name
                
                // Delete file from Dropbox and database
                $stmt = $db->prepare("SELECT file_name FROM file_uploads WHERE file_id = ?");
                $stmt->bind_param("s", $fileId);
                $stmt->execute();
                $fileName = $stmt->get_result()->fetch_assoc()['file_name'];
                
                $client->delete("/$fileId/$fileName");
                
                // Delete from database
                $db->begin_transaction();
                try {
                    $stmt = $db->prepare("DELETE FROM file_uploads WHERE file_id = ?");
                    $stmt->bind_param("s", $fileId);
                    $stmt->execute();
                    
                    $stmt = $db->prepare("DELETE FROM file_reports WHERE id = ?");
                    $stmt->bind_param("i", $reportId);
                    $stmt->execute();
                    
                    $db->commit();
                } catch (Exception $e) {
                    $db->rollback();
                    throw $e;
                }
                break;
        }
        
        header('Location: reports.php?success=1');
        exit;
    }

    // Get reports with pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $reports = $db->query("
        SELECT r.*,
               f.file_name,
               u1.name as reporter_name,
               u2.name as resolver_name
        FROM file_reports r
        LEFT JOIN file_uploads f ON r.file_id = f.file_id
        LEFT JOIN users u1 ON r.reported_by = u1.id
        LEFT JOIN users u2 ON r.resolved_by = u2.id
        ORDER BY r.created_at DESC 
        LIMIT $offset, $limit
    ")->fetch_all(MYSQLI_ASSOC);

    $total = $db->query("SELECT COUNT(*) as count FROM file_reports")->fetch_assoc()['count'];
    $totalPages = ceil($total / $limit);

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Reports - Admin</title>
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
            <h1 class="text-2xl font-bold text-gray-800 mb-6">File Reports</h1>

            <?php if (isset($_GET['success'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    Action completed successfully
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                File
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Reporter
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Reason
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Reported At
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($reports as $report): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($report['file_name']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($report['reporter_name']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($report['reason']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $report['status'] === 'resolved' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                        <?php echo ucfirst($report['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M j, Y g:i A', strtotime($report['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <?php if ($report['status'] !== 'resolved'): ?>
                                        <form method="POST" class="inline-block">
                                            <input type="hidden" name="action" value="resolve">
                                            <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                            <button type="submit" class="text-blue-600 hover:text-blue-900 mr-4">
                                                Resolve
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this file?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-900">
                                            Delete File
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($totalPages > 1): ?>
                    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200">
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
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <a href="?page=<?php echo $i; ?>" 
                                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50
                                           <?php echo $i === $page ? 'bg-blue-50 border-blue-500 text-blue-600' : ''; ?>">
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