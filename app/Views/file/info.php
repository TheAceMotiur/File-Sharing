<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - <?php echo getSiteName(); ?></title>
    <link rel="icon" type="image/png" href="/icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <?php include __DIR__ . '/../partials/header.php'; ?>

    <div class="container mx-auto px-4 py-8 max-w-2xl">
        <div class="bg-white rounded-lg shadow p-8">
            <h1 class="text-3xl font-bold mb-6">File Information</h1>
            
            <div class="space-y-4">
                <div class="flex items-center justify-center py-12">
                    <i class="fas fa-file text-8xl text-blue-500"></i>
                </div>
                
                <div class="border-t pt-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-gray-500 text-sm">File Name</p>
                            <p class="font-medium"><?php echo htmlspecialchars($file['original_name']); ?></p>
                        </div>
                        <div>
                            <p class="text-gray-500 text-sm">File Size</p>
                            <p class="font-medium"><?php echo $fileSize; ?></p>
                        </div>
                        <div>
                            <p class="text-gray-500 text-sm">Upload Date</p>
                            <p class="font-medium"><?php echo $uploadDate; ?></p>
                        </div>
                        <div>
                            <p class="text-gray-500 text-sm">Downloads</p>
                            <p class="font-medium"><?php echo $file['downloads'] ?? 0; ?></p>
                        </div>
                    </div>
                </div>

                <div class="flex space-x-4 pt-6">
                    <a href="<?php echo $downloadUrl; ?>" 
                       class="flex-1 bg-blue-500 text-white text-center py-3 rounded-lg hover:bg-blue-600 font-medium">
                        <i class="fas fa-download mr-2"></i>Download
                    </a>
                    <button onclick="copyLink()" 
                            class="flex-1 bg-gray-200 text-gray-700 py-3 rounded-lg hover:bg-gray-300 font-medium">
                        <i class="fas fa-copy mr-2"></i>Copy Link
                    </button>
                </div>

                <div class="pt-4 text-center">
                    <a href="/report/<?php echo $file['unique_id']; ?>" class="text-red-500 hover:text-red-700 text-sm">
                        <i class="fas fa-flag mr-1"></i>Report this file
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
    function copyLink() {
        const url = window.location.href;
        navigator.clipboard.writeText(url).then(() => {
            alert('Link copied to clipboard!');
        });
    }
    </script>

    <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
