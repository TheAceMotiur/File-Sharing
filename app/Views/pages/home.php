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
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo SITE_URL; ?>">
    <meta property="og:title" content="<?php echo $siteName; ?> - Fast & Secure File Sharing Platform">
    <meta property="og:description" content="Share files securely with <?php echo $siteName; ?>. Upload and share files with anyone, anywhere with end-to-end encryption and cloud storage capabilities.">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?php echo SITE_URL; ?>">
    <meta property="twitter:title" content="<?php echo $siteName; ?> - Fast & Secure File Sharing Platform">
    
    <link rel="icon" type="image/png" href="/icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include __DIR__ . '/../partials/header.php'; ?>

    <!-- Hero Section -->
    <section class="bg-gradient-to-r from-blue-500 to-purple-600 text-white py-20">
        <div class="container mx-auto px-4 text-center">
            <h1 class="text-5xl font-bold mb-6">Fast & Secure File Sharing</h1>
            <p class="text-xl mb-8">Upload, share, and manage your files with ease</p>
            <div class="space-x-4">
                <a href="/register" class="bg-white text-blue-600 px-8 py-3 rounded-lg font-bold hover:bg-gray-100 inline-block">
                    Get Started Free
                </a>
                <a href="/login" class="bg-transparent border-2 border-white text-white px-8 py-3 rounded-lg font-bold hover:bg-white hover:text-blue-600 inline-block">
                    Login
                </a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-16">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-12">Why Choose <?php echo $siteName; ?>?</h2>
            
            <div class="grid md:grid-cols-3 gap-8">
                <div class="bg-white p-6 rounded-lg shadow-md text-center">
                    <div class="text-blue-500 text-5xl mb-4">
                        <i class="fas fa-lock"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Secure & Private</h3>
                    <p class="text-gray-600">Your files are encrypted and protected with enterprise-grade security.</p>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow-md text-center">
                    <div class="text-blue-500 text-5xl mb-4">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Lightning Fast</h3>
                    <p class="text-gray-600">Upload and download files at blazing speeds with our optimized infrastructure.</p>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow-md text-center">
                    <div class="text-blue-500 text-5xl mb-4">
                        <i class="fas fa-cloud"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Cloud Storage</h3>
                    <p class="text-gray-600">Store your files in the cloud and access them from anywhere, anytime.</p>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow-md text-center">
                    <div class="text-blue-500 text-5xl mb-4">
                        <i class="fas fa-share-alt"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Easy Sharing</h3>
                    <p class="text-gray-600">Share files with anyone using simple, shareable links.</p>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow-md text-center">
                    <div class="text-blue-500 text-5xl mb-4">
                        <i class="fas fa-folder"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Organize Files</h3>
                    <p class="text-gray-600">Keep your files organized with folders and custom naming.</p>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow-md text-center">
                    <div class="text-blue-500 text-5xl mb-4">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Mobile Friendly</h3>
                    <p class="text-gray-600">Access and manage your files from any device, anywhere.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="bg-gray-100 py-16">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-12">How It Works</h2>
            
            <div class="grid md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div class="bg-blue-500 text-white rounded-full w-16 h-16 flex items-center justify-center text-2xl font-bold mx-auto mb-4">
                        1
                    </div>
                    <h3 class="text-xl font-bold mb-2">Upload Your Files</h3>
                    <p class="text-gray-600">Simply drag and drop or select files to upload to your secure storage.</p>
                </div>
                
                <div class="text-center">
                    <div class="bg-blue-500 text-white rounded-full w-16 h-16 flex items-center justify-center text-2xl font-bold mx-auto mb-4">
                        2
                    </div>
                    <h3 class="text-xl font-bold mb-2">Get Shareable Links</h3>
                    <p class="text-gray-600">Receive instant shareable links for your uploaded files.</p>
                </div>
                
                <div class="text-center">
                    <div class="bg-blue-500 text-white rounded-full w-16 h-16 flex items-center justify-center text-2xl font-bold mx-auto mb-4">
                        3
                    </div>
                    <h3 class="text-xl font-bold mb-2">Share & Download</h3>
                    <p class="text-gray-600">Share links with anyone and let them download files instantly.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="bg-blue-600 text-white py-16">
        <div class="container mx-auto px-4 text-center">
            <h2 class="text-3xl font-bold mb-4">Ready to Get Started?</h2>
            <p class="text-xl mb-8">Join thousands of users who trust <?php echo $siteName; ?> for their file sharing needs.</p>
            <a href="/register" class="bg-white text-blue-600 px-8 py-3 rounded-lg font-bold hover:bg-gray-100 inline-block">
                Create Free Account
            </a>
        </div>
    </section>

    <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
