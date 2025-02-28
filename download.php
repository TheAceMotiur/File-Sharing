<?php
require_once __DIR__ . '/config.php';
session_start();
require_once __DIR__ . '/vendor/autoload.php';
use Spatie\Dropbox\Client as DropboxClient;

function getFileFromCache($fileId) {
    $cacheDir = __DIR__ . '/cache';
    $cachePath = $cacheDir . '/' . $fileId;
    
    if (!file_exists($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    // Check if file exists in cache and is valid
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT cache_path, last_cached FROM file_downloads WHERE file_id = ?");
    $stmt->bind_param("s", $fileId);
    $stmt->execute();
    $cache = $stmt->get_result()->fetch_assoc();
    
    if ($cache && $cache['last_cached']) {
        $cacheAge = time() - strtotime($cache['last_cached']);
        if ($cacheAge <= 7 * 24 * 3600) { // 7 days in seconds
            return $cache['cache_path'];
        }
    }
    return null;
}

function incrementDownloadCount($fileId) {
    $db = getDBConnection();
    $date = date('Y-m-d');
    
    // Update or insert download count
    $stmt = $db->prepare("INSERT INTO file_downloads (file_id, download_count) 
                         VALUES (?, 1) 
                         ON DUPLICATE KEY UPDATE 
                         download_count = download_count + 1");
    $stmt->bind_param("s", $fileId);
    $stmt->execute();
    
    // Get current count
    $stmt = $db->prepare("SELECT download_count FROM file_downloads WHERE file_id = ?");
    $stmt->bind_param("s", $fileId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['download_count'];
}

// Function to add watermark to file name
function addWatermark($fileName) {
    $info = pathinfo($fileName);
    return $info['filename'] . '-[FreeNetly.COM].' . $info['extension'];
}

function isImageFile($fileName) {
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    return in_array($extension, $imageExtensions);
}

try {
    $db = getDBConnection();

    // Validate file ID
    $fileId = $_GET['id'] ?? '';

    // Validate file ID first
    if (empty($fileId)) {
        $error = "Invalid file ID";
        $statusCode = 400;
    } else {
        try {
            // Get file info
            $stmt = $db->prepare("SELECT * FROM file_uploads WHERE file_id = ? AND upload_status = 'completed' AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)");
            $stmt->bind_param("s", $fileId);
            $stmt->execute();
            $file = $stmt->get_result()->fetch_assoc();

            if (!$file) {
                $error = "File not found or has expired. This may be because the file was removed by the owner, exceeded its retention period, or the download link is invalid. Please contact the person who shared the file with you to request a new download link.";
                $statusCode = 404;
            }
        } catch (Exception $e) {
            $error = "Error retrieving file";
            $statusCode = 500;
        }
    }

    // Set HTTP status code if there's an error
    if (isset($statusCode)) {
        http_response_code($statusCode);
    }

    // Check if this is a download request
    if (isset($_GET['download'])) {
        try {
            // Check cache first
            $cachePath = getFileFromCache($fileId);
            
            if ($cachePath && file_exists($cachePath)) {
                // Serve from cache
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . addWatermark(basename($file['file_name'])) . '"');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                readfile($cachePath);
                exit;
            }
            
            // Track download count
            $downloadCount = incrementDownloadCount($fileId);
            
            // Get Dropbox credentials and file info
            $stmt = $db->prepare("
                SELECT da.access_token 
                FROM dropbox_accounts da
                INNER JOIN file_uploads fu ON fu.dropbox_account_id = da.id
                WHERE fu.file_id = ?
            ");
            $stmt->bind_param("s", $fileId);
            $stmt->execute();
            $dropbox = $stmt->get_result()->fetch_assoc();

            if (!$dropbox) {
                throw new Exception("Could not find associated Dropbox account");
            }
            $stmt = $db->prepare("SELECT file_name FROM file_uploads WHERE file_id = ?");
            $stmt->bind_param("s", $fileId);
            $stmt->execute();
            $file = $stmt->get_result()->fetch_assoc();

            if (!$file) {
                throw new Exception("File not found or has expired. This may be because the file was removed by the owner, exceeded its retention period, or the download link is invalid. Please contact the person who shared the file with you to request a new download link.");
            }

            // Initialize Dropbox client
            $client = new DropboxClient($dropbox['access_token']);
            
            // Get file from Dropbox
            $stream = $client->download("/{$fileId}/{$file['file_name']}");
            
            // If downloaded more than 2 times, cache it
            if ($downloadCount >= 2) {
                $cacheDir = __DIR__ . '/cache';
                $cachePath = $cacheDir . '/' . $fileId;
                
                // Save to cache
                file_put_contents($cachePath, stream_get_contents($stream));
                rewind($stream); // Reset stream pointer
                
                // Update cache record
                $stmt = $db->prepare("UPDATE file_downloads 
                                    SET cache_path = ?, last_cached = CURRENT_TIMESTAMP 
                                    WHERE file_id = ?");
                $stmt->bind_param("ss", $cachePath, $fileId);
                $stmt->execute();
            }
            
            // Set headers for file download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . addWatermark(basename($file['file_name'])) . '"');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            
            // Stream file to user
            fpassthru($stream);
            exit;
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    // Get file info
    $stmt = $db->prepare("SELECT * FROM file_uploads WHERE file_id = ? AND upload_status = 'completed'");
    $stmt->bind_param("s", $fileId);
    $stmt->execute();
    $file = $stmt->get_result()->fetch_assoc();

    if (!$file) {
        throw new Exception("File not found or has expired. This may be because the file was removed by the owner, exceeded its retention period, or the download link is invalid. Please contact the person who shared the file with you to request a new download link.");
    }

    // Get Dropbox temporary link
    $stmt = $db->prepare("UPDATE file_uploads SET 
        last_download_at = CURRENT_TIMESTAMP,
        expires_at = CURRENT_TIMESTAMP + INTERVAL 180 DAY 
        WHERE file_id = ?");
    $stmt->bind_param("s", $fileId);
    $stmt->execute();

    // Instead of getting Dropbox temporary link, create direct download URL
    $directDownloadUrl = "https://" . $_SERVER['HTTP_HOST'] . "/download/" . $fileId . "/download";
    $downloadUrl = $directDownloadUrl; // Use direct download URL instead of Dropbox temporary link

    $fileName = htmlspecialchars($file['file_name']);
    $fileSize = number_format($file['size'] / 1024 / 1024, 2);

    // Handle preview requests directly
    if (isset($_GET['preview']) && isImageFile($fileName)) {
        header('Location: ' . $directDownloadUrl);
        exit;
    }

} catch (Exception $e) {
    // Set error variable instead of using die()
    $error = $e->getMessage();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <?php
    // Generate SEO-friendly title and description
    $pageTitle = "Download " . $fileName . " - FreeNetly Secure File Sharing";
    $pageDescription = "Download " . $fileName . " securely via FreeNetly. File size: " . $fileSize . "MB. Our platform ensures safe and encrypted file transfers with cloud storage capabilities.";
    $canonicalUrl = "https://" . $_SERVER['HTTP_HOST'] . "/download/" . $fileId;
    
    // Determine if file is an image and set preview URL
    $isImage = isImageFile($fileName);
    $directDownloadUrl = "https://" . $_SERVER['HTTP_HOST'] . "/download/" . $fileId . "/download";
    $previewImage = $isImage ? $directDownloadUrl : "https://" . $_SERVER['HTTP_HOST'] . "/icon.png";
    ?>
    
    <!-- Primary Meta Tags -->
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl); ?>">
    <meta name="robots" content="noindex, nofollow, max-image-preview:large">
    <meta name="language" content="English">
    <meta name="author" content="FreeNetly">
    <meta name="theme-color" content="#2563eb">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($previewImage); ?>">
    <?php if ($isImage): ?>
    <meta property="og:image:type" content="<?php echo mime_content_type($fileName); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Preview of <?php echo htmlspecialchars($fileName); ?>">
    <?php endif; ?>
    <meta property="og:site_name" content="FreeNetly">

    <!-- Twitter -->
    <meta property="twitter:card" content="<?php echo $isImage ? 'summary_large_image' : 'summary'; ?>">
    <meta property="twitter:url" content="<?php echo htmlspecialchars($canonicalUrl); ?>">
    <meta property="twitter:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta property="twitter:description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta property="twitter:image" content="<?php echo htmlspecialchars($previewImage); ?>">
    <?php if ($isImage): ?>
    <meta name="twitter:image:alt" content="Preview of <?php echo htmlspecialchars($fileName); ?>">
    <?php endif; ?>

    <!-- Additional SEO Meta Tags -->
    <meta name="format-detection" content="telephone=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="application-name" content="FreeNetly">
    <meta name="apple-mobile-web-app-title" content="FreeNetly">

    <!-- JSON-LD Structured Data with more details -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "DownloadAction",
        "name": "<?php echo htmlspecialchars($fileName); ?>",
        "description": "<?php echo htmlspecialchars($pageDescription); ?>",
        "expectsAcceptanceOf": {
            "@type": "DigitalDocument",
            "name": "<?php echo htmlspecialchars($fileName); ?>",
            "fileFormat": "<?php echo pathinfo($fileName, PATHINFO_EXTENSION); ?>",
            "dateModified": "<?php echo $file['last_download_at'] ?? ''; ?>",
            "contentSize": "<?php echo $fileSize; ?> MB"
        },
        "publisher": {
            "@type": "Organization",
            "name": "FreeNetly",
            "url": "https://<?php echo $_SERVER['HTTP_HOST']; ?>"
        }
    }
    </script>

    <!-- Breadcrumb Structured Data with more details -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [{
            "@type": "ListItem",
            "position": 1,
            "name": "FreeNetly",
            "item": "https://<?php echo $_SERVER['HTTP_HOST']; ?>"
        },
        {
            "@type": "ListItem",
            "position": 2,
            "name": "Download <?php echo htmlspecialchars($fileName); ?>",
            "item": "<?php echo htmlspecialchars($canonicalUrl); ?>"
        }]
    }
    </script>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/icon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/icon.png">

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/js/report.js"></script>
    <script src="https://base64-encoder.com/wpsafelink.js"></script>
</head>
<body class="bg-gray-50">
    <?php include 'header.php'; ?>
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-lg p-8 max-w-md w-full">
            <?php if (isset($error)): ?>
                <!-- Error/Not Found Section -->
                <div class="text-center mb-8">
                    <div class="mx-auto h-12 w-12 text-red-400 mb-4">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="max-w-sm mx-auto">
                        <h2 class="text-2xl font-bold text-gray-900 mb-3">Error</h2>
                        <p class="text-red-600 font-semibold mb-6"><?php echo htmlspecialchars($error); ?></p>
                        <div class="flex justify-center">
                            <a href="/" 
                               class="inline-flex items-center px-4 py-2 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                                Return to Homepage
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- File Info Section -->
                <div class="text-center mb-8">
                    <div class="mx-auto h-12 w-12 text-gray-400 mb-4">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3v-13" />
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Ready to Download</h2>
                    
                    <!-- Add image preview for image files -->
                    <?php if (isImageFile($fileName)): ?>
                        <div class="mt-4 mb-4">
                            <p class="text-sm text-gray-600 mb-2">Preview:</p>
                            <div class="relative w-full h-48 bg-gray-100 rounded-lg overflow-hidden">
                                <img src="<?php echo htmlspecialchars($directDownloadUrl); ?>" 
                                     alt="<?php echo htmlspecialchars($fileName); ?>"
                                     class="object-contain w-full h-full"
                                     onerror="this.style.display='none';this.nextElementSibling.style.display='block';">
                                <div class="hidden absolute inset-0 flex items-center justify-center text-gray-500">
                                    Unable to load preview
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="bg-gray-50 rounded-lg p-4 mb-6">
                        <p class="text-sm text-gray-600 mb-1">Filename:</p>
                        <p class="font-medium text-gray-900"><?php echo $fileName; ?></p>
                        <p class="text-sm text-gray-500 mt-2">Size: <?php echo $fileSize; ?> MB</p>
                    </div>

                </div>

                <!-- Action Buttons -->
                <div class="space-y-4">
                    <!-- QR Code -->
                    <div class="flex flex-col items-center justify-center mb-4">
                        <p class="text-sm text-gray-600 mb-2">Scan to download</p>
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=<?php 
                            echo urlencode("https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); 
                        ?>" 
                             alt="QR Code"
                             class="border p-2 rounded-lg shadow-sm">
                    </div>
                
                    <!-- Download Button -->
                    <div class="flex justify-center">
                        <a href="https://freenetly.com/wait.php?link=https://freenetly.com/download/<?php echo urlencode($fileId); ?>/download"
                           class="w-full inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out">
                            <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Download File
                        </a>
                    </div>

                    <!-- Copy Link Button -->
                    <div class="flex justify-center mt-4">
                        <button onclick="copyDownloadLink()"
                                class="w-full inline-flex items-center justify-center px-6 py-3 border border-gray-300 shadow-sm text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out">
                            <svg class="mr-2 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                            </svg>
                            Copy Download Link
                        </button>
                    </div>

                    <script>
                    function copyDownloadLink() {
                        const downloadLink = 'https://<?php echo $_SERVER['HTTP_HOST']; ?>/download/<?php echo $fileId; ?>';
                        navigator.clipboard.writeText(downloadLink).then(() => {
                            // Show success message using SweetAlert2 (already included in your page)
                            Swal.fire({
                                title: 'Link Copied!',
                                text: 'The download link has been copied to your clipboard',
                                icon: 'success',
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 3000,
                                timerProgressBar: true
                            });
                        }).catch(() => {
                            // Fallback for browsers that don't support clipboard API
                            const textarea = document.createElement('textarea');
                            textarea.value = downloadLink;
                            document.body.appendChild(textarea);
                            textarea.select();
                            try {
                                document.execCommand('copy');
                                Swal.fire({
                                    title: 'Link Copied!',
                                    text: 'The download link has been copied to your clipboard',
                                    icon: 'success',
                                    toast: true,
                                    position: 'top-end',
                                    showConfirmButton: false,
                                    timer: 3000,
                                    timerProgressBar: true
                                });
                            } catch (err) {
                                Swal.fire({
                                    title: 'Error',
                                    text: 'Could not copy link. Please try again.',
                                    icon: 'error',
                                    toast: true,
                                    position: 'top-end',
                                    showConfirmButton: false,
                                    timer: 3000,
                                    timerProgressBar: true
                                });
                            }
                            document.body.removeChild(textarea);
                        });
                    }
                    </script>

                    <!-- Social Share Buttons -->
                    <div class="mt-6 border-t pt-4">
                        <p class="text-sm text-gray-600 mb-3 text-center">Share this file</p>
                        <div class="flex justify-center space-x-4">
                            <!-- Facebook -->
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" 
                               target="_blank"
                               class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-blue-600 hover:bg-blue-700 transition-colors">
                                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M18.77 7.46H14.5v-1.9c0-.9.6-1.1 1-1.1h3V.5h-4.33C10.24.5 9.5 3.44 9.5 5.32v2.15h-3v4h3v12h5v-12h3.85l.42-4z"/>
                                </svg>
                            </a>
                            <!-- Twitter/X -->
                            <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>&text=<?php echo urlencode('Download ' . $fileName . ' via FreeNetly'); ?>" 
                               target="_blank"
                               class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-black hover:bg-gray-800 transition-colors">
                                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                                </svg>
                            </a>
                            <!-- LinkedIn -->
                            <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" 
                               target="_blank"
                               class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-blue-800 hover:bg-blue-900 transition-colors">
                                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/>
                                </svg>
                            </a>
                            <!-- WhatsApp -->
                            <a href="https://wa.me/?text=<?php echo urlencode('Download ' . $fileName . ' via FreeNetly: ' . 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" 
                               target="_blank"
                               class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-green-500 hover:bg-green-600 transition-colors">
                                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                </svg>
                            </a>
                            <!-- Telegram -->
                            <a href="https://t.me/share/url?url=<?php echo urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>&text=<?php echo urlencode('Download ' . $fileName . ' via FreeNetly'); ?>" 
                               target="_blank"
                               class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-blue-500 hover:bg-blue-600 transition-colors">
                                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M.707 8.475C.275 8.64 0 9.508 0 9.508s.284.867.718 1.03l5.09 1.897 1.986 6.38a1.102 1.102 0 0 0 1.75.527l2.96-2.41a.405.405 0 0 1 .494-.013l5.34 3.87a1.1 1.1 0 0 0 1.046.135 1.1 1.1 0 0 0 .682-.803l3.91-18.795A1.102 1.102 0 0 0 22.5.075L.706 8.475z"/>
                                </svg>
                            </a>
                        </div>
                    </div>

                    <!-- Add uploader info -->
                    <div class="text-sm text-gray-600 italic mb-4">
                        This file was uploaded by a user. 
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="text-blue-600 hover:underline">Login to report file</a>
                        <?php else: ?>
                            <button onclick="reportFile('<?php echo $fileId; ?>', '<?php echo htmlspecialchars($fileName); ?>')"
                                    class="text-yellow-600 hover:underline">
                                Report file
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php include 'footer.php'; ?>
</body>
</html>