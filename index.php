<?php
// Start output buffering to prevent any unwanted output before JSON response
ob_start();

require_once __DIR__ . '/config.php';
session_start();
require_once __DIR__ . '/vendor/autoload.php';
use Spatie\Dropbox\Client as DropboxClient;

// Handle chunk upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'upload_chunk') {
    // Clean any previous output before sending JSON
    ob_clean();
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Login required to upload files'
        ]);
        exit;
    }

    header('Content-Type: application/json');
    try {
        // Get chunk data and metadata
        $chunkData = file_get_contents('php://input');
        
        if (empty($chunkData)) {
            throw new Exception('No chunk data received');
        }
        
        $chunkNum = isset($_GET['chunk']) ? (int)$_GET['chunk'] : null;
        $totalChunks = isset($_GET['chunks']) ? (int)$_GET['chunks'] : null;
        $fileName = isset($_GET['name']) ? $_GET['name'] : '';
        $fileSize = isset($_GET['size']) ? (int)$_GET['size'] : 0;
        $fileType = isset($_GET['type']) ? $_GET['type'] : '';
        $tempId = isset($_GET['temp_id']) ? $_GET['temp_id'] : null;
        
        // Log metadata for debugging
        error_log("Chunk Upload: chunk=$chunkNum, total=$totalChunks, name=$fileName, size=$fileSize bytes");
        
        // Validate required parameters
        if ($chunkNum === null || $totalChunks === null || empty($fileName) || empty($tempId)) {
            throw new Exception('Missing required chunk parameters');
        }
        
        // Validate chunk number in range
        if ($chunkNum < 0 || $chunkNum >= $totalChunks) {
            throw new Exception('Invalid chunk number');
        }
        
        // Validate file type (more permissive to allow for application/x-zip-compressed)
        $allowedTypes = [
            // Images
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
            // Audio
            'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4', 'audio/aac',
            // Video
            'video/mp4', 'video/mpeg', 'video/webm', 'video/quicktime', 'video/x-msvideo',
            // Archives
            'application/zip', 'application/x-zip-compressed', 'application/x-rar-compressed', 'application/x-7z-compressed',
            'application/x-tar', 'application/gzip', 'application/octet-stream',
            // Documents
            'application/pdf', 'image/vnd.djvu',
            // Other media
            'application/vnd.apple.mpegurl', 'application/x-mpegurl'
        ];

        // More permissive check for file types
        $isAllowed = false;
        foreach ($allowedTypes as $type) {
            if (stripos($fileType, $type) !== false || $type == 'application/octet-stream') {
                $isAllowed = true;
                break;
            }
        }
        
        if (!$isAllowed) {
            throw new Exception('File type not allowed. Only media and archive files are supported.');
        }

        // Add size validation (2GB = 2 * 1024 * 1024 * 1024 bytes)
        if ($fileSize > 2 * 1024 * 1024 * 1024) {
            throw new Exception('File size exceeds 2 GB limit');
        }

        // Create temp directory structure if it doesn't exist
        $tempBaseDir = __DIR__ . '/temp';
        if (!is_dir($tempBaseDir)) {
            if (!mkdir($tempBaseDir, 0777, true)) {
                throw new Exception('Failed to create temp directory');
            }
        }
        
        // Create upload-specific directory to keep chunks organized
        $uploadDir = $tempBaseDir . '/' . $tempId;
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                throw new Exception('Failed to create upload directory');
            }
            
            // Create a metadata file to track upload details
            $metaData = [
                'fileName' => $fileName,
                'fileSize' => $fileSize,
                'fileType' => $fileType,
                'totalChunks' => $totalChunks,
                'uploadedChunks' => [],
                'startTime' => time(),
                'userId' => $_SESSION['user_id']
            ];
            
            $metaFile = $uploadDir . '/metadata.json';
            if (!file_put_contents($metaFile, json_encode($metaData))) {
                throw new Exception('Failed to create upload metadata');
            }
        } else {
            // Load and verify metadata
            $metaFile = $uploadDir . '/metadata.json';
            if (!file_exists($metaFile)) {
                throw new Exception('Upload metadata missing, please restart upload');
            }
            
            $metaData = json_decode(file_get_contents($metaFile), true);
            
            // Verify this chunk is for the same file (more permissive check)
            if ($metaData['fileName'] !== $fileName || 
                $metaData['fileSize'] !== $fileSize || 
                $metaData['totalChunks'] !== $totalChunks) {
                throw new Exception('Chunk metadata mismatch, please restart upload');
            }
        }
        
        // Save chunk with retry logic
        $chunkPath = $uploadDir . '/chunk_' . $chunkNum;
        $maxRetries = 3;
        $retries = 0;
        $success = false;
        
        while ($retries < $maxRetries && !$success) {
            if (file_put_contents($chunkPath, $chunkData) !== false) {
                $success = true;
                error_log("Successfully saved chunk $chunkNum to $chunkPath");
            } else {
                $retries++;
                error_log("Failed to save chunk $chunkNum (attempt $retries/$maxRetries)");
                usleep(100000); // Wait 100ms before retrying
            }
        }
        
        if (!$success) {
            throw new Exception("Failed to save chunk $chunkNum after $maxRetries attempts");
        }
        
        // Update metadata to mark this chunk as complete
        if (!in_array($chunkNum, $metaData['uploadedChunks'])) {
            $metaData['uploadedChunks'][] = $chunkNum;
        }
        $metaData['lastActivity'] = time();
        file_put_contents($metaFile, json_encode($metaData));
        
        // Check if we have all chunks
        $uniqueChunks = array_unique($metaData['uploadedChunks']);
        $isComplete = count($uniqueChunks) === $totalChunks;
        
        if ($isComplete) {
            // All chunks received, combine them into a single file
            $completePath = $uploadDir . '/complete_' . $fileName;
            $completeFile = fopen($completePath, 'wb');
            
            if (!$completeFile) {
                throw new Exception('Failed to create complete file');
            }
            
            // Combine chunks in order
            $errors = [];
            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkPath = $uploadDir . '/chunk_' . $i;
                if (!file_exists($chunkPath)) {
                    $errors[] = "Missing chunk $i";
                    continue;
                }
                
                $chunk = file_get_contents($chunkPath);
                if ($chunk === false) {
                    $errors[] = "Failed to read chunk $i";
                    continue;
                }
                
                if (fwrite($completeFile, $chunk) === false) {
                    $errors[] = "Failed to write chunk $i to complete file";
                }
            }
            
            fclose($completeFile);
            
            if (!empty($errors)) {
                throw new Exception('Errors combining file: ' . implode(', ', $errors));
            }
            
            // Verify the final file size
            $actualSize = filesize($completePath);
            if ($actualSize !== $fileSize) {
                throw new Exception("File size mismatch: expected $fileSize bytes, got $actualSize bytes");
            }
            
            // Get Dropbox credentials with available storage
            $db = getDBConnection();
            $dropbox = $db->query("
                SELECT da.*, 
                       COALESCE(SUM(fu.size), 0) as used_storage
                FROM dropbox_accounts da
                LEFT JOIN file_uploads fu ON fu.dropbox_account_id = da.id 
                    AND fu.upload_status = 'completed'
                GROUP BY da.id
                HAVING used_storage < 2147483648 OR used_storage IS NULL
                LIMIT 1
            ")->fetch_assoc();

            if (!$dropbox) {
                throw new Exception('No Dropbox account with available storage');
            }

            // Initialize Dropbox client with the selected account
            $client = new Spatie\Dropbox\Client($dropbox['access_token']);
            
            // Generate unique file ID
            $fileId = uniqid();
            
            // Upload to Dropbox with retry logic
            $dropboxPath = "/{$fileId}/{$fileName}";
            $uploaded = false;
            $uploadRetries = 0;
            $maxUploadRetries = 3;
            
            while (!$uploaded && $uploadRetries < $maxUploadRetries) {
                try {
                    $client->upload($dropboxPath, fopen($completePath, 'r'), 'add');
                    $uploaded = true;
                } catch (Exception $e) {
                    $uploadRetries++;
                    if ($uploadRetries >= $maxUploadRetries) {
                        throw new Exception('Failed to upload to Dropbox after multiple attempts: ' . $e->getMessage());
                    }
                    sleep(2); // Wait before retrying
                }
            }
            
            // Save file info to database with the selected Dropbox account
            $stmt = $db->prepare("INSERT INTO file_uploads (
                file_id, 
                file_name, 
                size, 
                upload_status, 
                dropbox_path, 
                dropbox_account_id, 
                uploaded_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            $status = 'completed';
            $stmt->bind_param("ssissii", 
                $fileId,
                $fileName,
                $fileSize,
                $status,
                $dropboxPath,
                $dropbox['id'],
                $_SESSION['user_id']
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to save file data to database: ' . $stmt->error);
            }

            // Clean up the temporary files
            cleanupTempFiles($uploadDir);

            $downloadLink = "https://" . $_SERVER['HTTP_HOST'] . "/download/" . $fileId;
            
            echo json_encode([
                'success' => true,
                'downloadLink' => $downloadLink,
                'complete' => true,
                'fileId' => $fileId
            ]);
        } else {
            // Return success for this chunk with progress info
            $progress = [
                'receivedChunks' => count($uniqueChunks),
                'totalChunks' => $totalChunks,
                'percent' => round((count($uniqueChunks) / $totalChunks) * 100)
            ];
            
            echo json_encode([
                'success' => true,
                'chunk' => $chunkNum,
                'temp_id' => $tempId,
                'progress' => $progress,
                'complete' => false
            ]);
        }
        exit;
    } catch (Exception $e) {
        error_log("Chunk upload error: " . $e->getMessage());
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}

// Add verification endpoint for chunk uploads
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'upload_chunk' && isset($_GET['verify'])) {
    ob_clean();
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Login required']);
        exit;
    }
    
    try {
        $tempId = isset($_GET['temp_id']) ? $_GET['temp_id'] : null;
        $fileName = isset($_GET['name']) ? $_GET['name'] : '';
        
        if (empty($tempId) || empty($fileName)) {
            throw new Exception('Missing parameters');
        }
        
        $uploadDir = __DIR__ . '/temp/' . $tempId;
        $metaFile = $uploadDir . '/metadata.json';
        
        if (!file_exists($metaFile)) {
            throw new Exception('Upload session not found');
        }
        
        $metaData = json_decode(file_get_contents($metaFile), true);
        
        if ($metaData['fileName'] !== $fileName) {
            throw new Exception('File mismatch');
        }
        
        // Check if all chunks are present
        $uniqueChunks = array_unique($metaData['uploadedChunks']);
        $isComplete = count($uniqueChunks) === $metaData['totalChunks'];
        
        echo json_encode([
            'success' => true,
            'complete' => $isComplete,
            'chunksReceived' => count($uniqueChunks),
            'totalChunks' => $metaData['totalChunks']
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Helper function to clean up temporary files
function cleanupTempFiles($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = glob($dir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    
    rmdir($dir);
}

// Original file upload handler for smaller files
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action']) && !isset($_GET['action'])) {
    // Clean any previous output before sending JSON
    ob_clean();
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Login required to upload files'
        ]);
        exit;
    }

    header('Content-Type: application/json');
    try {
        if (!isset($_FILES['file'])) {
            throw new Exception('No file uploaded');
        }

        // Add allowed file types
        $allowedTypes = [
            // Images
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
            // Audio
            'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4', 'audio/aac',
            // Video
            'video/mp4', 'video/mpeg', 'video/webm', 'video/quicktime', 'video/x-msvideo',
            // Archives
            'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed',
            'application/x-tar', 'application/gzip',
            // Documents
            'application/pdf', 'image/vnd.djvu',
            // Other media
            'application/vnd.apple.mpegurl', 'application/x-mpegurl'
        ];

        // Get file mime type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            throw new Exception('File type not allowed. Only media and archive files are supported.');
        }

        // Add size validation (2GB = 2 * 1024 * 1024 * 1024 bytes)
        if ($file['size'] > 2 * 1024 * 1024 * 1024) {
            throw new Exception('File size exceeds 2 GB limit');
        }

        // Get Dropbox credentials with available storage
        $db = getDBConnection();
        $dropbox = $db->query("
            SELECT da.*, 
                   COALESCE(SUM(fu.size), 0) as used_storage
            FROM dropbox_accounts da
            LEFT JOIN file_uploads fu ON fu.dropbox_account_id = da.id 
                AND fu.upload_status = 'completed'
            GROUP BY da.id
            HAVING used_storage < 2147483648 OR used_storage IS NULL
            LIMIT 1
        ")->fetch_assoc();

        if (!$dropbox) {
            throw new Exception('No Dropbox account with available storage');
        }

        // Initialize Dropbox client with the selected account
        $client = new Spatie\Dropbox\Client($dropbox['access_token']);
        
        // Generate unique file ID
        $fileId = uniqid();
        
        // Upload to Dropbox
        $dropboxPath = "/{$fileId}/{$file['name']}";
        $fileContents = file_get_contents($file['tmp_name']);
        $client->upload($dropboxPath, $fileContents, 'add');
        
        // Save file info to database with the selected Dropbox account
        $stmt = $db->prepare("INSERT INTO file_uploads (
            file_id, 
            file_name, 
            size, 
            upload_status, 
            dropbox_path, 
            dropbox_account_id, 
            uploaded_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $status = 'completed';
        $stmt->bind_param("ssissii", 
            $fileId,
            $file['name'],
            $file['size'],
            $status,
            $dropboxPath,
            $dropbox['id'],
            $_SESSION['user_id']
        );
        $stmt->execute();

        $downloadLink = "https://" . $_SERVER['HTTP_HOST'] . "/download/" . $fileId;        
        
        echo json_encode([
            'success' => true,
            'downloadLink' => $downloadLink
        ]);
        exit;

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}

// If we reach here, it's a normal page request, so we flush the buffer
// before sending HTML content
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Primary Meta Tags -->
    <title>FilesWith - Fast & Secure File Sharing Platform</title>
    <meta name="title" content="FilesWith - Fast & Secure File Sharing Platform">
    <meta name="description" content="Share files securely with FilesWith. Upload and share files with anyone, anywhere with end-to-end encryption and cloud storage capabilities.">
    <meta name="robots" content="index, follow">
    <meta name="language" content="English">
    <meta name="author" content="FilesWith">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://<?php echo $_SERVER['HTTP_HOST']; ?>">
    <meta property="og:title" content="FilesWith - Fast & Secure File Sharing Platform">
    <meta property="og:description" content="Share files securely with FilesWith. Upload and share files with anyone, anywhere with end-to-end encryption and cloud storage capabilities.">
    <meta property="og:image" content="https://<?php echo $_SERVER['HTTP_HOST']; ?>/icon.png">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://<?php echo $_SERVER['HTTP_HOST']; ?>">
    <meta property="twitter:title" content="FilesWith - Fast & Secure File Sharing Platform">
    <meta property="twitter:description" content="Share files securely with FilesWith. Upload and share files with anyone, anywhere with end-to-end encryption and cloud storage capabilities.">
    <meta property="twitter:image" content="https://<?php echo $_SERVER['HTTP_HOST']; ?>/icon.png">

    <!-- Canonical URL -->
    <link rel="canonical" href="https://<?php echo $_SERVER['HTTP_HOST']; ?>">

    <!-- JSON-LD Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebApplication",
        "name": "FilesWith",
        "description": "Share files securely with FilesWith. Upload and share files with anyone, anywhere with end-to-end encryption and cloud storage capabilities.",
        "url": "https://<?php echo $_SERVER['HTTP_HOST']; ?>",
        "applicationCategory": "File Sharing",
        "offers": {
            "@type": "Offer",
            "price": "0",
            "priceCurrency": "USD"
        },
        "featureList": [
            "Secure file sharing",
            "Cloud storage",
            "Fast transfer speeds",
            "End-to-end encryption"
        ],
        "operatingSystem": "All",
        "aggregateRating": {
            "@type": "AggregateRating",
            "ratingValue": "4.8",
            "ratingCount": "1000"
        },
        "creator": {
            "@type": "Organization",
            "name": "FilesWith",
            "url": "https://<?php echo $_SERVER['HTTP_HOST']; ?>"
        }
    }
    </script>

    <!-- Breadcrumb Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [{
            "@type": "ListItem",
            "position": 1,
            "name": "Home",
            "item": "https://<?php echo $_SERVER['HTTP_HOST']; ?>"
        }]
    }
    </script>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="icon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="icon.png">
    
    <!-- Resources -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/nprogress@0.2.0/nprogress.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/nprogress@0.2.0/nprogress.css">

    <!-- JSON-LD Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebApplication",
        "name": "FilesWith",
        "description": "Share files securely with FilesWith. Upload and share files with anyone, anywhere with end-to-end encryption and cloud storage capabilities.",
        "url": "https://<?php echo $_SERVER['HTTP_HOST']; ?>",
        "applicationCategory": "File Sharing",
        "offers": {
            "@type": "Offer",
            "price": "0",
            "priceCurrency": "USD"
        },
        "featureList": [
            "Secure file sharing",
            "Cloud storage",
            "Fast transfer speeds",
            "End-to-end encryption"
        ],
        "operatingSystem": "All"
    }
    </script>

</head>
<body class="bg-gray-50">
<?php include 'header.php'; ?>

    <div id="app" class="min-h-screen">
        <main class="container mx-auto px-4 py-12">
            <!-- Hero Section -->
            <div class="text-center mb-12">
                <h1 class="text-4xl font-bold text-gray-900 mb-4">
                    Share Files Securely
                </h1>
                <p class="text-xl text-gray-600">
                    Upload and share files with anyone, anywhere.
                </p>
            </div>

            <!-- Upload Section -->
            <div class="max-w-3xl mx-auto">
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <!-- Show login prompt for non-authenticated users -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
                        <div class="text-center">
                            <div class="mx-auto h-12 w-12 text-gray-400 mb-4">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M12 15v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Login Required</h3>
                            <p class="text-gray-600 mb-4">Please login or register to upload files</p>
                            <div class="flex justify-center space-x-4">
                                <a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" 
                                   class="inline-flex items-center px-4 py-2 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                    Login
                                </a>
                                <a href="register.php" 
                                   class="inline-flex items-center px-4 py-2 border border-gray-300 text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    Register
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Original upload form for logged-in users -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
                        <form @submit.prevent="uploadFile" method="POST" enctype="multipart/form-data" class="space-y-6">
                            <div class="border-2 border-dashed border-gray-300 rounded-xl p-10 text-center transition-colors duration-150 ease-in-out hover:border-blue-500"
                                 @dragover.prevent 
                                 @drop.prevent="handleDrop"
                                 :class="{ 'border-blue-500 bg-blue-50': uploading }">
                                
                                <div v-if="!uploading">
                                    <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                    <h3 class="mt-4 text-lg font-medium text-gray-900">Upload your file</h3>
                                    <p class="mt-2 text-gray-600">Drag and drop or click to select</p>
                                    <div class="mt-2 text-sm text-gray-500">
                                        Maximum file size: 2 GB<br>
                                        Supported formats: Images, Audio, Video, Archives
                                    </div>
                                    <input type="file" class="hidden" @change="handleFileSelect" ref="fileInput" required>
                                    <button type="button" @click="$refs.fileInput.click()" 
                                        class="mt-4 inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                        Select File
                                    </button>
                                </div>

                                <div v-else class="space-y-4">
                                    <div class="text-center mb-2" v-if="isLargeFile">
                                        <p class="text-sm font-medium text-gray-700">
                                            Uploading large file ({{ formatFileSize(fileSize) }})
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            Chunk {{ currentChunk + 1 }} of {{ totalChunks }} 
                                            <span v-if="chunkRetries > 0">(Retry: {{ chunkRetries }})</span>
                                        </p>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-3">
                                        <div class="bg-blue-600 h-3 rounded-full transition-all duration-150" 
                                             :style="{ width: progress + '%' }">
                                        </div>
                                    </div>
                                    <p class="text-sm font-medium text-gray-600">
                                        Uploading... {{ progress }}%
                                    </p>
                                    <p class="text-xs text-gray-500" v-if="uploadSpeed">
                                        {{ uploadSpeed }} | {{ timeRemaining }}
                                    </p>
                                    <p v-if="uploadError" class="text-xs text-red-500 mt-2">
                                        {{ uploadError }} <button @click="retryCurrentChunk" class="text-blue-500 underline ml-1">Retry</button>
                                    </p>
                                </div>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Success Section with Download Link -->
                <div v-if="showDownloadSection" class="mt-8">
                    <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                        <div class="text-center mb-6">
                            <!-- Success Icon -->
                            <div class="mx-auto h-12 w-12 text-green-500 mb-4">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            
                            <h3 class="text-xl font-semibold text-gray-900 mb-2">File Upload Successful!</h3>
                            <p class="text-gray-600">Your file is ready to be shared</p>
                        </div>
                
                        <!-- Download Link Section -->
                        <div class="space-y-4">
                            <label class="block text-sm font-medium text-gray-700">Share this secure link</label>
                            <div class="flex flex-col sm:flex-row gap-3">
                                <div class="flex-1">
                                    <input type="text" 
                                           :value="downloadLink"
                                           class="w-full px-4 py-2 text-gray-800 border border-gray-300 rounded-lg bg-gray-50 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                           readonly
                                           ref="downloadInput">
                                </div>
                                <button @click="copyDownloadLink" 
                                        class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                              d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                                    </svg>
                                    Copy Link
                                </button>
                            </div>
                        </div>
                
                        <!-- Quick Actions -->
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                                <a href="/" 
                                   class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-150">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                              d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Upload Another File
                                </a>
                                <a :href="downloadLink" target = "_blank"
                                   class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                    View Download Page
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Features Section -->
                <div class="mt-12 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Existing Secure Sharing Feature -->
                    <div class="text-center p-6">
                        <div class="bg-blue-100 rounded-full p-3 inline-flex mx-auto mb-4">
                            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold">Secure Sharing</h3>
                        <p class="text-gray-600 mt-2">Your files are encrypted and protected</p>
                    </div>

                    <!-- Easy Access Feature -->
                    <div class="text-center p-6">
                        <div class="bg-green-100 rounded-full p-3 inline-flex mx-auto mb-4">
                            <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold">Cloud Storage</h3>
                        <p class="text-gray-600 mt-2">Access your files from anywhere, anytime</p>
                    </div>

                    <!-- Fast Transfer Feature -->
                    <div class="text-center p-6">
                        <div class="bg-purple-100 rounded-full p-3 inline-flex mx-auto mb-4">
                            <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold">Fast Transfer</h3>
                        <p class="text-gray-600 mt-2">Quick and efficient file transfers</p>
                    </div>
                </div>

                <!-- How It Works Section -->
                <div class="mt-16">
                    <h2 class="text-3xl font-bold text-center text-gray-900 mb-8">How It Works</h2>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                        <!-- Step 1 -->
                        <div class="text-center">
                            <div class="bg-gray-100 rounded-full w-12 h-12 flex items-center justify-center mx-auto mb-4">
                                <span class="text-xl font-bold text-gray-700">1</span>
                            </div>
                            <h3 class="text-lg font-semibold mb-2">Upload</h3>
                            <p class="text-gray-600">Select or drag & drop your files</p>
                        </div>

                        <!-- Step 2 -->
                        <div class="text-center">
                            <div class="bg-gray-100 rounded-full w-12 h-12 flex items-center justify-center mx-auto mb-4">
                                <span class="text-xl font-bold text-gray-700">2</span>
                            </div>
                            <h3 class="text-lg font-semibold mb-2">Get Link</h3>
                            <p class="text-gray-600">Receive your secure sharing link</p>
                        </div>

                        <!-- Step 3 -->
                        <div class="text-center">
                            <div class="bg-gray-100 rounded-full w-12 h-12 flex items-center justify-center mx-auto mb-4">
                                <span class="text-xl font-bold text-gray-700">3</span>
                            </div>
                            <h3 class="text-lg font-semibold mb-2">Share</h3>
                            <p class="text-gray-600">Share the link with anyone</p>
                        </div>

                        <!-- Step 4 -->
                        <div class="text-center">
                            <div class="bg-gray-100 rounded-full w-12 h-12 flex items-center justify-center mx-auto mb-4">
                                <span class="text-xl font-bold text-gray-700">4</span>
                            </div>
                            <h3 class="text-lg font-semibold mb-2">Download</h3>
                            <p class="text-gray-600">Recipients download securely</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        const { createApp } = Vue
        createApp({
            data() {
                return {
                    uploading: false,
                    progress: 0,
                    downloadLink: '',
                    showDownloadSection: false,
                    isLargeFile: false,
                    fileSize: 0,
                    currentChunk: 0,
                    totalChunks: 0,
                    uploadSpeed: '',
                    timeRemaining: '',
                    uploadError: '',
                    chunkRetries: 0,
                    maxChunkRetries: 3,
                    tempId: '',
                    currentFile: null,
                    uploadStartTime: null,
                    chunkSize: 5 * 1024 * 1024, // 5MB chunks by default
                    uploadStats: {
                        totalBytes: 0,
                        uploadedBytes: 0,
                        startTime: 0
                    }
                }
            },
            methods: {
                handleDrop(e) {
                    e.preventDefault()
                    const files = e.dataTransfer.files
                    if (files.length) this.uploadFile(files[0])
                },
                handleFileSelect(e) {
                    const files = e.target.files;
                    if (files.length) {
                        const file = files[0];
                        // Add client-side file type validation
                        const allowedExtensions = [
                            // Images
                            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
                            // Audio
                            'mp3', 'wav', 'ogg', 'm4a', 'aac',
                            // Video
                            'mp4', 'mpeg', 'webm', 'mov', 'avi',
                            // Archives
                            'zip', 'rar', '7z', 'tar', 'gz',
                            // Documents
                            'pdf', 'djvu',
                            // Other media
                            'm3u8', 'm3u'
                        ];
                        
                        const extension = file.name.split('.').pop().toLowerCase();
                        if (!allowedExtensions.includes(extension)) {
                            alert('File type not allowed. Only media and archive files are supported.');
                            return;
                        }
                        
                        this.uploadFile(file);
                    }
                },
                async uploadFile(file) {
                    // Add size check at the start
                    const maxSize = 2 * 1024 * 1024 * 1024; // 2GB in bytes
                    if (file.size > maxSize) {
                        alert('File is too large. Maximum file size is 2 GB.');
                        return;
                    }

                    this.uploading = true;
                    this.progress = 0;
                    this.uploadError = '';
                    this.chunkRetries = 0;
                    this.currentFile = file;
                    this.fileSize = file.size;
                    
                    // Adjust chunk size based on file size for better performance
                    if (file.size > 500 * 1024 * 1024) {
                        this.chunkSize = 10 * 1024 * 1024; // 10MB for files > 500MB
                    } else if (file.size > 100 * 1024 * 1024) {
                        this.chunkSize = 5 * 1024 * 1024; // 5MB for files > 100MB
                    } else if (file.size > 10 * 1024 * 1024) {
                        this.chunkSize = 2 * 1024 * 1024; // 2MB for files > 10MB
                    } else {
                        this.chunkSize = 1 * 1024 * 1024; // 1MB for smaller files
                    }
                    
                    // Determine if we should use chunked upload
                    this.isLargeFile = file.size > 10 * 1024 * 1024; // Consider large if > 10MB
                    
                    try {
                        if (this.isLargeFile) {
                            await this.uploadLargeFile(file);
                        } else {
                            await this.uploadSmallFile(file);
                        }
                    } catch (error) {
                        console.error('Upload failed:', error);
                        this.uploadError = `Upload failed: ${error.message}`;
                        this.uploading = false;
                    }
                },
                async uploadSmallFile(file) {
                    const formData = new FormData();
                    formData.append('file', file);

                    try {
                        NProgress.start();
                        const xhr = new XMLHttpRequest();
                        
                        // Setup progress tracking
                        xhr.upload.addEventListener('progress', (e) => {
                            if (e.lengthComputable) {
                                this.progress = Math.round((e.loaded * 100) / e.total);
                                this.updateUploadStats(e.loaded);
                            }
                        });

                        // Create promise to handle the upload
                        const uploadPromise = new Promise((resolve, reject) => {
                            xhr.onload = () => {
                                if (xhr.status >= 200 && xhr.status < 300) {
                                    try {
                                        const response = JSON.parse(xhr.responseText);
                                        resolve(response);
                                    } catch (e) {
                                        reject(new Error('Invalid JSON response'));
                                    }
                                } else {
                                    reject(new Error('Upload failed'));
                                }
                            };
                            xhr.onerror = () => reject(new Error('Network error'));
                        });

                        // Configure and send request
                        xhr.open('POST', 'index.php', true);
                        xhr.send(formData);

                        // Wait for upload to complete
                        const response = await uploadPromise;
                        
                        if (!response.success) {
                            throw new Error(response.error || 'Upload failed');
                        }
                        
                        this.downloadLink = response.downloadLink;
                        this.showDownloadSection = true;
                    } catch (error) {
                        console.error('Upload error:', error);
                        alert('Upload failed: ' + error.message);
                    } finally {
                        this.uploading = false;
                        NProgress.done();
                    }
                },
                async uploadLargeFile(file) {
                    this.totalChunks = Math.ceil(file.size / this.chunkSize);
                    this.currentChunk = 0;
                    this.tempId = `upload_${Date.now()}_${Math.random().toString(36).substring(2, 15)}`;
                    this.uploadStartTime = Date.now();
                    
                    // Setup upload stats tracking
                    this.uploadStats = {
                        totalBytes: file.size,
                        uploadedBytes: 0,
                        startTime: Date.now()
                    };

                    NProgress.start();
                    
                    // Process chunks sequentially with reliability improvements
                    try {
                        for (let i = 0; i < this.totalChunks; i++) {
                            this.currentChunk = i;
                            this.chunkRetries = 0;
                            
                            let uploadSuccess = false;
                            
                            // Retry logic for each chunk
                            while (!uploadSuccess && this.chunkRetries < this.maxChunkRetries) {
                                try {
                                    await this.uploadChunk(i);
                                    uploadSuccess = true;
                                } catch (error) {
                                    this.chunkRetries++;
                                    this.uploadError = `Error uploading chunk ${i+1}: ${error.message}`;
                                    
                                    if (this.chunkRetries >= this.maxChunkRetries) {
                                        throw new Error(`Failed to upload chunk ${i+1} after ${this.maxChunkRetries} attempts`);
                                    }
                                    
                                    // Wait before retrying (exponential backoff)
                                    const delay = Math.min(1000 * Math.pow(2, this.chunkRetries - 1), 10000);
                                    await new Promise(resolve => setTimeout(resolve, delay));
                                }
                            }
                            
                            // Update progress
                            this.progress = Math.round(((i + 1) * 100) / this.totalChunks);
                            this.updateUploadStats((i + 1) * this.chunkSize);
                        }
                        
                        // Final check to ensure all chunks were uploaded
                        const verificationResponse = await this.verifyUpload();
                        
                        if (verificationResponse.complete) {
                            this.downloadLink = verificationResponse.downloadLink;
                            this.showDownloadSection = true;
                        } else {
                            throw new Error('Upload verification failed');
                        }
                    } catch (error) {
                        console.error('Chunked upload error:', error);
                        this.uploadError = `Upload failed: ${error.message}`;
                        throw error;
                    } finally {
                        this.uploading = false;
                        NProgress.done();
                    }
                },
                async uploadChunk(chunkIndex) {
                    const start = chunkIndex * this.chunkSize;
                    const end = Math.min(start + this.chunkSize, this.currentFile.size);
                    const chunk = this.currentFile.slice(start, end);
                    
                    // Create URL with query parameters
                    const url = `index.php?action=upload_chunk&chunk=${chunkIndex}&chunks=${this.totalChunks}` + 
                              `&name=${encodeURIComponent(this.currentFile.name)}&size=${this.currentFile.size}` + 
                              `&type=${encodeURIComponent(this.currentFile.type)}&temp_id=${this.tempId}`;
                    
                    return new Promise((resolve, reject) => {
                        const xhr = new XMLHttpRequest();
                        
                        xhr.open('POST', url, true);
                        xhr.setRequestHeader('Content-Type', 'application/octet-stream');
                        
                        xhr.onload = () => {
                            if (xhr.status >= 200 && xhr.status < 300) {
                                try {
                                    const response = JSON.parse(xhr.responseText);
                                    if (response.success) {
                                        resolve(response);
                                    } else {
                                        reject(new Error(response.error || 'Upload failed'));
                                    }
                                } catch (e) {
                                    reject(new Error('Invalid response format'));
                                }
                            } else {
                                reject(new Error(`HTTP error ${xhr.status}: ${xhr.responseText}`));
                            }
                        };
                        
                        xhr.onerror = () => reject(new Error('Network error'));
                        xhr.ontimeout = () => reject(new Error('Upload timeout'));
                        
                        // Add progress tracking for each chunk
                        xhr.upload.onprogress = (e) => {
                            if (e.lengthComputable) {
                                const chunkProgress = (e.loaded / e.total) * 100;
                                this.progress = Math.round((chunkIndex * 100 / this.totalChunks) + (chunkProgress / this.totalChunks));
                                
                                // Update upload stats based on current chunk progress
                                const totalUploaded = (chunkIndex * this.chunkSize) + e.loaded;
                                this.updateUploadStats(totalUploaded);
                            }
                        };
                        
                        // Set timeout for larger chunks
                        xhr.timeout = 120000; // 2 minutes
                        
                        xhr.send(chunk);
                    });
                },
                async verifyUpload() {
                    // Create URL with query parameters for verification
                    const url = `index.php?action=upload_chunk&verify=true` + 
                              `&name=${encodeURIComponent(this.currentFile.name)}&size=${this.currentFile.size}` + 
                              `&type=${this.currentFile.type}&temp_id=${this.tempId}`;
                    
                    return new Promise((resolve, reject) => {
                        const xhr = new XMLHttpRequest();
                        xhr.open('GET', url, true);
                        
                        xhr.onload = () => {
                            if (xhr.status >= 200 && xhr.status < 300) {
                                try {
                                    const response = JSON.parse(xhr.responseText);
                                    resolve(response);
                                } catch (e) {
                                    reject(new Error('Invalid response format'));
                                }
                            } else {
                                reject(new Error(`HTTP error ${xhr.status}`));
                            }
                        };
                        
                        xhr.onerror = () => reject(new Error('Network error'));
                        xhr.send();
                    });
                },
                retryCurrentChunk() {
                    if (this.currentFile && this.uploading) {
                        this.uploadError = '';
                        this.chunkRetries = 0;
                        this.uploadChunk(this.currentChunk)
                            .then(response => {
                                if (response.complete) {
                                    this.downloadLink = response.downloadLink;
                                    this.showDownloadSection = true;
                                    this.uploading = false;
                                } else {
                                    this.currentChunk++;
                                    if (this.currentChunk < this.totalChunks) {
                                        this.uploadChunk(this.currentChunk);
                                    }
                                }
                            })
                            .catch(error => {
                                this.uploadError = `Retry failed: ${error.message}`;
                            });
                    }
                },
                updateUploadStats(bytesUploaded) {
                    const now = Date.now();
                    const bytesActual = Math.min(bytesUploaded, this.uploadStats.totalBytes);
                    this.uploadStats.uploadedBytes = bytesActual;
                    
                    // Calculate time elapsed in seconds
                    const timeElapsed = (now - this.uploadStats.startTime) / 1000;
                    
                    if (timeElapsed > 0) {
                        // Calculate upload speed (bytes per second)
                        const bps = bytesActual / timeElapsed;
                        this.uploadSpeed = this.formatSpeed(bps);
                        
                        // Calculate time remaining
                        const remainingBytes = this.uploadStats.totalBytes - bytesActual;
                        if (bps > 0) {
                            const secondsRemaining = Math.ceil(remainingBytes / bps);
                            this.timeRemaining = this.formatTimeRemaining(secondsRemaining);
                        }
                    }
                },
                formatSpeed(bytesPerSecond) {
                    if (bytesPerSecond < 1024) {
                        return `${bytesPerSecond.toFixed(1)} B/s`;
                    } else if (bytesPerSecond < 1024 * 1024) {
                        return `${(bytesPerSecond / 1024).toFixed(1)} KB/s`;
                    } else {
                        return `${(bytesPerSecond / 1024 / 1024).toFixed(2)} MB/s`;
                    }
                },
                formatTimeRemaining(seconds) {
                    if (seconds < 60) {
                        return `${seconds} seconds remaining`;
                    } else if (seconds < 3600) {
                        return `${Math.floor(seconds / 60)} minutes ${seconds % 60} seconds remaining`;
                    } else {
                        return `${Math.floor(seconds / 3600)} hours ${Math.floor((seconds % 3600) / 60)} minutes remaining`;
                    }
                },
                formatFileSize(bytes) {
                    if (bytes < 1024) {
                        return bytes + ' bytes';
                    } else if (bytes < 1024 * 1024) {
                        return (bytes / 1024).toFixed(1) + ' KB';
                    } else if (bytes < 1024 * 1024 * 1024) {
                        return (bytes / 1024 / 1024).toFixed(1) + ' MB';
                    } else {
                        return (bytes / 1024 / 1024 / 1024).toFixed(2) + ' GB';
                    }
                },
                copyDownloadLink() {
                    this.$refs.downloadInput.select();
                    document.execCommand('copy');
                    
                    // Show copy feedback
                    const originalText = event.target.innerText;
                    event.target.innerText = 'Copied!';
                    setTimeout(() => {
                        event.target.innerText = originalText;
                    }, 2000);
                }
            }
        }).mount('#app')

        // Configure NProgress
        NProgress.configure({ 
            showSpinner: false,
            minimum: 0.08,
            easing: 'ease',
            speed: 500 
        });
    </script>
</body>
</html>