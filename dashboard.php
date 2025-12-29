<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/ads.php'; // Include ads functionality
session_start();
require_once __DIR__ . '/includes/auth.php';

// Check if user is logged in and verified
checkEmailVerification();

try {
    $db = getDBConnection();
    
    // Get user info
    $stmt = $db->prepare("SELECT name, email, created_at, email_verified, premium FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    // Update premium status in session to ensure it's current (use both variables)
    $_SESSION['premium'] = $user['premium'];
    $_SESSION['user_premium'] = $user['premium'];
 
    // Get file statistics
    $stmt = $db->prepare("SELECT 
        COUNT(*) as total_files,
        COALESCE(SUM(size), 0) as total_size,
        COALESCE(SUM(CASE WHEN upload_status = 'completed' THEN 1 ELSE 0 END), 0) as completed_files
        FROM file_uploads 
        WHERE uploaded_by = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();

    // Initialize variables to avoid undefined variable warnings
    $totalFiles = 0;
    $totalPages = 0;

    // Get files with pagination, only completed uploads
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    // First, get the total number of files for pagination
    $totalCountStmt = $db->prepare("SELECT COUNT(*) as total 
        FROM file_uploads 
        WHERE uploaded_by = ? 
        AND upload_status = 'completed'");
    $totalCountStmt->bind_param("i", $_SESSION['user_id']);
    $totalCountStmt->execute();
    $totalFiles = $totalCountStmt->get_result()->fetch_assoc()['total'];
    $totalPages = ceil($totalFiles / $limit);

    // Ensure current page is within valid range
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $limit;

    // Get files for current page
    $stmt = $db->prepare("SELECT *, 
        CASE 
            WHEN LOWER(file_name) LIKE '%.jpg' OR 
                 LOWER(file_name) LIKE '%.jpeg' OR 
                 LOWER(file_name) LIKE '%.png' OR 
                 LOWER(file_name) LIKE '%.gif' OR 
                 LOWER(file_name) LIKE '%.webp' 
            THEN 1 
            ELSE 0 
        END as is_image
        FROM file_uploads 
        WHERE uploaded_by = ? 
        AND upload_status = 'completed' 
        ORDER BY created_at DESC 
        LIMIT ?, ?");
    $stmt->bind_param("iii", $_SESSION['user_id'], $offset, $limit);
    $stmt->execute();
    $files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Handle file deletion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
        $fileId = $_POST['file_id'];
        
        // Get Dropbox credentials
        $dropbox = $db->query("SELECT * FROM dropbox_accounts LIMIT 1")->fetch_assoc();
        
        // Delete from Dropbox
        $ch = curl_init('https://api.dropboxapi.com/2/files/delete_v2');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $dropbox['access_token'],
            'Content-Type: application/json'
        ));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            "path" => "/" . $fileId
        ]));
        curl_exec($ch);
        curl_close($ch);
        
        // Delete from database
        $stmt = $db->prepare("DELETE FROM file_uploads WHERE file_id = ? AND uploaded_by = ?");
        $stmt->bind_param("si", $fileId, $_SESSION['user_id']);
        $stmt->execute();
        
        $_SESSION['success_message'] = "File deleted successfully!";
        header('Location: dashboard');
        exit;
    }

    // Replace the rename POST handler with this optimized version:
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rename') {
        $fileId = $_POST['file_id'];
        $newName = $_POST['new_name'];
        
        try {
            // Get the original file record
            $stmt = $db->prepare("SELECT * FROM file_uploads WHERE file_id = ? AND uploaded_by = ?");
            $stmt->bind_param("si", $fileId, $_SESSION['user_id']);
            $stmt->execute();
            $file = $stmt->get_result()->fetch_assoc();
            
            if (!$file) {
                throw new Exception('File not found');
            }

            // Get original extension and ensure it's preserved
            $originalExt = pathinfo($file['file_name'], PATHINFO_EXTENSION);
            
            // Remove any extension from new name if present
            $newNameWithoutExt = pathinfo($newName, PATHINFO_FILENAME);
            
            // Combine new name with original extension
            $finalName = $newNameWithoutExt . '.' . $originalExt;

            // Set up paths
            $oldPath = '/' . $fileId . '/' . $file['file_name'];
            $newPath = '/' . $fileId . '/' . $finalName;

            // Get Dropbox credentials
            $dropbox = $db->query("SELECT * FROM dropbox_accounts LIMIT 1")->fetch_assoc();

            // Use curl to rename file in Dropbox
            $ch = curl_init('https://api.dropboxapi.com/2/files/move_v2');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Authorization: Bearer ' . $dropbox['access_token'],
                'Content-Type: application/json'
            ));
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                "from_path" => $oldPath,
                "to_path" => $newPath
            ]));
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new Exception('Failed to rename file in Dropbox');
            }

            // Update database
            $stmt = $db->prepare("UPDATE file_uploads SET file_name = ?, dropbox_path = ? WHERE file_id = ? AND uploaded_by = ?");
            $stmt->bind_param("sssi", $finalName, $newPath, $fileId, $_SESSION['user_id']);
            $stmt->execute();
            
            $_SESSION['success_message'] = "File renamed successfully!";
            header('Location: dashboard');
            exit;
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error renaming file: " . $e->getMessage();
            header('Location: dashboard');
            exit;
        }
    }

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Add this helper function before the HTML output
function getFileIcon($fileName) {
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // Define icon mappings with larger icons and better visual hierarchy
    $icons = [
        // Images
        'jpg' => [
            'color' => 'from-pink-500 to-rose-500',
            'icon' => '<svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>'
        ],
        'jpeg' => [
            'color' => 'from-pink-500 to-rose-500',
            'icon' => '<svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>'
        ],
        'png' => [
            'color' => 'from-pink-500 to-rose-500',
            'icon' => '<svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>'
        ],
        
        // Documents
        'pdf' => [
            'color' => 'from-red-500 to-red-600',
            'icon' => '<svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>'
        ],
        'doc' => [
            'color' => 'from-blue-500 to-blue-600',
            'icon' => '<svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>'
        ],
        'docx' => [
            'color' => 'from-blue-500 to-blue-600',
            'icon' => '<svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>'
        ],
        
        // Archives
        'zip' => [
            'color' => 'from-yellow-500 to-yellow-600',
            'icon' => '<svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>'
        ],
        'rar' => [
            'color' => 'from-yellow-500 to-yellow-600',
            'icon' => '<svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>'
        ],
        
        // Code
        'html' => [
            'color' => 'from-orange-500 to-orange-600',
            'icon' => '<svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>'
        ],
        'css' => [
            'color' => 'from-blue-400 to-blue-500',
            'icon' => '<svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>'
        ],
        
        // Default
        'default' => [
            'color' => 'from-gray-400 to-gray-500',
            'icon' => '<svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>'
        ]
    ];
    
    $iconData = isset($icons[$extension]) ? $icons[$extension] : $icons['default'];
    return [
        'html' => '<div class="flex flex-col items-center justify-center">
                    <div class="w-12 h-12 flex items-center justify-center rounded-xl bg-gradient-to-br ' . $iconData['color'] . ' text-white">
                        ' . $iconData['icon'] . '
                    </div>
                    <span class="mt-1 text-xs font-medium text-gray-500 uppercase">' . $extension . '</span>
                </div>'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - FreeNetly</title>
    <link rel="icon" type="image/png" href="icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
    .rounded-xl {
        border-radius: 1rem;
    }
    
    .shadow-sm {
        box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05);
    }
    
    .hover\:shadow-md:hover {
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
    }
    
    .active\:scale-95:active {
        transform: scale(0.95);
    }
    
    .w-4\.5 {
        width: 1.125rem;
    }
    
    .h-4\.5 {
        height: 1.125rem;
    }
    </style>

</head>
<body class="bg-gray-50">
    <?php include 'header.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <!-- Success Message -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div id="successAlert" class="fixed top-4 right-4 flex items-center p-4 mb-4 text-green-800 rounded-lg bg-green-50 shadow-lg z-50">
                <svg class="flex-shrink-0 w-4 h-4" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 8.207-4 4a1 1 0 0 1-1.414 0l-2-2a1 1 0 0 1 1.414-1.414L9 10.586l3.293-3.293a1 1 0 0 1 1.414 1.414Z"/>
                </svg>
                <span class="ml-2 text-sm font-medium"><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
                <button type="button" class="ml-auto -mx-1.5 -my-1.5 bg-green-50 text-green-500 rounded-lg focus:ring-2 focus:ring-green-400 p-1.5 hover:bg-green-200 inline-flex items-center justify-center h-8 w-8" onclick="closeSuccessMessage()">
                    <span class="sr-only">Close</span>
                    <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
                    </svg>
                </button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (!isset($_SESSION['premium']) || !$_SESSION['premium']): ?>
            <?php displayInArticleAd(); // In-article ad shown only to non-premium users ?>
        <?php endif; ?>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Welcome Section -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">
                            Welcome back, <?php echo htmlspecialchars($user['name']); ?>!
                        </h1>
                        <p class="text-gray-600 mt-1">Here's an overview of your files and storage.</p>
                    </div>
                    <a href="/" 
                    class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 transition-colors">
                        Upload
                    </a>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <!-- Total Files Card -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-50">
                            <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-800">Total Files</h3>
                            <p class="text-gray-500"><?php echo number_format($stats['total_files']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Storage Used Card -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-50">
                            <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                    d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-800">Storage Used</h3>
                            <div class="flex items-center gap-2">
                                <p class="text-gray-500"><?php echo number_format($stats['total_size'] / 1024 / 1024, 2); ?> MB</p>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                    Unlimited
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!isset($_SESSION['premium']) || !$_SESSION['premium']): ?>
                <?php displayHomepageFeaturedAd(); // Featured ad before the file listing ?>
            <?php endif; ?>

            <!-- File Manager -->
            <div class="bg-white rounded-lg shadow-sm">
                <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-800">Your Files</h2>
                    <div class="flex gap-2">
                        <button onclick="createNewFolder()" 
                                class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-blue-500 hover:bg-blue-600">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                    d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            New Folder
                        </button>
                        <a href="/" 
                           class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-green-500 hover:bg-green-600">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0l-4 4m4-4v12"/>
                            </svg>
                            Upload Files
                        </a>
                    </div>
                </div>

                <!-- Grid View -->
                <div class="p-6 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    <?php foreach ($files as $file): ?>
                    <div class="group relative flex flex-col items-center p-4 rounded-lg border border-gray-200 hover:border-blue-500 hover:shadow-md transition-all duration-200">
                        <!-- File Icon -->
                        <div class="mb-3">
                            <?php $iconData = getFileIcon($file['file_name']); echo $iconData['html']; ?>
                        </div>
                        
                        <!-- File Name -->
                        <p class="text-sm text-center font-medium text-gray-700 truncate w-full" title="<?php echo htmlspecialchars($file['file_name']); ?>">
                            <?php echo htmlspecialchars($file['file_name']); ?>
                        </p>
                        
                        <!-- File Size -->
                        <p class="text-xs text-gray-500 mt-1">
                            <?php echo number_format($file['size'] / 1024 / 1024, 2); ?> MB
                        </p>
                        
                        <!-- Quick Actions -->
                        <div class="absolute top-2 right-2 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <?php if ($file['is_image']): ?>
                            <button onclick="previewImage('<?php echo htmlspecialchars($file['file_name']); ?>', '/download/<?php echo $file['file_id']; ?>/download')"
                                    class="p-1 rounded-full bg-purple-500 text-white hover:bg-purple-600"
                                    title="Preview">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                            <?php endif; ?>
                            
                            <button onclick="showRenameModal('<?php echo $file['file_id']; ?>', '<?php echo htmlspecialchars($file['file_name']); ?>')"
                                    class="p-1 rounded-full bg-blue-500 text-white hover:bg-blue-600"
                                    title="Rename">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                </svg>
                            </button>
                            
                            <form method="POST" class="inline-block deleteForm" data-file-name="<?php echo htmlspecialchars($file['file_name']); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="file_id" value="<?php echo $file['file_id']; ?>">
                                <button type="submit" 
                                        class="p-1 rounded-full bg-red-500 text-white hover:bg-red-600"
                                        title="Delete">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($files)): ?>
                    <div class="col-span-full text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No files</h3>
                        <p class="mt-1 text-sm text-gray-500">Get started by uploading a file</p>
                        <div class="mt-6">
                            <a href="/" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-blue-500 hover:bg-blue-600">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0l-4 4m4-4v12"/>
                                </svg>
                                Upload Files
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="px-6 py-4 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <!-- Results info -->
                        <p class="text-sm text-gray-700">
                            Showing <span class="font-medium"><?php echo number_format($offset + 1); ?></span> to 
                            <span class="font-medium"><?php echo number_format(min($offset + $limit, $totalFiles)); ?></span> of 
                            <span class="font-medium"><?php echo number_format($totalFiles); ?></span> files
                        </p>

                        <!-- Page numbers -->
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Previous</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?php echo $i === $page ? 'text-blue-600 bg-blue-50 border-blue-500' : 'text-gray-700 hover:bg-gray-50'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Next</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Pagination Controls -->
            <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6">
                <!-- Mobile Pagination -->
                <div class="flex flex-1 justify-between sm:hidden">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" 
                        class="relative inline-flex items-center rounded-md px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 focus:z-10 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            Previous
                        </a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" 
                        class="relative ml-3 inline-flex items-center rounded-md px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 focus:z-10 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            Next
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Desktop Pagination -->
                <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                    <!-- Results Counter -->
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing <span class="font-medium"><?php echo number_format($offset + 1); ?></span> to 
                            <span class="font-medium"><?php echo number_format(min($offset + $limit, $totalFiles)); ?></span> of 
                            <span class="font-medium"><?php echo number_format($totalFiles); ?></span> results
                        </p>
                    </div>

                    <!-- Page Numbers -->
                    <div>
                        <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                            <!-- Previous Button -->
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>" 
                                class="relative inline-flex items-center rounded-l-md px-2.5 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 transition-colors duration-150">
                                    <span class="sr-only">Previous</span>
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php endif; ?>

                            <!-- Page Numbers -->
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>" 
                                class="relative inline-flex items-center px-4 py-2 text-sm font-semibold 
                                        <?php echo $i === $page 
                                            ? 'z-10 bg-blue-600 text-white focus:z-20 focus:outline-offset-0' 
                                            : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0'; ?> 
                                        transition-colors duration-150">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>

                            <!-- Next Button -->
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>" 
                                class="relative inline-flex items-center rounded-r-md px-2.5 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 transition-colors duration-150">
                                    <span class="sr-only">Next</span>
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div id="deleteModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3 text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                        <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mt-5">Delete File</h3>
                    <div class="mt-2 px-7 py-3">
                        <p class="text-sm text-gray-500" id="deleteModalText"></p>
                    </div>
                    <div class="items-center px-4 py-3">
                        <button id="confirmDelete" class="px-4 py-2 bg-red-600 text-white text-base font-medium rounded-md w-32 shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                            Delete
                        </button>
                        <button id="cancelDelete" class="mt-3 ml-3 px-4 py-2 bg-white text-gray-700 text-base font-medium rounded-md w-32 border border-gray-300 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Image Preview Modal -->
        <div id="imagePreviewModal" class="hidden fixed inset-0 bg-black bg-opacity-75 z-[100] flex items-center justify-center p-4">
            <div class="relative max-w-4xl w-full bg-white rounded-lg shadow-xl">
                <!-- Header -->
                <div class="flex items-center justify-between p-4 border-b">
                    <h3 class="text-xl font-semibold text-gray-900" id="previewFileName"></h3>
                    <button type="button" onclick="closeImagePreview()" class="text-gray-400 hover:text-gray-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <!-- Image Container -->
                <div class="p-4 flex items-center justify-center" style="min-height: 200px;">
                    <img id="previewImage" src="" alt="" class="max-w-full h-auto mx-auto" style="max-height: 70vh;">
                    <div id="imageLoader" class="hidden">
                        <svg class="animate-spin h-8 w-8 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rename Modal -->
        <div id="renameModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Rename File</h3>
                    <form id="renameForm" method="POST">
                        <input type="hidden" name="action" value="rename">
                        <input type="hidden" name="file_id" id="renameFileId">
                        <div class="mb-4">
                            <label for="new_name" class="block text-sm font-medium text-gray-700">New Name</label>
                            <input type="text" name="new_name" id="new_name" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                        focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div class="flex items-center justify-end space-x-3">
                            <button type="button" onclick="closeRenameModal()"
                                    class="px-4 py-2 bg-white text-gray-700 text-base font-medium rounded-md
                                        border border-gray-300 shadow-sm hover:bg-gray-50
                                        focus:outline-none focus:ring-2 focus:ring-blue-500">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="px-4 py-2 bg-blue-600 text-white text-base font-medium rounded-md
                                        shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2
                                        focus:ring-blue-500">
                                Rename
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Create Folder Modal -->
        <div id="createFolderModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Create New Folder</h3>
                    <form id="createFolderForm" method="POST">
                        <input type="hidden" name="action" value="create_folder">
                        <div class="mb-4">
                            <label for="folder_name" class="block text-sm font-medium text-gray-700">Folder Name</label>
                            <input type="text" name="folder_name" id="folder_name" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm 
                                        focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div class="flex items-center justify-end space-x-3">
                            <button type="button" onclick="closeCreateFolderModal()"
                                    class="px-4 py-2 bg-white text-gray-700 text-base font-medium rounded-md
                                        border border-gray-300 shadow-sm hover:bg-gray-50
                                        focus:outline-none focus:ring-2 focus:ring-blue-500">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="px-4 py-2 bg-blue-600 text-white text-base font-medium rounded-md
                                        shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2
                                        focus:ring-blue-500">
                                Create
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        $(document).ready(function() {
            let currentForm = null;
            
            // Show delete confirmation modal
            $('.deleteForm').on('submit', function(e) {
                e.preventDefault();
                currentForm = this;
                const fileName = $(this).data('file-name');
                $('#deleteModalText').text(`Are you sure you want to delete "${fileName}"?`);
                $('#deleteModal').removeClass('hidden');
            });

            // Handle confirm delete
            $('#confirmDelete').on('click', function() {
                if (currentForm) {
                    currentForm.submit();
                }
            });

            // Handle cancel delete
            $('#cancelDelete').on('click', function() {
                $('#deleteModal').addClass('hidden');
                currentForm = null;
            });

            // Close modal when clicking outside
            $('#deleteModal').on('click', function(e) {
                if (e.target === this) {
                    $(this).addClass('hidden');
                    currentForm = null;
                }
            });

            // Close modal on escape key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && !$('#deleteModal').hasClass('hidden')) {
                    $('#deleteModal').addClass('hidden');
                    currentForm = null;
                }
            });

            // Auto-hide success message after 3 seconds
            setTimeout(function() {
                closeSuccessMessage();
            }, 3000);
        });

        // Function to close success message
        function closeSuccessMessage() {
            const successAlert = document.getElementById('successAlert');
            if (successAlert) {
                successAlert.classList.add('opacity-0', 'transform', 'translate-y-[-100%]', 'transition-all', 'duration-500');
                setTimeout(() => {
                    successAlert.remove();
                }, 500);
            }
        }

        // Add these functions to your existing script section
        function previewImage(fileName, fileUrl) {
            const modal = document.getElementById('imagePreviewModal');
            const previewImg = document.getElementById('previewImage');
            const fileNameEl = document.getElementById('previewFileName');
            const loader = document.getElementById('imageLoader');
            
            // Show modal and loader
            modal.classList.remove('hidden');
            previewImg.classList.add('hidden');
            loader.classList.remove('hidden');
            
            fileNameEl.textContent = fileName;
            
            // Create new image object to preload
            const img = new Image();
            img.onload = function() {
                // Hide loader and show image
                loader.classList.add('hidden');
                previewImg.src = fileUrl;
                previewImg.classList.remove('hidden');
            };
            img.onerror = function() {
                // Handle error
                loader.classList.add('hidden');
                previewImg.classList.remove('hidden');
                previewImg.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-400"%3E%3Crect x="3" y="3" width="18" height="18" rx="2" ry="2"%3E%3C/rect%3E%3Ccircle cx="8.5" cy="8.5" r="1.5"%3E%3C/circle%3E%3Cpolyline points="21 15 16 10 5 21"%3E%3C/polyline%3E';
                alert('Error loading image');
            };
            img.src = fileUrl;
            
            // Close on escape key
            const escHandler = function(e) {
                if (e.key === 'Escape') {
                    closeImagePreview();
                    document.removeEventListener('keydown', escHandler);
                }
            };
            document.addEventListener('keydown', escHandler);
            
            // Close on outside click
            modal.onclick = function(e) {
                if (e.target === modal) {
                    closeImagePreview();
                }
            };
        }

        function closeImagePreview() {
            const modal = document.getElementById('imagePreviewModal');
            const previewImg = document.getElementById('previewImage');
            previewImg.src = '';
            modal.classList.add('hidden');
        }

        function showRenameModal(fileId, currentName) {
            const modal = document.getElementById('renameModal');
            const fileIdInput = document.getElementById('renameFileId');
            const nameInput = document.getElementById('new_name');
            
            // Get filename without extension
            const nameWithoutExt = currentName.substring(0, currentName.lastIndexOf('.'));
            
            modal.classList.remove('hidden');
            fileIdInput.value = fileId;
            nameInput.value = nameWithoutExt;
            nameInput.select();
        }

        function closeRenameModal() {
            const modal = document.getElementById('renameModal');
            modal.classList.add('hidden');
        }

        // Close rename modal on escape key and outside click
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !document.getElementById('renameModal').classList.contains('hidden')) {
                closeRenameModal();
            }
        });

        document.getElementById('renameModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRenameModal();
            }
        });

        function createNewFolder() {
            const modal = document.getElementById('createFolderModal');
            const folderNameInput = document.getElementById('folder_name');
            modal.classList.remove('hidden');
            folderNameInput.value = '';
            folderNameInput.focus();
        }

        function closeCreateFolderModal() {
            const modal = document.getElementById('createFolderModal');
            modal.classList.add('hidden');
        }

        // Add event listeners for the create folder modal
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('createFolderModal');
            
            // Close on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                    closeCreateFolderModal();
                }
            });

            // Close on outside click
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeCreateFolderModal();
                }
            });

            // Handle form submission
            document.getElementById('createFolderForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const folderName = document.getElementById('folder_name').value;
                
                fetch('/api/folders.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=create_folder&folder_name=${encodeURIComponent(folderName)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        closeCreateFolderModal();
                        window.location.reload();
                    } else {
                        throw new Error(data.error || 'Failed to create folder');
                    }
                })
                .catch(error => {
                    alert('Error creating folder: ' + error.message);
                });
            });
        });
        </script>
    <?php include 'footer.php'; ?>
    </div>

    <script>
        // Close success message
        function closeSuccessMessage() {
            document.getElementById('successAlert').classList.add('hidden');
        }
        
        // Vue app for file management
        document.addEventListener('DOMContentLoaded', function() {
            const { createApp } = Vue
            
            createApp({
                data() {
                    return {
                        // File Explorer State
                        items: [],
                        loading: true,
                        currentFolder: null,
                        breadcrumbs: [],
                        
                        // UI State
                        showUploadModal: false,
                        showCreateFolderModal: false,
                        showDeleteConfirmation: false,
                        showingContextMenu: false,
                        dragOver: false,
                        uploadDragOver: false,
                        
                        // Context Menu
                        contextMenuPosition: { x: 0, y: 0 },
                        contextMenuItem: {},
                        
                        // Upload
                        filesToUpload: [],
                        uploadStatus: {},
                        isUploading: false,
                        
                        // Edit/Delete State
                        editingItem: null,
                        editingName: '',
                        deleteItem: null,
                        
                        // Preview
                        previewImage: null,
                    }
                },
                created() {
                    // Load files when component is created
                    this.loadFiles();
                    
                    // Close context menu when clicking elsewhere
                    window.addEventListener('click', this.hideContextMenu);
                    window.addEventListener('keydown', this.handleKeyDown);
                },
                beforeUnmount() {
                    window.removeEventListener('click', this.hideContextMenu);
                    window.removeEventListener('keydown', this.handleKeyDown);
                },
                computed: {
                    // Split items between folders and files
                    folderItems() {
                        return this.items.filter(item => item.type === 'folder');
                    },
                    fileItems() {
                        return this.items.filter(item => item.type === 'file');
                    }
                },
                methods: {
                    // Files and folders loading
                    async loadFiles(folderId = null) {
                        this.loading = true;
                        try {
                            const response = await fetch(`/api/folders.php?action=list&parent_id=${folderId || ''}`);
                            if (!response.ok) throw new Error('Failed to load files');
                            
                            const data = await response.json();
                            this.items = [...data.folders, ...data.files];
                            this.breadcrumbs = data.breadcrumbs;
                            this.currentFolder = data.current_folder;
                            
                        } catch (error) {
                            console.error('Error loading files:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Could not load your files. Please try again later.'
                            });
                        } finally {
                            this.loading = false;
                        }
                    },
                    
                    // Folder navigation
                    navigateToFolder(folderId) {
                        this.loadFiles(folderId);
                    },
                    
                    // File operations
                    handleFileClick(file) {
                        if (file.is_image) {
                            this.previewImage = file;
                        } else {
                            window.open(`/download/${file.id}`, '_blank');
                        }
                    },
                    
                    getFileIconHtml(filename) {
                        // Extract the file extension to determine icon
                        const extension = filename.split('.').pop().toLowerCase();
                        
                        // Create DOM element to parse HTML strings
                        const parser = new DOMParser();
                        const icons = <?php echo json_encode(array_map(function($ext) use ($icons) {
                            return isset($icons[$ext]) ? $icons[$ext]['html'] : $icons['default']['html'];
                        }, array_merge(array_keys($icons), ['default']))); ?>;
                        
                        return icons[extension] || icons['default'];
                    },
                    
                    formatFileSize(bytes) {
                        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
                        let size = bytes;
                        let unitIndex = 0;
                        
                        while (size >= 1024 && unitIndex < units.length - 1) {
                            size /= 1024;
                            unitIndex++;
                        }
                        
                        return `${size.toFixed(1)} ${units[unitIndex]}`;
                    },
                    
                    // Context Menu
                    showContextMenu(event, item) {
                        event.preventDefault();
                        this.showingContextMenu = true;
                        this.contextMenuItem = item;
                        
                        // Position the menu
                        const menuWidth = 160;
                        const menuHeight = 150;
                        const windowWidth = window.innerWidth;
                        const windowHeight = window.innerHeight;
                        
                        // Adjust position to ensure menu stays within viewport
                        let x = event.clientX;
                        let y = event.clientY;
                        
                        if (x + menuWidth > windowWidth) {
                            x = windowWidth - menuWidth;
                        }
                        
                        if (y + menuHeight > windowHeight) {
                            y = windowHeight - menuHeight;
                        }
                        
                        this.contextMenuPosition = { x, y };
                    },
                    
                    hideContextMenu() {
                        this.showingContextMenu = false;
                    },
                    
                    // Folder creation
                    async createFolder() {
                        if (!this.newFolderName.trim()) {
                            return;
                        }
                        
                        try {
                            const formData = new FormData();
                            formData.append('name', this.newFolderName);
                            if (this.currentFolder) {
                                formData.append('parent_id', this.currentFolder);
                            }
                            
                            const response = await fetch('/api/folders.php?action=create', {
                                method: 'POST',
                                body: formData
                            });
                            
                            const data = await response.json();
                            
                            if (data.success) {
                                this.items.unshift(data.folder);
                                this.newFolderName = '';
                                this.showCreateFolderModal = false;
                            } else {
                                throw new Error(data.error || 'Failed to create folder');
                            }
                        } catch (error) {
                            console.error('Error creating folder:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: error.message || 'Could not create folder. Please try again.'
                            });
                        }
                    },
                    
                    // Rename operations
                    startRename(item) {
                        this.editingItem = item;
                        this.editingName = item.name;
                        this.$nextTick(() => {
                            // Focus and select the entire name without extension if it's a file
                            if (this.$refs.renameInput) {
                                this.$refs.renameInput.focus();
                                if (item.type === 'file') {
                                    const name = item.name;
                                    const lastDotIndex = name.lastIndexOf('.');
                                    if (lastDotIndex > 0) {
                                        // Select only the name part, not the extension
                                        this.$refs.renameInput.setSelectionRange(0, lastDotIndex);
                                    }
                                } else {
                                    this.$refs.renameInput.select();
                                }
                            }
                        });
                    },
                    
                    cancelRename() {
                        this.editingItem = null;
                        this.editingName = '';
                    },
                    
                    async renameItem(item) {
                        if (!this.editingName.trim() || this.editingName === item.name) {
                            this.cancelRename();
                            return;
                        }
                        
                        try {
                            let url, formData = new FormData();
                            
                            if (item.type === 'folder') {
                                url = '/api/folders.php?action=rename';
                                formData.append('folder_id', item.id);
                                formData.append('name', this.editingName);
                            } else {
                                // For files, preserve the extension
                                const oldName = item.name;
                                const lastDotIndex = oldName.lastIndexOf('.');
                                let newName = this.editingName;
                                
                                if (lastDotIndex > 0) {
                                    const extension = oldName.substring(lastDotIndex);
                                    // If user didn't add extension, append it automatically
                                    if (!newName.endsWith(extension)) {
                                        newName += extension;
                                    }
                                }
                                
                                url = '/api/rename.php';
                                formData.append('file_id', item.id);
                                formData.append('new_name', newName);
                                formData.append('action', 'rename');
                            }
                            
                            const response = await fetch(url, {
                                method: 'POST',
                                body: formData
                            });
                            
                            if (!response.ok) throw new Error('Failed to rename item');
                            
                            const data = await response.json();
                            if (data.success) {
                                // Update item in the list
                                if (item.type === 'folder') {
                                    item.name = this.editingName;
                                } else {
                                    item.name = data.name || this.editingName;
                                }
                            } else {
                                throw new Error(data.error || 'Failed to rename item');
                            }
                        } catch (error) {
                            console.error('Error renaming item:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: error.message || 'Could not rename item. Please try again.'
                            });
                        } finally {
                            this.cancelRename();
                        }
                    },
                    
                    // Delete operations
                    confirmDeleteItem(item) {
                        this.deleteItem = item;
                        this.showDeleteConfirmation = true;
                    },
                    
                    async deleteSelectedItem() {
                        if (!this.deleteItem) return;
                        
                        try {
                            let url, formData = new FormData();
                            
                            if (this.deleteItem.type === 'folder') {
                                url = '/api/folders.php?action=delete';
                                formData.append('folder_id', this.deleteItem.id);
                            } else {
                                url = '/';
                                formData.append('file_id', this.deleteItem.id);
                                formData.append('action', 'delete');
                            }
                            
                            const response = await fetch(url, {
                                method: 'POST',
                                body: formData
                            });
                            
                            if (!response.ok) throw new Error('Failed to delete item');
                            
                            const data = await response.json();
                            if (data.success) {
                                // Remove item from the list
                                this.items = this.items.filter(item => item.id !== this.deleteItem.id);
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Success',
                                    text: `${this.deleteItem.type.charAt(0).toUpperCase() + this.deleteItem.type.slice(1)} deleted successfully`,
                                    showConfirmButton: false,
                                    timer: 1500
                                });
                            } else {
                                throw new Error(data.error || 'Failed to delete item');
                            }
                        } catch (error) {
                            console.error('Error deleting item:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: error.message || 'Could not delete item. Please try again.'
                            });
                        } finally {
                            this.showDeleteConfirmation = false;
                            this.deleteItem = null;
                        }
                    },
                    
                    // File upload operations
                    triggerFileInput() {
                        this.$refs.fileInput.click();
                    },
                    
                    handleFileInputChange(event) {
                        this.addFilesToUpload(event.target.files);
                    },
                    
                    handleUploadDrop(event) {
                        this.uploadDragOver = false;
                        this.addFilesToUpload(event.dataTransfer.files);
                    },
                    
                    handleFileDrop(event) {
                        this.dragOver = false;
                        this.addFilesToUpload(event.dataTransfer.files);
                        this.uploadFiles();
                    },
                    
                    addFilesToUpload(fileList) {
                        for (const file of fileList) {
                            // Skip duplicate files by name
                            if (!this.filesToUpload.find(f => f.name === file.name)) {
                                this.filesToUpload.push(file);
                            }
                        }
                    },
                    
                    removeFileToUpload(index) {
                        this.filesToUpload.splice(index, 1);
                    },
                    
                    async uploadFiles() {
                        if (this.filesToUpload.length === 0 || this.isUploading) return;
                        
                        this.isUploading = true;
                        let uploads = [];
                        
                        for (let i = 0; i < this.filesToUpload.length; i++) {
                            if (this.uploadStatus[i]?.completed) continue;
                            
                            // Set initial upload status
                            this.$set(this.uploadStatus, i, {
                                uploading: true,
                                progress: 0,
                                completed: false,
                                error: false,
                                errorMessage: null
                            });
                            
                            const file = this.filesToUpload[i];
                            const formData = new FormData();
                            formData.append('files[]', file);
                            
                            if (this.currentFolder) {
                                formData.append('folder_id', this.currentFolder);
                            }
                            
                            uploads.push(this.uploadFile(formData, i));
                        }
                        
                        // Wait for all uploads to complete
                        await Promise.all(uploads);
                        
                        // Reload files to show the new uploads
                        await this.loadFiles(this.currentFolder);
                        
                        // Reset upload state if all were successful
                        const allCompleted = Object.values(this.uploadStatus).every(status => status.completed);
                        if (allCompleted) {
                            setTimeout(() => {
                                this.filesToUpload = [];
                                this.uploadStatus = {};
                                this.showUploadModal = false;
                                this.isUploading = false;
                            }, 1500);
                        } else {
                            this.isUploading = false;
                        }
                    },
                    
                    async uploadFile(formData, index) {
                        try {
                            const xhr = new XMLHttpRequest();
                            
                            // Track progress
                            xhr.upload.addEventListener('progress', (event) => {
                                if (event.lengthComputable) {
                                    const percentComplete = Math.round((event.loaded / event.total) * 100);
                                    this.$set(this.uploadStatus[index], 'progress', percentComplete);
                                }
                            });
                            
                            // Create a promise to handle the upload
                            return new Promise((resolve, reject) => {
                                xhr.open('POST', '/upload.php', true);
                                
                                xhr.onload = () => {
                                    if (xhr.status >= 200 && xhr.status < 300) {
                                        const response = JSON.parse(xhr.responseText);
                                        if (response.success) {
                                            this.$set(this.uploadStatus[index], 'completed', true);
                                            resolve();
                                        } else {
                                            this.$set(this.uploadStatus[index], 'error', true);
                                            this.$set(this.uploadStatus[index], 'errorMessage', response.errors[0] || 'Upload failed');
                                            reject(new Error(response.errors[0]));
                                        }
                                    } else {
                                        this.$set(this.uploadStatus[index], 'error', true);
                                        this.$set(this.uploadStatus[index], 'errorMessage', 'Server error');
                                        reject(new Error('Server error'));
                                    }
                                };
                                
                                xhr.onerror = () => {
                                    this.$set(this.uploadStatus[index], 'error', true);
                                    this.$set(this.uploadStatus[index], 'errorMessage', 'Network error');
                                    reject(new Error('Network error'));
                                };
                                
                                xhr.send(formData);
                            });
                        } catch (error) {
                            console.error('Error uploading file:', error);
                            this.$set(this.uploadStatus[index], 'error', true);
                            this.$set(this.uploadStatus[index], 'errorMessage', error.message || 'Upload failed');
                            throw error;
                        } finally {
                            this.$set(this.uploadStatus[index], 'uploading', false);
                        }
                    },
                    
                    cancelUpload() {
                        if (this.isUploading) return;
                        this.filesToUpload = [];
                        this.uploadStatus = {};
                        this.showUploadModal = false;
                    },
                    
                    // Keyboard navigation
                    handleKeyDown(event) {
                        if (event.key === 'Escape') {
                            if (this.showingContextMenu) {
                                this.hideContextMenu();
                            }
                            if (this.editingItem) {
                                this.cancelRename();
                            }
                            if (this.showUploadModal) {
                                this.cancelUpload();
                            }
                            if (this.showCreateFolderModal) {
                                this.showCreateFolderModal = false;
                            }
                            if (this.showDeleteConfirmation) {
                                this.showDeleteConfirmation = false;
                                this.deleteItem = null;
                            }
                            if (this.previewImage) {
                                this.previewImage = null;
                            }
                        }
                    }
                },
                mounted() {
                    // When the modal is shown, focus the folder name input
                    this.$watch('showCreateFolderModal', (isVisible) => {
                        if (isVisible) {
                            this.$nextTick(() => {
                                this.$refs.folderNameInput?.focus();
                            });
                        } else {
                            this.newFolderName = '';
                        }
                    });
                }
            }).mount('#app');
        });
    </script>
</body>
</html>