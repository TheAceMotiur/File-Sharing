<?php
require_once __DIR__ . '/config.php';
session_start();
require_once __DIR__ . '/vendor/autoload.php';
use Spatie\Dropbox\Client as DropboxClient;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        // Handling regular file upload
        if (isset($_FILES['file'])) {
            $file = $_FILES['file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File upload failed');
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

            // Updated file size to 2GB (2 * 1024 * 1024 * 1024 bytes)
            if ($file['size'] > 2 * 1024 * 1024 * 1024) {
                throw new Exception('File size exceeds 2 GB limit');
            }

            // Complete regular upload process
            completeUpload($file['tmp_name'], $file['name'], $file['size']);
        } 
        // Handling chunked upload completion
        elseif (isset($_POST['fileId']) && isset($_POST['fileName']) && isset($_POST['totalSize']) && isset($_POST['chunksComplete'])) {
            $tempDir = __DIR__ . '/temp/' . $_POST['fileId'];
            $fileName = $_POST['fileName'];
            $totalSize = (int)$_POST['totalSize'];
            
            // Verify all chunks are complete
            if ($_POST['chunksComplete'] !== "true") {
                throw new Exception('Not all chunks have been uploaded');
            }
            
            // Combine chunks into a single file
            $outputFile = $tempDir . '/complete_' . $fileName;
            $chunks = glob($tempDir . '/chunk_*');
            sort($chunks, SORT_NATURAL); // Sort chunks in natural order
            
            if (count($chunks) === 0) {
                throw new Exception('No chunks found');
            }
            
            // Create the final file
            $finalFile = fopen($outputFile, 'wb');
            foreach ($chunks as $chunk) {
                $chunkContent = file_get_contents($chunk);
                fwrite($finalFile, $chunkContent);
                unlink($chunk); // Remove the chunk after appending
            }
            fclose($finalFile);
            
            // Process the complete file
            completeUpload($outputFile, $fileName, $totalSize, true);
            
            // Clean up temp directory
            rmdir($tempDir);
        }
        // Handling chunk upload - moved to a separate endpoint (chunk_upload.php)
        else {
            throw new Exception('No file uploaded');
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}

// Function to complete the upload process
function completeUpload($filePath, $fileName, $fileSize, $isTemporary = false) {
    global $db;
    
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
    $dropboxPath = "/{$fileId}/{$fileName}";
    $fileContents = file_get_contents($filePath);
    $client->upload($dropboxPath, $fileContents, 'add');
    
    // Delete temporary file if it was created during chunking
    if ($isTemporary) {
        unlink($filePath);
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
    $stmt->execute();

    $downloadLink = "https://" . $_SERVER['HTTP_HOST'] . "/download/" . $fileId;        
    
    echo json_encode([
        'success' => true,
        'downloadLink' => $downloadLink
    ]);
    exit;
}
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
                                    <div class="w-full bg-gray-200 rounded-full h-3">
                                        <div class="bg-blue-600 h-3 rounded-full transition-all duration-150" 
                                             :style="{ width: progress + '%' }">
                                        </div>
                                    </div>
                                    <p class="text-sm font-medium text-gray-600">
                                        Uploading... {{ progress }}%
                                    </p>
                                    <p v-if="chunkStats" class="text-xs text-gray-500">
                                        Chunk {{ chunkStats.current }} of {{ chunkStats.total }}
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
                    chunkStats: null,
                    chunkSize: 2 * 1024 * 1024, // 2MB chunks
                    uploadAbort: null
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
                    
                    try {
                        NProgress.start();
                        
                        // For small files (less than 10MB), use direct upload
                        if (file.size < 10 * 1024 * 1024) {
                            await this.regularUpload(file);
                        } else {
                            // For larger files, use chunked upload
                            await this.chunkedUpload(file);
                        }
                        
                    } catch (error) {
                        console.error('Upload error:', error);
                        alert('Upload failed: ' + error.message);
                    } finally {
                        this.uploading = false;
                        this.chunkStats = null;
                        NProgress.done();
                    }
                },
                async regularUpload(file) {
                    const formData = new FormData();
                    formData.append('file', file);
                    
                    const xhr = new XMLHttpRequest();
                    
                    // Setup progress tracking
                    xhr.upload.addEventListener('progress', (e) => {
                        if (e.lengthComputable) {
                            this.progress = Math.round((e.loaded * 100) / e.total);
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
                },
                async chunkedUpload(file) {
                    // Generate a unique ID for this file upload session
                    const fileId = Date.now().toString(36) + Math.random().toString(36).substr(2, 5);
                    
                    // Calculate total chunks
                    const totalChunks = Math.ceil(file.size / this.chunkSize);
                    let uploadedChunks = 0;
                    let totalUploaded = 0;
                    
                    // Create a directory for chunks
                    await fetch('chunk_upload.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'init',
                            fileId: fileId,
                            fileName: file.name,
                            totalChunks: totalChunks,
                            fileSize: file.size
                        })
                    });
                    
                    // Upload each chunk
                    for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
                        const start = chunkIndex * this.chunkSize;
                        const end = Math.min(file.size, start + this.chunkSize);
                        const chunk = file.slice(start, end);
                        
                        this.chunkStats = {
                            current: chunkIndex + 1,
                            total: totalChunks
                        };
                        
                        const formData = new FormData();
                        formData.append('chunk', chunk);
                        formData.append('fileId', fileId);
                        formData.append('chunkIndex', chunkIndex);
                        formData.append('fileName', file.name);
                        formData.append('totalChunks', totalChunks);
                        
                        const response = await fetch('chunk_upload.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        if (!response.ok) {
                            throw new Error(`Failed to upload chunk ${chunkIndex + 1}`);
                        }
                        
                        uploadedChunks++;
                        totalUploaded += chunk.size;
                        this.progress = Math.round((totalUploaded * 100) / file.size);
                    }
                    
                    // All chunks uploaded, now finalize
                    const finalFormData = new FormData();
                    finalFormData.append('fileId', fileId);
                    finalFormData.append('fileName', file.name);
                    finalFormData.append('totalSize', file.size);
                    finalFormData.append('chunksComplete', 'true');
                    
                    const finalResponse = await fetch('index.php', {
                        method: 'POST',
                        body: finalFormData
                    });
                    
                    if (!finalResponse.ok) {
                        throw new Error('Failed to finalize upload');
                    }
                    
                    const result = await finalResponse.json();
                    
                    if (!result.success) {
                        throw new Error(result.error || 'Upload failed');
                    }
                    
                    this.downloadLink = result.downloadLink;
                    this.showDownloadSection = true;
                },
                copyDownloadLink() {
                    const copyText = this.$refs.downloadInput;
                    const copyBtn = event.target;
                    const originalText = copyBtn.innerHTML;
                    
                    copyText.select();
                    navigator.clipboard.writeText(copyText.value);
                    
                    // Update button text and style
                    copyBtn.innerHTML = `
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Copied</span>
                        </div>
                    `;
                    copyBtn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                    copyBtn.classList.add('bg-green-600', 'hover:bg-green-700');
                    
                    // Reset button after 1 second
                    setTimeout(() => {
                        copyBtn.innerHTML = originalText;
                        copyBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
                        copyBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                    }, 1000);
                }
            }
        }).mount('#app')
    </script>
</body>
</html>
<!-- Add this CSS for better mobile responsiveness -->
<style>
@media (max-width: 576px) {
    .input-group {
        flex-direction: column;
    }
    .input-group .form-control {
        border-radius: .25rem !important;
        margin-bottom: 10px;
    }
    .input-group .btn {
        border-radius: .25rem !important;
        width: 100%;
    }
}
</style>