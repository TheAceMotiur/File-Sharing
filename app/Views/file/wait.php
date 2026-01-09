<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Please Wait - <?php echo getSiteName(); ?></title>
    <meta name="description" content="Your download will be ready in a few seconds. Please wait while we prepare your file.">
    <link rel="icon" type="image/png" href="/icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include __DIR__ . '/../partials/header.php'; ?>
    
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-lg p-8 max-w-md w-full text-center">
            <div class="mb-6">
                <div class="mx-auto h-12 w-12 text-blue-500 mb-4">
                    <svg class="animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Please Wait</h2>
                <p class="text-gray-600 mb-4">Your download will be ready in <span id="countdown" class="font-semibold text-blue-600">5</span> seconds</p>
                <div class="w-full bg-gray-200 rounded-full h-2.5 mb-4">
                    <div id="progress" class="bg-blue-600 h-2.5 rounded-full transition-all" style="width: 0%"></div>
                </div>
            </div>
            
            <div class="text-sm text-gray-500 space-y-2">
                <p><i class="fas fa-shield-alt text-green-500"></i> Safe & Secure Download</p>
                <p><i class="fas fa-bolt text-yellow-500"></i> High Speed Servers</p>
                <p><i class="fas fa-check-circle text-blue-500"></i> Verified File</p>
            </div>
        </div>
    </div>

    <script>
    let countdown = 5;
    let progress = 0;
    const downloadUrl = <?php echo json_encode($downloadUrl); ?>;
    
    const countdownEl = document.getElementById('countdown');
    const progressEl = document.getElementById('progress');
    
    const interval = setInterval(() => {
        countdown--;
        progress += 20;
        
        countdownEl.textContent = countdown;
        progressEl.style.width = progress + '%';
        
        if (countdown <= 0) {
            clearInterval(interval);
            window.location.href = downloadUrl;
        }
    }, 1000);
    </script>

    <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
