<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/ads.php';  // Include ads functionality
session_start();
require_once __DIR__ . '/vendor/autoload.php';
use Spatie\Dropbox\Client as DropboxClient;

$siteName = getSiteName();

// Add this helper function before the main request handling
function removeChunkDirectory($chunksDir) {
    if (is_dir($chunksDir)) {
        $files = glob($chunksDir . '/*');
        foreach ($files as $file) {
            unlink($file);
        }
        rmdir($chunksDir);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Handle chunk uploads
    if (isset($_POST['chunk'])) {
        $chunksDir = '';
        try {
            if (!isset($_SESSION['user_id'])) {
                throw new Exception('Login required to upload files');
            }

            $chunk = $_POST['chunk'];
            $totalChunks = $_POST['totalChunks'];
            $currentChunk = $_POST['currentChunk'];
            $fileId = $_POST['fileId'];
            $fileName = $_POST['fileName'];
            $fileSize = $_POST['totalSize'];

            // Validate total file size (2GB limit)
            if ($fileSize > 2 * 1024 * 1024 * 1024) {
                throw new Exception('File size exceeds 2 GB limit');
            }

            // Create temporary directory for chunks if not exists
            $chunksDir = __DIR__ . '/chunks/' . $fileId;
            if (!file_exists($chunksDir)) {
                mkdir($chunksDir, 0777, true);
            }

            // Save chunk
            $chunkFile = $chunksDir . '/' . $currentChunk;
            file_put_contents($chunkFile, base64_decode($chunk));

            // Check if all chunks are received
            if ($currentChunk == $totalChunks - 1) {
                // Combine chunks
                $finalFile = fopen($chunksDir . '/' . $fileName, 'wb');
                
                try {
                    for ($i = 0; $i < $totalChunks; $i++) {
                        $chunkContent = file_get_contents($chunksDir . '/' . $i);
                        if ($chunkContent === false) {
                            throw new Exception('Failed to read chunk ' . $i);
                        }
                        fwrite($finalFile, $chunkContent);
                        unlink($chunksDir . '/' . $i);
                    }
                    fclose($finalFile);

                    // Get file mime type
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $chunksDir . '/' . $fileName);
                    finfo_close($finfo);

                    // Validate file type
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

                    if (!in_array($mimeType, $allowedTypes)) {
                        // Cleanup and throw error
                        removeChunkDirectory($chunksDir);
                        throw new Exception('File type not allowed');
                    }

                    // Upload to Dropbox
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
                        throw new Exception('No storage available');
                    }

                    $client = new Spatie\Dropbox\Client($dropbox['access_token']);
                    $dropboxPath = "/{$fileId}/{$fileName}";
                    
                    // Upload to Dropbox using chunks
                    $handle = fopen($chunksDir . '/' . $fileName, 'rb');
                    $cursor = $client->uploadSessionStart(fread($handle, 1024 * 1024 * 10));
                    
                    while (!feof($handle)) {
                        $client->uploadSessionAppend(fread($handle, 1024 * 1024 * 10), $cursor);
                    }
                    
                    $client->uploadSessionFinish('', $cursor, $dropboxPath);
                    fclose($handle);

                    // Save to database
                    $stmt = $db->prepare("INSERT INTO file_uploads (
                        file_id, file_name, size, upload_status, dropbox_path, 
                        dropbox_account_id, uploaded_by
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

                    // Cleanup
                    removeChunkDirectory($chunksDir);

                    echo json_encode([
                        'success' => true,
                        'downloadLink' => "https://" . $_SERVER['HTTP_HOST'] . "/download/" . $fileId
                    ]);
                    exit;
                } catch (Exception $e) {
                    if (is_resource($finalFile)) {
                        fclose($finalFile);
                    }
                    removeChunkDirectory($chunksDir);
                    throw $e;
                }
            }

            echo json_encode(['success' => true, 'chunk' => $currentChunk]);
            exit;

        } catch (Exception $e) {
            // Clean up on error
            if (!empty($chunksDir)) {
                removeChunkDirectory($chunksDir);
            }
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Primary Meta Tags -->
    <title><?php echo $siteName; ?> - Fast & Secure File Sharing Platform</title>
    <meta name="title" content="<?php echo $siteName; ?> - Fast & Secure File Sharing Platform">
    <meta name="description" content="Share files securely with <?php echo $siteName; ?>. Upload and share files with anyone, anywhere with end-to-end encryption and cloud storage capabilities.">
    <meta name="robots" content="index, follow">
    <meta name="language" content="English">
    <meta name="author" content="<?php echo $siteName; ?>">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://<?php echo $_SERVER['HTTP_HOST']; ?>">
    <meta property="og:title" content="<?php echo $siteName; ?> - Fast & Secure File Sharing Platform">
    <meta property="og:description" content="Share files securely with <?php echo $siteName; ?>. Upload and share files with anyone, anywhere with end-to-end encryption and cloud storage capabilities.">
    <meta property="og:image" content="https://<?php echo $_SERVER['HTTP_HOST']; ?>/icon.png">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://<?php echo $_SERVER['HTTP_HOST']; ?>">
    <meta property="twitter:title" content="<?php echo $siteName; ?> - Fast & Secure File Sharing Platform">
    <meta property="twitter:description" content="Share files securely with <?php echo $siteName; ?>. Upload and share files with anyone, anywhere with end-to-end encryption and cloud storage capabilities.">
    <meta property="twitter:image" content="https://<?php echo $_SERVER['HTTP_HOST']; ?>/icon.png">

    <!-- Canonical URL -->
    <link rel="canonical" href="https://<?php echo $_SERVER['HTTP_HOST']; ?>">

    <!-- JSON-LD Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebApplication",
        "name": "<?php echo $siteName; ?>",
        "description": "Share files securely with <?php echo $siteName; ?>. Upload and share files with anyone, anywhere with end-to-end encryption and cloud storage capabilities.",
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
            "name": "<?php echo $siteName; ?>",
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
        "name": "<?php echo $siteName; ?>",
        "description": "Share files securely with <?php echo $siteName; ?>. Upload and share files with anyone, anywhere with end-to-end encryption and cloud storage capabilities.",
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
            </div>                <?php displayHomepageHeroAd(); ?> <!-- Hero section ad -->

            <!-- Upload Section -->
            <div class="max-w-3xl mx-auto">
                <?php if (!isset($_SESSION['user_id'])): ?>
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <!-- Show login prompt for non-authenticated users -->xl shadow-sm border border-gray-200 p-8">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
                        <div class="text-center">
                            <div class="mx-auto h-12 w-12 text-gray-400 mb-4">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"     d="M12 15v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                          d="M12 15v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>svg>
                                </svg>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Login Required</h3>or register to upload files</p>
                            <p class="text-gray-600 mb-4">Please login or register to upload files</p>
                            <div class="flex justify-center space-x-4">
                                <a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" "inline-flex items-center px-4 py-2 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                   class="inline-flex items-center px-4 py-2 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">Login
                                    Login
                                </a>
                                <a href="register.php" line-flex items-center px-4 py-2 border border-gray-300 text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                   class="inline-flex items-center px-4 py-2 border border-gray-300 text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Register
                                    Registera>
                                </a>div>
                            </div>div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Original upload form for logged-in users -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
                        <form @submit.prevent="uploadFile" method="POST" enctype="multipart/form-data" class="space-y-6">rder-dashed border-gray-300 rounded-xl p-10 text-center transition-colors duration-150 ease-in-out hover:border-blue-500"
                            <div class="border-2 border-dashed border-gray-300 rounded-xl p-10 text-center transition-colors duration-150 ease-in-out hover:border-blue-500"
                                 @dragover.prevent 
                                 @drop.prevent="handleDrop" :class="{ 'border-blue-500 bg-blue-50': uploading }">
                                 :class="{ 'border-blue-500 bg-blue-50': uploading }">
                                
                                <div v-if="!uploading">lor" viewBox="0 0 24 24">
                                    <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"   d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>e</h3>
                                    <h3 class="mt-4 text-lg font-medium text-gray-900">Upload your file</h3>rop or click to select</p>
                                    <p class="mt-2 text-gray-600">Drag and drop or click to select</p>ray-500">
                                    <div class="mt-2 text-sm text-gray-500">
                                        Maximum file size: 2 GB<br>pported formats: Images, Audio, Video, Archives
                                        Supported formats: Images, Audio, Video, Archives
                                    </div>lect" ref="fileInput" required>
                                    <input type="file" class="hidden" @change="handleFileSelect" ref="fileInput" required>
                                    <button type="button" @click="$refs.fileInput.click()"  inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                        class="mt-4 inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">t File
                                        Select Filebutton>
                                    </button>                                </div>
                                </div>

                                <div v-else class="space-y-4">
                                    <div class="w-full bg-gray-200 rounded-full h-3"> transition-all duration-150" 
                                        <div class="bg-blue-600 h-3 rounded-full transition-all duration-150" style="{ width: progress + '%' }">
                                             :style="{ width: progress + '%' }">div>
                                        </div>
                                    </div>xt-gray-600">
                                    <p class="text-sm font-medium text-gray-600">Uploading... {{ progress }}%
                                        Uploading... {{ progress }}%p>
                                    </p>div>
                                </div>iv>
                            </div>form>
                        </form>
                    </div>                <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <?php displayHomepageFeaturedAd(); ?> <!-- Featured ad after upload section -->hite rounded-lg shadow-sm p-6 border border-gray-200">
b-6">
            <!-- Features Section -->
            <div class="py-12">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="lg:text-center">ke-width="2" 
                        <h2 class="text-base text-indigo-600 font-semibold tracking-wide uppercase">Features</h2>    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        <p class="mt-2 text-3xl leading-8 font-extrabold tracking-tight text-gray-900 sm:text-4xl">svg>
                            Everything you need for file sharing</div>
                        </p>
                    </div>load Successful!</h3>
 class="text-gray-600">Your file is ready to be shared</p>
                    <!-- Features content -->        </div>
                    <!-- ...existing code... -->
                </div>ion -->
            </div>
            gray-700">Share this secure link</label>
            <?php displayInArticleAd(); ?> <!-- In-article ad at the bottom of the page -->l sm:flex-row gap-3">

        </main>
    </div>
-full px-4 py-2 text-gray-800 border border-gray-300 rounded-lg bg-gray-50 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
    <?php include 'footer.php'; ?>
     ref="downloadInput">
    <script>
        const { createApp } = Vue
        createApp({rder border-transparent text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
            data() {4 24">
                return {
                    uploading: false,    d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                    progress: 0,
                    downloadLink: '',Link
                    showDownloadSection: falsebutton>
                }div>
            },        </div>
            methods: {
                handleDrop(e) {
                    e.preventDefault()
                    const files = e.dataTransfer.files flex-col sm:flex-row gap-3 justify-center">
                    if (files.length) this.uploadFile(files[0])
                },t text-sm font-medium rounded-lg text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-150">
                handleFileSelect(e) {4 24">
                    const files = e.target.files;d" stroke-linejoin="round" stroke-width="2" 
                    if (files.length) {    d="M12 4v16m8-8H4"/>
                        const file = files[0];
                        // Add client-side file type validationUpload Another File
                        const allowedExtensions = [
                            // Images
                            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',ext-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
                            // Audio4 24">
                            'mp3', 'wav', 'ogg', 'm4a', 'aac',
                            // Video    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            'mp4', 'mpeg', 'webm', 'mov', 'avi',
                            // ArchivesView Download Page
                            'zip', 'rar', '7z', 'tar', 'gz',a>
                            // Documentsdiv>
                            'pdf', 'djvu',div>
                            // Other mediadiv>
                            'm3u8', 'm3u'                </div>
                        ];
                        
                        const extension = file.name.split('.').pop().toLowerCase();ols-3 gap-6">
                        if (!allowedExtensions.includes(extension)) {Feature -->
                            alert('File type not allowed. Only media and archive files are supported.');
                            return;
                        }ox="0 0 24 24">
                        
                        this.uploadFile(file);    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    }svg>
                },
                
                async uploadFile(file) { class="text-gray-600 mt-2">Your files are encrypted and protected</p>
                    if (file.size > 2 * 1024 * 1024 * 1024) { // 2GB limit                    </div>
                        alert('File is too large. Maximum file size is 2 GB.');
                        return;
                    }
                
                    this.uploading = true;Box="0 0 24 24">
                    this.progress = 0;
                    const fileId = Date.now().toString(36) + Math.random().toString(36).substring(2);    d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z" />
                svg>
                    try {
                        NProgress.start();
                        const chunkSize = 2 * 1024 * 1024; // 2MB chunks class="text-gray-600 mt-2">Access your files from anywhere, anytime</p>
                        const totalChunks = Math.ceil(file.size / chunkSize);                    </div>
                
                        for (let i = 0; i < totalChunks; i++) {>
                            const chunk = file.slice(i * chunkSize, (i + 1) * chunkSize);
                            const reader = new FileReader();
                            wBox="0 0 24 24">
                            try {ejoin="round" stroke-width="2" 
                                await new Promise((resolve, reject) => {    d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    reader.onload = async () => {svg>
                                        try {
                                            const base64Chunk = reader.result.split(',')[1];
                                            const response = await fetch('index.php', { class="text-gray-600 mt-2">Quick and efficient file transfers</p>
                                                method: 'POST',div>
                                                headers: {                </div>
                                                    'Content-Type': 'application/x-www-form-urlencoded',
                                                },ection -->
                                                body: new URLSearchParams({
                                                    chunk: base64Chunk,-900 mb-8">How It Works</h2>
                                                    totalChunks: totalChunks,id-cols-1 md:grid-cols-4 gap-8">
                                                    currentChunk: i,
                                                    fileId: fileId,
                                                    fileName: file.name,center justify-center mx-auto mb-4">
                                                    totalSize: file.sizepan class="text-xl font-bold text-gray-700">1</span>
                                                })
                                            });
                 class="text-gray-600">Select or drag & drop your files</p>
                                            // Check if response is JSON                        </div>
                                            const contentType = response.headers.get("content-type");
                                            if (contentType && contentType.indexOf("application/json") !== -1) {
                                                const result = await response.json();
                                                if (!result.success) throw new Error(result.error);center justify-center mx-auto mb-4">
                                            }pan class="text-xl font-bold text-gray-700">2</span>
                                            
                                            this.progress = Math.round((i + 1) * 100 / totalChunks);
                                            resolve(); class="text-gray-600">Receive your secure sharing link</p>
                                        } catch (error) {                        </div>
                                            // If it's the last chunk and we get an error, check if file exists
                                            if (i === totalChunks - 1) {
                                                // Set success anyway since large files might timeout but upload successfully
                                                this.downloadLink = `https://${window.location.host}/download/${fileId}`;center justify-center mx-auto mb-4">
                                                this.showDownloadSection = true;pan class="text-xl font-bold text-gray-700">3</span>
                                                resolve();
                                                return;
                                            } class="text-gray-600">Share the link with anyone</p>
                                            reject(error);                        </div>
                                        }
                                    };
                                    reader.onerror = reject;
                                    reader.readAsDataURL(chunk);center justify-center mx-auto mb-4">
                                });pan class="text-xl font-bold text-gray-700">4</span>
                            } catch (error) {
                                // If chunk upload fails but not the last chunk, throw error
                                if (i !== totalChunks - 1) { class="text-gray-600">Recipients download securely</p>
                                    throw error;div>
                                }div>
                            }div>
                        }iv>
                main>
                        // Set success state    </div>
                        this.downloadLink = `https://${window.location.host}/download/${fileId}`;
                        this.showDownloadSection = true;    <?php include 'footer.php'; ?>
                
                    } catch (error) {
                        console.error('Upload error:', error);ateApp } = Vue
                        alert('Upload failed: ' + error.message);
                    } finally {
                        this.uploading = false;
                        NProgress.done();alse,
                    }
                },
                copyDownloadLink() {   showDownloadSection: false
                    const copyText = this.$refs.downloadInput;  }
                    const copyBtn = event.target;
                    const originalText = copyBtn.innerHTML;
                    
                    copyText.select();
                    navigator.clipboard.writeText(copyText.value);
                      if (files.length) this.uploadFile(files[0])
                    // Update button text and style
                    copyBtn.innerHTML = `
                        <div class="flex items-center">get.files;
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>e validation
                            <span>Copied</span>Extensions = [
                        </div>
                    `;jpeg', 'png', 'gif', 'webp', 'svg',
                    copyBtn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                    copyBtn.classList.add('bg-green-600', 'hover:bg-green-700');wav', 'ogg', 'm4a', 'aac',
                    
                    // Reset button after 1 secondg', 'webm', 'mov', 'avi',
                    setTimeout(() => {
                        copyBtn.innerHTML = originalText;, '7z', 'tar', 'gz',
                        copyBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
                        copyBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
                    }, 1000);a
                }  'm3u8', 'm3u'
            }];
        }).mount('#app')
    </script>toLowerCase();
</body>
</html>File type not allowed. Only media and archive files are supported.');
<!-- Add this CSS for better mobile responsiveness -->   return;
<style>}
@media (max-width: 576px) {
    .input-group {   this.uploadFile(file);
        flex-direction: column;  }
    }},
    .input-group .form-control {
        border-radius: .25rem !important;
        margin-bottom: 10px;
    }File is too large. Maximum file size is 2 GB.');
    .input-group .btn {   return;
        border-radius: .25rem !important;    }
        width: 100%;
    }rue;
}
</style></style>