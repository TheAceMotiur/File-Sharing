<?php
session_start();

// If there's a link parameter, store it in session and redirect 
if (isset($_GET['link'])) {
    $_SESSION['download_link'] = $_GET['link'];
    header('Location: wait.php');
    exit();
}

// Get link from session
$link = isset($_SESSION['download_link']) ? $_SESSION['download_link'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Please Wait - OneNetly</title>
    <meta name="description" content="Your download will be ready in a few seconds. Please wait while we prepare your file.">
    <link rel="icon" type="image/png" href="icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- AdSense Code -->
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-9354746037074515" crossorigin="anonymous"></script>
</head>
<body class="bg-gray-50">
    <?php include 'header.php'; ?>
<script src="https://freenetly.com/js/adblock-detector.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const detector = new AdBlockDetector({
            warningMessage: 'Please disable your ad blocker to continue',
            warningTitle: '🛡️ Ad Blocker Detected',
            opacity: 0.95,
            blur: true,
            blurAmount: 5,
        });
        detector.init();
    });
</script>
    <!-- Top Ad Unit -->
    <div class="text-center my-2 sm:my-4 px-2 sm:px-4">
        <ins class="adsbygoogle"
             style="display:block"
             data-ad-client="ca-pub-9354746037074515"
             data-ad-slot="4878379783"
             data-ad-format="auto"
             data-full-width-responsive="true"></ins>
        <script>
             (adsbygoogle = window.adsbygoogle || []).push({});
        </script>
    </div>

    <div class="min-h-screen flex items-center justify-center p-2 sm:p-4 relative">
        <!-- Left Side Ad - Only visible on larger screens -->
        <div class="hidden xl:block fixed left-4 top-1/2 transform -translate-y-1/2">
            <ins class="adsbygoogle"
                 style="display:block"
                 data-ad-client="ca-pub-9354746037074515"
                 data-ad-slot="4878379783"
                 data-ad-format="vertical"
                 data-full-width-responsive="false"></ins>
            <script>
                 (adsbygoogle = window.adsbygoogle || []).push({});
            </script>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 md:p-8 max-w-md w-full mx-auto text-center">
            <div class="mb-4 sm:mb-6">
                <div class="mx-auto h-8 w-8 sm:h-12 sm:w-12 text-blue-500 mb-3 sm:mb-4">
                    <svg class="animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-2">Please Wait</h2>
                <p class="text-gray-600 mb-3 sm:mb-4 text-sm sm:text-base">Your download will be ready in <span id="countdown" class="font-semibold">30</span> seconds</p>
                <div class="w-full bg-gray-200 rounded-full h-2 sm:h-2.5 mb-3 sm:mb-4">
                    <div id="progress" class="bg-blue-600 h-2 sm:h-2.5 rounded-full" style="width: 0%"></div>
                </div>
            </div>

            <div id="downloadBtn" class="hidden">
                <a href="<?php echo htmlspecialchars($link); ?>" 
                   class="w-full inline-flex items-center justify-center px-4 sm:px-6 py-2 sm:py-3 border border-transparent text-sm sm:text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Download Now
                </a>
            </div>

            <!-- Mobile Ad Unit - Only visible on smaller screens -->
            <div class="block sm:hidden mt-4">
                <ins class="adsbygoogle"
                     style="display:block"
                     data-ad-client="ca-pub-9354746037074515"
                     data-ad-slot="4878379783"
                     data-ad-format="horizontal"
                     data-full-width-responsive="true"></ins>
                <script>
                     (adsbygoogle = window.adsbygoogle || []).push({});
                </script>
            </div>

            <!-- In-article Ad Unit -->
            <div class="mt-6 sm:mt-8">
                <ins class="adsbygoogle"
                     style="display:block; text-align:center;"
                     data-ad-layout="in-article"
                     data-ad-format="fluid"
                     data-ad-client="ca-pub-9354746037074515"
                     data-ad-slot="4878379783"></ins>
                <script>
                     (adsbygoogle = window.adsbygoogle || []).push({});
                </script>
            </div>
        </div>

        <!-- Right Side Ad - Only visible on larger screens -->
        <div class="hidden xl:block fixed right-4 top-1/2 transform -translate-y-1/2">
            <ins class="adsbygoogle"
                 style="display:block"
                 data-ad-client="ca-pub-9354746037074515"
                 data-ad-slot="4878379783"
                 data-ad-format="vertical"
                 data-full-width-responsive="false"></ins>
            <script>
                 (adsbygoogle = window.adsbygoogle || []).push({});
            </script>
        </div>
    </div>

    <!-- Bottom Ad Unit -->
    <div class="text-center my-2 sm:my-4 px-2 sm:px-4">
        <ins class="adsbygoogle"
             style="display:block"
             data-ad-client="ca-pub-9354746037074515"
             data-ad-slot="4878379783"
             data-ad-format="auto"
             data-full-width-responsive="true"></ins>
        <script>
             (adsbygoogle = window.adsbygoogle || []).push({});
        </script>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        let timeLeft = 30;
        const countdownEl = document.getElementById('countdown');
        const progressEl = document.getElementById('progress');
        const downloadBtn = document.getElementById('downloadBtn');
        
        const timer = setInterval(() => {
            timeLeft--;
            countdownEl.textContent = timeLeft;
            
            // Update progress bar
            const progress = ((30 - timeLeft) / 30) * 100;
            progressEl.style.width = progress + '%';
            
            if (timeLeft <= 0) {
                clearInterval(timer);
                downloadBtn.classList.remove('hidden');
                countdownEl.parentElement.textContent = 'Your download is ready!';
            }
        }, 1000);
    </script>
</body>
</html>