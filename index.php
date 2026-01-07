<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/ads.php'; // Include ads functionality

// Redirect logged-in users to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard_new.php');
    exit;
}

$siteName = getSiteName();
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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
            
            <?php displayHomepageHeroAd(); // Display ad below hero section ?>

            <!-- Upload Section -->
            <div class="max-w-3xl mx-auto">
                <!-- Show login prompt for non-authenticated users -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
                    <div class="text-center">
                        <div class="mx-auto h-16 w-16 text-blue-600 mb-4">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-3">Upload & Share Files</h3>
                        <p class="text-gray-600 mb-6">Please login or register to start uploading and managing your files</p>
                        <div class="flex justify-center space-x-4">
                            <a href="login.php?redirect=dashboard_new.php" 
                               class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition-colors">
                                <i class="fas fa-sign-in-alt mr-2"></i>
                                Login
                            </a>
                            <a href="register.php" 
                               class="inline-flex items-center px-6 py-3 border border-gray-300 text-base font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                                <i class="fas fa-user-plus mr-2"></i>
                                Register
                            </a>
                        </div>
                    </div>
                </div>

                <?php displayInArticleAd(); // Display in-article ad ?>

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

                <?php displayHomepageFeaturedAd(); // Display featured ad before How It Works section ?>

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
                
                <?php displayHorizontalAd(); // Display horizontal ad at the bottom ?>
            </div>
        </main>

    <?php include 'footer.php'; ?>
</body>
</html>