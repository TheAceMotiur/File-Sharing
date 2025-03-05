<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';
session_start();

use Spatie\Dropbox\Client as DropboxClient;

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

    // Get files with pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    // Get total count for pagination
    $total = $db->query("SELECT COUNT(*) as count FROM file_uploads")->fetch_assoc()['count'];
    $totalPages = ceil($total / $limit);

    // Get files with user info
    $userId = isset($_GET['user']) ? (int)$_GET['user'] : null;

    // Modify the query to filter by user if specified
    if ($userId) {
        $query = "SELECT f.*, u.name as uploader_name, u.email as uploader_email
                  FROM file_uploads f
                  LEFT JOIN users u ON f.uploaded_by = u.id
                  WHERE f.uploaded_by = ?
                  ORDER BY f.created_at DESC 
                  LIMIT ?, ?";
        $stmt = $db->prepare($query);
        $stmt->bind_param("iii", $userId, $offset, $limit);
        $stmt->execute();
        $files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Get total count for this user
        $countStmt = $db->prepare("SELECT COUNT(*) as count FROM file_uploads WHERE uploaded_by = ?");
        $countStmt->bind_param("i", $userId);
        $countStmt->execute();
        $total = $countStmt->get_result()->fetch_assoc()['count'];
    } else {
        // Existing query for all files
        $files = $db->query("
            SELECT f.*, u.name as uploader_name, u.email as uploader_email
            FROM file_uploads f
            LEFT JOIN users u ON f.uploaded_by = u.id
            ORDER BY f.created_at DESC 
            LIMIT $offset, $limit
        ")->fetch_all(MYSQLI_ASSOC);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'delete') {
            // Single file delete
            $fileId = $_POST['file_id'];
            $stmt = $db->prepare("SELECT * FROM file_uploads WHERE file_id = ?");
            $stmt->bind_param("s", $fileId);
            $stmt->execute();
            $file = $stmt->get_result()->fetch_assoc();

            if ($file) {
                // Get Dropbox account
                $dropbox = $db->query("SELECT access_token FROM dropbox_accounts LIMIT 1")->fetch_assoc();
                $client = new DropboxClient($dropbox['access_token']);

                try {
                    // Delete from Dropbox
                    $dropboxPath = "/{$fileId}";
                    try {
                        $client->delete($dropboxPath);
                    } catch (Exception $e) {
                        // Log the error but continue with database deletion
                        error_log("Dropbox deletion failed for file {$fileId}: " . $e->getMessage());
                    }

                    // Delete from database regardless of Dropbox status
                    $stmt = $db->prepare("DELETE FROM file_uploads WHERE file_id = ?");
                    $stmt->bind_param("s", $fileId);
                    if ($stmt->execute()) {
                        header('Location: files.php?success=1');
                        exit;
                    } else {
                        throw new Exception("Database deletion failed");
                    }
                } catch (Exception $e) {
                    $error = "Failed to delete file: " . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] === 'bulk_delete' && isset($_POST['file_ids'])) {
            $fileIds = is_array($_POST['file_ids']) ? $_POST['file_ids'] : explode(',', $_POST['file_ids']);
            $dropbox = $db->query("SELECT access_token FROM dropbox_accounts LIMIT 1")->fetch_assoc();
            $client = new DropboxClient($dropbox['access_token']);
            $errors = [];
            $successCount = 0;

            foreach ($fileIds as $fileId) {
                try {
                    // Delete from Dropbox
                    $dropboxPath = "/{$fileId}";
                    try {
                        $client->delete($dropboxPath);
                    } catch (Exception $e) {
                        // Log the error but continue with database deletion
                        error_log("Dropbox deletion failed for file {$fileId}: " . $e->getMessage());
                    }

                    // Delete from database regardless of Dropbox status
                    $stmt = $db->prepare("DELETE FROM file_uploads WHERE file_id = ?");
                    $stmt->bind_param("s", $fileId);
                    if ($stmt->execute() && $stmt->affected_rows > 0) {
                        $successCount++;
                    } else {
                        $errors[] = "Failed to delete file {$fileId} from database";
                    }
                } catch (Exception $e) {
                    $errors[] = "Failed to process file {$fileId}: " . $e->getMessage();
                }
            }

            if (empty($errors)) {
                header('Location: files.php?success=1&deleted=' . $successCount);
                exit;
            } elseif ($successCount > 0) {
                header('Location: files.php?partial=1&success=' . $successCount . '&errors=' . count($errors));
                exit;
            }
        }
    }

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Files Management - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
        }
        .modal-content {
            margin: auto;
            display: block;
            width: auto;
            height: auto;
            max-width: 95%;
            max-height: 95vh;
        }
        .preview-thumbnail {
            height: 3rem;
            width: 3rem;
            object-fit: cover;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .preview-thumbnail:hover {
            transform: scale(1.1);
        }
        .modal-close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }
        .modal-close:hover {
            color: #bbb;
        }
        .file-icon {
            font-size: 1.5rem;
            width: 2rem;
            text-align: center;
            margin-right: 0.75rem;
        }
        .file-icon-wrapper {
            display: flex;
            align-items: center;
        }
    </style>
</head>
<body class="bg-gray-50" x-data="{ sidebarOpen: false }">
    <!-- Image Preview Modal -->
    <div id="imagePreviewModal" class="modal" onclick="closeImagePreview(event)">
        <span class="modal-close" onclick="closeImagePreview(event)">&times;</span>
        <img class="modal-content" id="previewImage">
    </div>

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
            <div class="mb-6 flex justify-between items-center">
                <h1 class="text-2xl font-bold text-gray-800">
                    <?php if ($userId): ?>
                        <?php
                        $userStmt = $db->prepare("SELECT name FROM users WHERE id = ?");
                        $userStmt->bind_param("i", $userId);
                        $userStmt->execute();
                        $userName = $userStmt->get_result()->fetch_assoc()['name'];
                        echo htmlspecialchars($userName) . "'s Files";
                        ?>
                        <a href="files.php" class="text-sm font-normal text-blue-600 hover:text-blue-800 ml-4">
                            View All Files
                        </a>
                    <?php else: ?>
                        Files Management
                    <?php endif; ?>
                </h1>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 bg-gray-50">
                                <input type="checkbox" id="selectAll" class="rounded border-gray-300">
                            </th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">File Name</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Uploader</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                            <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase">Uploaded</th>
                            <th class="px-6 py-3 bg-gray-50 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($files as $file): ?>
                        <tr>
                            <td class="px-6 py-4">
                                <input type="checkbox" name="file_select" value="<?php echo $file['file_id']; ?>" 
                                       class="file-checkbox rounded border-gray-300">
                            </td>
                            <td class="px-6 py-4">
                                <?php
                                $fileExtension = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
                                $isImage = in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                
                                // Determine icon and color based on file extension
                                [$icon, $color] = match($fileExtension) {
                                    'pdf' => ['fa-file-pdf', 'text-red-500'],
                                    'doc', 'docx' => ['fa-file-word', 'text-blue-600'],
                                    'xls', 'xlsx' => ['fa-file-excel', 'text-green-600'],
                                    'ppt', 'pptx' => ['fa-file-powerpoint', 'text-orange-600'],
                                    'zip', 'rar', '7z' => ['fa-file-zipper', 'text-yellow-600'],
                                    'txt' => ['fa-file-lines', 'text-gray-600'],
                                    'mp3', 'wav', 'ogg' => ['fa-file-audio', 'text-purple-600'],
                                    'mp4', 'avi', 'mov' => ['fa-file-video', 'text-pink-600'],
                                    'jpg', 'jpeg', 'png', 'gif', 'webp' => ['fa-file-image', 'text-indigo-600'],
                                    default => ['fa-file', 'text-gray-400']
                                };
                                ?>
                                <div class="file-icon-wrapper">
                                    <i class="fa-regular <?php echo $icon; ?> file-icon <?php echo $color; ?>"></i>
                                    <span><?php echo htmlspecialchars($file['file_name']); ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div>
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($file['uploader_name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars($file['uploader_email']); ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo number_format($file['size'] / 1024 / 1024, 2); ?> MB
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('M j, Y', strtotime($file['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <?php if ($isImage): ?>
                                    <button onclick="showImagePreview('../download/<?php echo $file['file_id']; ?>/<?php echo urlencode($file['file_name']); ?>')" 
                                            class="text-indigo-600 hover:text-indigo-900 mr-3">
                                        <i class="fa-regular fa-eye mr-1"></i>
                                        Preview
                                    </button>
                                <?php endif; ?>
                                <a href="../download/<?php echo $file['file_id']; ?>/<?php echo urlencode($file['file_name']); ?>" 
                                   class="text-blue-600 hover:text-blue-900 mr-3">
                                   <i class="fa-solid fa-download mr-1"></i>
                                   Download
                                </a>
                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this file?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="file_id" value="<?php echo $file['file_id']; ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-900">
                                        <i class="fa-regular fa-trash-can mr-1"></i>
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Add bulk actions -->
                <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
                    <button id="bulkDeleteBtn" 
                            class="hidden bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 disabled:opacity-50"
                            disabled>
                        Delete Selected Files
                    </button>
                </div>

                <?php if ($totalPages > 1): ?>
                <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200">
                    <div class="flex-1 flex justify-between items-center">
                        <div>
                            <p class="text-sm text-gray-700">
                                Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to 
                                <span class="font-medium"><?php echo min($offset + $limit, $total); ?></span> of
                                <span class="font-medium"><?php echo $total; ?></span> results
                            </p>
                        </div>
                        <div>
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <a href="?page=<?php echo $i . ($userId ? "&user=$userId" : ''); ?>"
                                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium 
                                              <?php echo $i === $page ? 'text-blue-600 border-blue-500' : 'text-gray-700 hover:bg-gray-50'; ?>">
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

    <!-- Add bulk delete form -->
    <form id="bulkDeleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="bulk_delete">
        <div id="fileIdsContainer"></div>
    </form>

    <script>
        function showImagePreview(url) {
            const modal = document.getElementById('imagePreviewModal');
            const modalImg = document.getElementById('previewImage');
            modal.style.display = "flex";
            modal.style.alignItems = "center";
            modal.style.justifyContent = "center";
            modalImg.src = url;
        }

        function closeImagePreview(event) {
            // Only close if clicking the background or close button
            if (event.target.id === 'imagePreviewModal' || event.target.className === 'modal-close') {
                document.getElementById('imagePreviewModal').style.display = "none";
            }
        }

        // Add bulk selection handling
        document.getElementById('selectAll').addEventListener('change', function() {
            document.querySelectorAll('.file-checkbox').forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkDeleteButton();
        });

        document.querySelectorAll('.file-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkDeleteButton);
        });

        document.getElementById('bulkDeleteBtn').addEventListener('click', function() {
            if (confirm('Are you sure you want to delete all selected files?')) {
                const selectedFiles = Array.from(document.querySelectorAll('.file-checkbox:checked'))
                                         .map(cb => cb.value);
                
                const form = document.getElementById('bulkDeleteForm');
                const container = document.getElementById('fileIdsContainer');
                container.innerHTML = ''; // Clear previous inputs
                
                // Create hidden input for each selected file
                selectedFiles.forEach(fileId => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'file_ids[]';
                    input.value = fileId;
                    container.appendChild(input);
                });
                
                form.submit();
            }
        });

        function updateBulkDeleteButton() {
            const selectedCount = document.querySelectorAll('.file-checkbox:checked').length;
            const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
            
            if (selectedCount > 0) {
                bulkDeleteBtn.classList.remove('hidden');
                bulkDeleteBtn.disabled = false;
                bulkDeleteBtn.textContent = `Delete Selected Files (${selectedCount})`;
            } else {
                bulkDeleteBtn.classList.add('hidden');
                bulkDeleteBtn.disabled = true;
            }
        }
    </script>
</body>
</html>